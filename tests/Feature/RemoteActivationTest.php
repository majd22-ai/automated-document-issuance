<?php

namespace Tests\Feature;

use App\Contracts\RegistrationSms;
use App\Models\ActivationFingerprintAttempt;
use App\Models\CitizenAccountConfirmation;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Registration\LocalSms;
use App\Services\Registration\RemoteActivation;
use App\Services\Registration\UnavailableSms;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class RemoteActivationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config(['registration.local_otp' => true]);
        $this->app->bind(RegistrationSms::class, LocalSms::class);
        Storage::fake('attachments');
    }

    private function citizen(): User
    {
        return User::factory()->create(['role' => 'citizen', 'is_active' => true, 'national_id' => '98765432101', 'identity_path' => 'has_identity', 'registration_status' => 'identity_verification_pending', 'phone' => '+967777123456']);
    }

    private function staff(bool $grant = true): User
    {
        $u = User::factory()->create(['role' => 'employee', 'is_active' => true]);
        if ($grant) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $u->id, 'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'), 'can_activate' => true, 'granted_by' => $u->id])->save();
        }

        return $u;
    }

    private function verified(User $u): void
    {
        $code = app(RemoteActivation::class)->send($u, ['national_id' => $u->national_id, 'phone' => '+967777123457']);
        $this->assertTrue(Hash::check($code, $u->activationRequest()->first()->otp_hash));
        $this->assertSame(180, (int) $u->activationRequest()->first()->otp_sent_at->diffInSeconds($u->activationRequest()->first()->otp_expires_at));
        app(RemoteActivation::class)->verify($u, $code);
        $this->assertNull($u->activationRequest()->first()->otp_hash);
    }

    private function upload(User $u, string $kind = 'identity_card'): void
    {
        $this->actingAs($u)->post(route('citizen.account.remote.submit'), ['document_kind' => $kind, 'no_physical_card' => '1', 'selfie_capture_acknowledged' => '1', 'declaration' => '1', 'front' => UploadedFile::fake()->image('front.jpg', 600, 600), 'back' => UploadedFile::fake()->image('back.jpg', 600, 600), 'selfie' => UploadedFile::fake()->image('selfie.jpg', 600, 600)])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function approval(User $u): array
    {
        $r = $u->activationRequest()->first();

        return ['action' => 'activate', 'national_id' => $u->national_id, 'identity_confirmed' => true, 'official_issuance_confirmed' => false, 'evidence_reference' => 'LOCAL-REMOTE-REVIEW', 'operation_token' => (string) Str::uuid(), 'visit_token' => $r->visit_token, 'revision' => $r->revision, 'documents_reviewed' => true, 'selfie_reviewed' => true];
    }

    public function test_complete_remote_flow_private_images_and_approval_without_fingerprints(): void
    {
        $u = $this->citizen();
        $staff = $this->staff();
        $password = $u->password;
        $count = User::count();
        $this->verified($u);
        $this->upload($u);
        $r = $u->activationRequest()->first();
        Storage::disk('attachments')->assertExists($r->evidence['front']['path']);
        $this->actingAs($staff)->get(route('employee.accounts.show', $u))->assertOk()->assertDontSee('محاكاة السبابة');
        $this->get(route('account-activation.image', [$u, 'front']))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $data = $this->approval($u);
        $this->post(route('employee.accounts.confirm', $u), $data)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertTrue($u->fresh()->hasActivatedIdentity());
        $this->assertSame($password, $u->fresh()->password);
        $this->assertSame($count, User::count());
        $this->assertSame('+967777123457', $u->fresh()->phone);
        $this->assertSame(0, ActivationFingerprintAttempt::count());
        $this->post(route('employee.accounts.confirm', $u), $data)->assertSessionHasNoErrors();
        $this->assertSame(1, CitizenAccountConfirmation::count());
    }

    public function test_invalid_identity_and_expired_or_wrong_codes_are_rejected(): void
    {
        $u = $this->citizen();
        $this->actingAs($u)->post(route('citizen.account.remote.code'), ['national_id' => '11111', 'phone' => '777123456', 'phone_change_confirmed' => '1'])->assertSessionHasErrors('national_id');
        $code = app(RemoteActivation::class)->send($u, ['national_id' => $u->national_id, 'phone' => $u->phone]);
        $this->post(route('citizen.account.remote.verify'), ['otp' => '000000'])->assertSessionHasErrors('otp');
        $this->assertSame(1, $u->activationRequest()->first()->otp_attempts);
        $this->travel(181)->seconds();
        $this->post(route('citizen.account.remote.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertFalse($u->fresh()->hasActivatedIdentity());
    }

    public function test_private_images_and_decisions_require_permission_and_birth_requires_extra_review(): void
    {
        $u = $this->citizen();
        $this->verified($u);
        $this->upload($u, 'birth_certificate');
        $this->actingAs($this->staff(false))->get(route('account-activation.image', [$u, 'front']))->assertForbidden();
        $this->actingAs($u)->post(route('employee.accounts.confirm', $u), $this->approval($u))->assertForbidden();
        $staff = $this->staff();
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $u), $this->approval($u))->assertSessionHasErrors('documents_reviewed');
        $this->post(route('employee.accounts.review', $u), ['decision' => 'requires_correction', 'reason' => 'يرجى إعادة رفع صورة واضحة', 'revision' => $u->activationRequest()->first()->revision])->assertSessionHasNoErrors();
        $this->assertSame('requires_correction', $u->activationRequest()->first()->status);
        $this->upload($u, 'birth_certificate');
        $this->assertCount(1, $u->activationRequest()->first()->evidence_history);
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $u), $this->approval($u) + ['birth_reviewed' => true])->assertSessionHasNoErrors();
        $this->assertTrue($u->fresh()->hasActivatedIdentity());
    }

    public function test_five_wrong_codes_block_the_correct_code_and_resend_rotates_it(): void
    {
        $u = $this->citizen();
        $code = app(RemoteActivation::class)->send($u, ['national_id' => $u->national_id, 'phone' => $u->phone]);
        $this->actingAs($u);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('citizen.account.remote.verify'), ['otp' => '000000'])->assertSessionHasErrors('otp');
        }
        $this->post(route('citizen.account.remote.verify'), ['otp' => $code])->assertSessionHasErrors('otp');
        $this->assertNull($u->activationRequest()->first()->otp_verified_at);
        $this->post(route('citizen.account.remote.code'), ['national_id' => $u->national_id, 'phone' => $u->phone, 'phone_change_confirmed' => 1])->assertStatus(429);
        $this->travel(61)->seconds();
        $this->post(route('citizen.account.remote.code'), ['national_id' => $u->national_id, 'phone' => $u->phone, 'phone_change_confirmed' => 1])->assertSessionHas('local_activation_code');
        $this->assertSame(0, $u->activationRequest()->first()->otp_attempts);
        $this->get(route('citizen.account.status'))->assertOk()->assertSee('data-auto-open', false)->assertSee('رمز تجريبي محلي');
    }

    public function test_unverified_upload_invalid_images_and_stale_revision_cannot_activate(): void
    {
        $u = $this->citizen();
        $staff = $this->staff();
        app(RemoteActivation::class)->send($u, ['national_id' => $u->national_id, 'phone' => $u->phone]);
        $this->actingAs($u)->post(route('citizen.account.remote.submit'), ['document_kind' => 'identity_card', 'declaration' => 1, 'selfie_capture_acknowledged' => 1, 'front' => UploadedFile::fake()->create('bad.pdf', 10), 'back' => UploadedFile::fake()->image('back.jpg', 100, 100), 'selfie' => UploadedFile::fake()->image('selfie.jpg', 600, 600)])->assertSessionHasErrors(['front', 'back']);
        $this->assertSame('collecting', $u->activationRequest()->first()->status);
        $this->travel(61)->seconds();
        $this->verified($u);
        $this->upload($u);
        $data = $this->approval($u);
        $data['revision']++;
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $u), $data)->assertSessionHasErrors('activation');
        $this->assertFalse($u->fresh()->hasActivatedIdentity());
    }

    public function test_rejection_notifies_citizen_and_prevents_approval_and_local_proof_is_not_portable(): void
    {
        $u = $this->citizen();
        $staff = $this->staff();
        $this->verified($u);
        $this->upload($u);
        $data = $this->approval($u);
        $this->app['env'] = 'production';
        $this->actingAs($staff)->post(route('employee.accounts.confirm', $u), $data)->assertSessionHasErrors('activation');
        $this->app['env'] = 'local';
        $this->post(route('employee.accounts.review', $u), ['decision' => 'rejected', 'reason' => 'الصورة غير واضحة للمراجعة', 'revision' => $data['revision']])->assertSessionHasNoErrors();
        $this->post(route('employee.accounts.confirm', $u), $data)->assertSessionHasErrors('activation');
        $this->assertSame('rejected', $u->activationRequest()->first()->status);
        $this->assertSame('account_status', $u->notifications()->latest()->first()->data['kind']);
        $this->assertFalse($u->fresh()->hasActivatedIdentity());
    }

    public function test_production_missing_sms_and_initial_account_cannot_use_remote_activation(): void
    {
        $u = $this->citizen();
        $this->app['env'] = 'production';
        $this->app->bind(RegistrationSms::class, UnavailableSms::class);
        $this->actingAs($u)->post(route('citizen.account.remote.code'), ['national_id' => $u->national_id, 'phone' => '777123456', 'phone_change_confirmed' => '1'])->assertSessionHasErrors('phone');
        $this->assertNull($u->activationRequest()->first());
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        $u->forceFill(['registration_status' => 'initial_account', 'identity_path' => 'no_identity'])->save();
        $this->post(route('citizen.account.remote.code'),['national_id' => $u->national_id, 'phone' => '777123456', 'phone_change_confirmed' => '1'])->assertSessionHasErrors('activation');
    }
}
