<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Registration\MockCivilSystems;
use App\Services\Registration\MockIdentityVerification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class LinkedRegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        config(['citizen_mock.enabled' => true]);
        Storage::fake('attachments');
        $this->artisan('db:seed', ['--class' => 'LinkedCivilMockSeeder', '--force' => true])->assertSuccessful();
    }

    private function data(array $extra = []): array
    {
        return $extra + ['identity_path' => 'has_identity', 'name' => 'مواطن محاكاة ناجح', 'national_id' => '99010000001', 'email' => 'mock@example.test', 'phone' => '777123456', 'terms' => '1'];
    }

    public function test_registry_requires_matching_name_and_card(): void
    {
        $this->post('/register', $this->data(['national_id' => '99999999999']))->assertSessionHasErrors('national_id');
        $this->post('/register', $this->data(['name' => 'اسم مخالف']))->assertSessionHasErrors('national_id');
    }

    public function test_otp_then_password_creates_pending_account_without_logging_in(): void
    {
        $this->post('/register', $this->data())->assertRedirect(route('register.verify'));
        $code = Crypt::decryptString(session('mock_registration_code'));
        $this->assertFalse(Hash::check('000000', DB::table('registration_attempts')->first()->otp_hash));
        $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->post('/register/verify', ['code' => $code])->assertRedirect(route('register.password'));
        $this->post('/register/password', ['password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertRedirect(route('login'));
        $user = User::sole();
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertSame('pending_activation', $user->registration_status);
        $this->assertNotEmpty($user->digital_account_id);
        $this->assertFalse($user->hasActivatedIdentity());
        $this->assertGuest();
        $this->post('/login', ['identifier' => $user->national_id, 'password' => 'StrongPass123!'])->assertRedirect(route('citizen.account.status'));
    }

    public function test_birth_and_family_lookup_is_linked_and_not_name_only(): void
    {
        $data = ['identity_path' => 'no_identity', 'name' => 'طفل محاكاة أسري كامل', 'birth_date' => '2005-02-03', 'birth_place' => 'منطقة اختبار', 'father_name' => 'أب محاكاة أسرة تجريبية', 'mother_name' => 'أم محاكاة أسرة تجريبية'];
        $this->assertSame('MATCH', app(MockCivilSystems::class)->lookup($data)['registry_result']);
        $this->assertSame('NOT_FOUND', app(MockCivilSystems::class)->lookup(array_replace($data, ['birth_date' => '2000-01-01']))['registry_result']);
        $this->assertSame('REVIEW', app(MockCivilSystems::class)->lookup($data + ['father_national_id' => '00000'])['registry_result']);
    }

    private function citizen(string $fixture = 'match'): User
    {
        $p = DB::table('mock_civil_persons')->where('fixture_key', $fixture)->first();
        $user = User::factory()->create()->forceFill(['national_id' => $p->national_id, 'mock_person_id' => $p->id, 'digital_account_id' => Str::uuid(), 'activation_source' => 'linked_local_mock', 'registration_status' => 'pending_activation', 'identity_path' => 'has_identity']);
        $user->save();

        return $user;
    }

    private function capture(User $u, array $extra = [])
    {
        return $this->actingAs($u)->post(route('citizen.account.mock-face'), $extra + ['selfie' => UploadedFile::fake()->image('face.jpg', 600, 600), 'operation_token' => (string) Str::uuid(), 'camera_acknowledged' => 1]);
    }

    public function test_face_match_activates_only_the_existing_mock_account(): void
    {
        $u = $this->citizen();
        $password = $u->password;
        $this->capture($u, ['result' => 'NO_MATCH'])->assertRedirect(route('citizen.account.status'));
        $this->assertTrue($u->fresh()->hasActivatedIdentity());
        $this->assertSame($password, $u->fresh()->password);
        $this->assertSame(1, User::count());
        $this->assertDatabaseHas('mock_face_attempts', ['user_id' => $u->id, 'result' => 'MATCH', 'source' => 'deterministic_local_mock']);
        $this->assertGreaterThan(0, $u->notifications()->count());
        $this->get(route('citizen.account.status'))->assertOk()->assertSee('محاكاة');
        config(['citizen_mock.enabled' => false]);
        $this->assertFalse($u->fresh()->hasActivatedIdentity());
    }

    public function test_review_and_missing_reference_never_auto_activate(): void
    {
        foreach (['review' => 'REVIEW', 'no-photo' => 'NO_REFERENCE_IMAGE'] as $key => $result) {
            $u = $this->citizen($key);
            $this->capture($u)->assertRedirect();
            $this->assertSame('under_review', $u->fresh()->registration_status);
            $this->assertDatabaseHas('citizen_activation_requests', ['user_id' => $u->id, 'document_kind' => $result, 'status' => 'pending']);
            $this->assertFalse($u->fresh()->hasActivatedIdentity());
        }
    }

    public function test_no_match_and_liveness_failure_are_bounded_and_sent_to_review(): void
    {
        foreach (['no-match', 'liveness', 'quality'] as $fixture) {
            $u = $this->citizen($fixture);
            for ($i = 0; $i < 3; $i++) {
                $this->capture($u)->assertRedirect();
            }
            $this->assertSame('under_review', $u->fresh()->registration_status);
            $this->capture($u)->assertStatus(409);
            $this->assertSame(3, DB::table('mock_face_attempts')->where('user_id', $u->id)->count());
        }
    }

    public function test_operation_replay_and_bad_image_are_rejected(): void
    {
        $u = $this->citizen('no-match');
        $token = (string) Str::uuid();
        $this->capture($u, ['selfie' => UploadedFile::fake()->image('small.jpg', 50, 50)])->assertSessionHasErrors('selfie');
        $this->capture($u, ['operation_token' => $token])->assertRedirect();
        $this->capture($u, ['operation_token' => $token])->assertStatus(409);
        $this->assertSame(1, DB::table('mock_face_attempts')->count());
    }

    public function test_pending_account_cannot_access_services_or_other_citizens_images(): void
    {
        $u = $this->citizen();
        $other = $this->citizen('review');
        $this->actingAs($u)->get(route('citizen.dashboard'))->assertRedirect(route('citizen.account.status'));
        $this->get(route('mock-activation.image', [$other, 'reference']))->assertForbidden();
        $this->get(route('mock-activation.image', [$u, 'reference']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/admin/mock-systems')->assertForbidden();
    }

    public function test_otp_expires_limits_attempts_and_cannot_be_reused(): void
    {
        $this->post('/register', $this->data());
        $code = Crypt::decryptString(session('mock_registration_code'));
        $this->assertNotSame($code, DB::table('registration_attempts')->first()->otp_hash);
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->travel(4)->minutes();
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertSame(0, User::count());
    }

    public function test_card_record_cannot_be_registered_twice(): void
    {
        $this->citizen();
        $this->post('/register', $this->data())->assertSessionHasErrors('national_id');
        $this->assertSame(1, User::count());
    }

    public function test_unmatched_no_card_account_has_reference_but_no_national_id(): void
    {
        $data = $this->data(['identity_path' => 'no_identity', 'national_id' => null, 'name' => 'مواطن خيالي غير موجود', 'birth_date' => '2001-01-01', 'birth_place' => 'مكان خيالي', 'father_name' => 'اسم أب خيالي كامل', 'mother_name' => 'اسم أم خيالية كاملة']);
        $this->post('/register', $data)->assertRedirect();
        $code = Crypt::decryptString(session('mock_registration_code'));
        $this->post('/register/verify', ['code' => $code])->assertRedirect();
        $this->post('/register/password', ['username' => 'mock_new', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertRedirect(route('login'));
        $u = User::sole();
        $this->assertNull($u->national_id);
        $this->assertNull($u->mock_person_id);
        $this->assertSame('under_review', $u->registration_status);
        $this->assertNotNull($u->activationRequest);
        $this->assertNull($u->digital_account_id);
        $this->assertSame('mock_new', $u->username);
        $this->get(route('login'))->assertSee($u->username);
        $this->post('/login', ['identifier' => $u->username, 'password' => 'StrongPass123!'])->assertRedirect(route('citizen.account.status'));
    }

    public function test_authorized_employee_reviews_but_cannot_replay_or_edit_registry(): void
    {
        $u = $this->citizen('review');
        $this->capture($u);
        $staff = User::factory()->create(['role' => 'employee']);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => $service->id, 'can_activate' => true, 'granted_by' => User::factory()->create(['role' => 'admin'])->id])->save();
        $row = $u->activationRequest;
        $data = ['revision' => $row->revision, 'decision' => 'approved', 'reason' => 'تمت مراجعة الهوية التجريبية'];
        $this->actingAs(User::factory()->create(['role' => 'employee']))->post(route('employee.accounts.mock-review', $u), $data)->assertForbidden();
        $this->actingAs($staff)->get(route('employee.accounts.show', $u))->assertOk()->assertSee('مراجعة التفعيل — محاكاة محلية');
        $this->post(route('employee.accounts.mock-review', $u), $data)->assertRedirect();
        $this->assertTrue($u->fresh()->hasActivatedIdentity());
        $this->post(route('employee.accounts.mock-review', $u), $data)->assertStatus(409);
        $this->assertDatabaseHas('audit_events', ['event' => 'citizen_account.mock_review', 'actor_id' => $staff->id]);
        $audit = AuditEvent::where('event', 'citizen_account.mock_review')->firstOrFail();
        $this->assertSame($data['reason'], $audit->private_reason);
        $this->assertNotSame($data['reason'], DB::table('audit_events')->where('id', $audit->id)->value('private_reason'));
    }

    public function test_no_card_linked_family_retains_first_issuance_constraints(): void
    {
        $this->post('/register', $this->data(['identity_path' => 'no_identity', 'national_id' => null, 'name' => 'طفل محاكاة أسري كامل', 'birth_date' => '2005-02-03', 'birth_place' => 'منطقة اختبار', 'father_name' => 'أب محاكاة أسرة تجريبية', 'mother_name' => 'أم محاكاة أسرة تجريبية']))->assertRedirect();
        $code = Crypt::decryptString(session('mock_registration_code'));
        $this->post('/register/password', ['password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertForbidden();
        $this->post('/register/verify', ['code' => $code])->assertRedirect();
        $this->post('/register/verify', ['code' => $code])->assertSessionHasErrors('code');
        $this->post('/register/password', ['username' => 'family_mock', 'password' => 'StrongPass123!', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertSame(0, User::count());
        $this->post('/register/password', ['password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertSessionHasErrors('username');
        $this->post('/register/password', ['username' => '12345', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertSessionHasErrors('username');
        $this->assertSame(0, User::count());
        $this->post('/register/password', ['username' => 'family_mock', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!'])->assertRedirect(route('login'));
        $u = User::sole();
        $this->assertSame('initial_account', $u->registration_status);
        $this->assertNull($u->national_id);
        $this->assertNotNull($u->mock_person_id);
        $this->assertFalse($u->hasActivatedIdentity());
        $this->assertTrue($u->allowsCitizenService(GovernmentService::where('code', 'identity-first-issue')->firstOrFail()));
        $this->assertFalse($u->allowsCitizenService(GovernmentService::where('code', 'passport-first-issue')->firstOrFail()));
    }

    public function test_resend_waits_and_invalidates_previous_code(): void
    {
        $this->post('/register', $this->data());
        $first = Crypt::decryptString(session('mock_registration_code'));
        $this->post('/register/resend')->assertSessionHasErrors('code');
        $this->travel(61)->seconds();
        $this->post('/register/resend')->assertRedirect();
        $next = Crypt::decryptString(session('mock_registration_code'));
        $this->post('/register/verify', ['code' => $first])->assertSessionHasErrors('code');
        $this->post('/register/verify', ['code' => $next])->assertRedirect(route('register.password'));
    }

    public function test_review_retake_is_a_new_bounded_round_and_citizen_cannot_approve(): void
    {
        $u = $this->citizen('no-match');
        for ($i = 0; $i < 3; $i++) {
            $this->capture($u)->assertRedirect();
        }
        $row = $u->activationRequest;
        $data = ['revision' => $row->revision, 'decision' => 'retake_photo', 'reason' => 'أعد التقاط الصورة التجريبية'];
        $this->actingAs($u)->post(route('employee.accounts.mock-review', $u), $data)->assertForbidden();
        $staff = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_activate' => true, 'granted_by' => User::factory()->create(['role' => 'admin'])->id])->save();
        $this->actingAs($staff)->post(route('employee.accounts.mock-review', $u), $data)->assertRedirect();
        $this->assertSame('action_required', $u->fresh()->registration_status);
        $this->travel(61)->seconds();
        for ($i = 0; $i < 3; $i++) {
            $this->capture($u)->assertRedirect();
        }
        $this->capture($u)->assertStatus(409);
        $this->assertSame(6, DB::table('mock_face_attempts')->where('user_id', $u->id)->count());
    }

    public function test_mock_system_dialogs_are_admin_only_and_disabled_outside_local_testing(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get(route('admin.mock-systems.index'))->assertOk()->assertSee('mock-system-0')->assertSee('mock-system-6');
        $this->app['env'] = 'production';
        $this->get(route('admin.mock-systems.index'))->assertForbidden();
        $this->assertFalse(MockCivilSystems::enabled());
    }

    public function test_birth_record_correction_and_staff_rejection_keep_history(): void
    {
        $p = DB::table('mock_civil_persons')->where('fixture_key', 'incomplete')->first();
        $u = User::factory()->create()->forceFill(['identity_path' => 'no_identity', 'activation_source' => 'linked_local_mock', 'registration_status' => 'under_review', 'mock_person_id' => $p->id]);
        $u->save();
        $row = app(MockIdentityVerification::class)->queue($u, 'REVIEW', 'سجل غير مكتمل');
        $staff = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_activate' => true, 'granted_by' => User::factory()->create(['role' => 'admin'])->id])->save();
        $this->actingAs($staff)->post(route('employee.accounts.mock-review', $u), ['revision' => $row->revision, 'decision' => 'requires_correction', 'reason' => 'استكمل معلومات الأسرة'])->assertRedirect();
        $this->actingAs($u)->post(route('citizen.account.mock-correction'), ['name' => $p->name, 'birth_date' => $p->birth_date, 'birth_place' => $p->birth_place, 'father_name' => $p->father_name, 'mother_name' => $p->mother_name])->assertRedirect();
        $this->assertSame('under_review', $u->fresh()->registration_status);
        $this->assertNull($u->fresh()->national_id);
        $row->refresh();
        $this->actingAs($staff)->post(route('employee.accounts.mock-review', $u), ['revision' => $row->revision, 'decision' => 'rejected', 'reason' => 'بيانات القيد تحتاج استكمالًا آخر'])->assertRedirect();
        $this->assertSame(2, AuditEvent::where('event', 'citizen_account.mock_review')->count());
        $this->assertSame(2, AuditEvent::where('event', 'citizen_account.mock_review')->whereNotNull('private_reason')->count());
        $this->assertFalse($u->fresh()->hasActivatedIdentity());
    }
}
