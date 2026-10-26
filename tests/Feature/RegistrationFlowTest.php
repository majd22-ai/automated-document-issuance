<?php

namespace Tests\Feature;

use App\Contracts\RegistrationSms;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceEligibility;
use App\Services\ServiceRequestSubmission;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class RegistrationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Registration tests require an isolated testing database.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['name' => 'مواطن اختبار', 'identity_path' => 'has_identity', 'national_id' => '12345678901', 'username' => 'citizen_test', 'phone' => '777123456', 'email' => 'registration@example.test', 'password' => 'StrongPass123!', 'password_confirmation' => 'StrongPass123!', 'terms' => '1', 'captcha' => 'ABC234'], $overrides);
    }

    private function start(array $overrides = [])
    {
        return $this->withSession(['registration_captcha' => ['hash' => Hash::make('ABC234'), 'expires' => now()->addMinutes(5)->timestamp]])->post(route('register.post'), $this->data($overrides));
    }

    private function sms(): object
    {
        $fake = new class implements RegistrationSms
        {
            public array $messages = [];

            public bool $fail = false;

            public function send(string $phone, string $code): void
            {
                if ($this->fail) {
                    throw new \RuntimeException('Simulated unavailable transport');
                }
                $this->messages[] = [$phone, $code];
            }
        };
        $this->app->instance(RegistrationSms::class, $fake);

        return $fake;
    }

    public function test_local_otp_completes_without_claiming_phone_ownership(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'local';
        config(['registration.local_otp' => true]);
        $this->start()->assertRedirect('/register/verify');
        $code = session('registration_local_code');
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->get('/register/verify')->assertOk()->assertSee($code)->assertHeader('Cache-Control', 'no-store, private');
        $this->post('/register/verify', ['code' => $code])->assertRedirect('/register/complete');
        $this->assertNull(User::sole()->phone_verified_at);
        $this->assertNull(session('registration_local_code'));
    }

    public function test_production_never_enables_local_otp(): void
    {
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->app['env'] = 'production';
        config(['registration.local_otp' => true]);
        $this->start()->assertSessionHasErrors('phone');
        $this->assertNull(session('registration_local_code'));
        $this->assertDatabaseCount('users', 0);
    }

    public function test_registration_page_has_guarded_steps_and_modal(): void
    {
        $this->get(route('register'))->assertOk()->assertSee('إنشاء حساب جديد — بوابة خدمات السجل المدني')->assertSee('بيانات المستخدم')->assertSee('لا أملك رقم هوية')->assertSee('registration.js')->assertDontSee('confirmed_phone')->assertDontSee('modal-phone')->assertSee('registration-feedback');
        $this->get('/register/verify')->assertRedirect(route('register'));
        $this->get('/register/complete')->assertRedirect(route('register'));
    }

    public function test_no_sms_provider_fails_closed_without_creating_account(): void
    {
        $this->start()->assertSessionHasErrors('phone');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_verified_phone_creates_citizen_without_verifying_civil_identity(): void
    {
        $sms = $this->sms();
        $this->start()->assertRedirect('/register/verify');
        $this->assertDatabaseCount('users', 0);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertRedirect('/register/complete');
        $user = User::sole();
        $this->assertSame('citizen_test', $user->username);
        $this->assertSame('12345678901', $user->national_id);
        $this->assertSame('+967777123456', $user->phone);
        $this->assertSame('identity_verification_pending', $user->registration_status);
        $this->assertNotNull($user->phone_verified_at);
        $this->assertNull($user->email_verified_at);
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertSame('citizen', $user->role);
        $this->assertAuthenticatedAs($user);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertRedirect();
        $this->assertDatabaseCount('users', 1);
    }

    public function test_initial_account_never_uses_phone_as_national_identity(): void
    {
        $sms = $this->sms();
        $this->start(['identity_path' => 'no_identity', 'national_id' => ''])->assertRedirect('/register/verify');
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertRedirect('/register/complete');
        $user = User::sole();
        $this->assertNull($user->nationalIdentity());
        $this->assertSame('initial_account', $user->registration_status);
        $this->assertSame('citizen_test', $user->username);
    }

    public function test_validation_cannot_be_bypassed_in_browser(): void
    {
        $this->start(['username' => 'x', 'email' => 'invalid', 'phone' => '123', 'password' => 'abcdefgh', 'password_confirmation' => 'different', 'terms' => '0', 'role' => 'admin', 'is_active' => true, 'registration_status' => 'identity_verified'])
            ->assertSessionHasErrors(['username', 'email', 'phone', 'password', 'terms', 'role', 'is_active', 'registration_status']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_captcha_is_server_validated_and_password_not_flashed(): void
    {
        $this->start(['captcha' => 'wrong'])->assertSessionHasErrors('captcha')->assertSessionMissing('_old_input.password');
    }

    public function test_invalid_single_phone_and_no_identity_rejects_identity_value(): void
    {
        $this->start(['phone' => 'invalid', 'identity_path' => 'no_identity'])->assertSessionHasErrors(['phone', 'national_id']);
    }

    public function test_wrong_expired_and_exhausted_codes_do_not_create_users(): void
    {
        $this->sms();
        $this->start();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        }
        $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->travel(11)->minutes();
        $this->post('/register/verify', ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_resend_is_throttled_then_replaces_code_and_edit_invalidates_attempt(): void
    {
        $sms = $this->sms();
        $this->start();
        $this->post('/register/resend')->assertSessionHasErrors('code');
        $this->assertCount(1, $sms->messages);
        $this->travel(61)->seconds();
        $this->post('/register/resend')->assertRedirect('/register/verify');
        $this->assertCount(2, $sms->messages);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertSessionHasErrors('code');
        $this->post('/register/edit')->assertRedirect(route('register'));
        $this->post('/register/verify', ['code' => $sms->messages[1][1]])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_expired_correct_code_is_rejected(): void
    {
        $sms = $this->sms();
        $this->start();
        $this->travel(11)->minutes();
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertSessionHasErrors('code')->assertSessionMissing('_old_input.code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_correct_code_after_attempt_limit_is_rejected_even_after_resend(): void
    {
        $sms = $this->sms();
        $this->start();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register/verify', ['code' => '000000']);
        }
        $this->travel(61)->seconds();
        $this->post('/register/resend');
        $this->post('/register/verify', ['code' => $sms->messages[1][1]])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_attempt_is_bound_to_session_and_contains_no_plaintext_password_or_otp(): void
    {
        $sms = $this->sms();
        $this->start();
        $row = DB::table('registration_attempts')->sole();
        $this->assertTrue(Hash::check($sms->messages[0][1], $row->otp_hash));
        $this->assertStringNotContainsString('StrongPass123!', $row->payload);
        $this->assertStringNotContainsString('registration@example.test', $row->payload);
        $payload = json_decode(Crypt::decryptString($row->payload), true);
        $this->assertTrue(Hash::check('StrongPass123!', $payload['password']));
        $this->withSession(['registration_token' => 'another-session'])->post('/register/verify', ['code' => $sms->messages[0][1]])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_previous_civil_record_without_card_is_not_initial_account(): void
    {
        $sms = $this->sms();
        $this->start(['identity_path' => 'existing_record']);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]]);
        $this->assertSame('existing_record', User::sole()->identity_path);
        $this->assertSame('identity_verification_pending', User::sole()->registration_status);
    }

    public function test_existing_and_new_identity_and_username_conflicts_are_rejected(): void
    {
        User::factory()->create(['username' => '12345678901', 'email' => 'legacy@example.test']);
        $this->start()->assertSessionHasErrors('national_id');
        $user = User::factory()->create(['username' => 'citizen_test', 'email' => 'registration@example.test']);
        $user->forceFill(['national_id' => '99887766', 'registration_status' => 'identity_verification_pending'])->save();
        $this->start(['national_id' => '99887766'])->assertSessionHasErrors(['username', 'email', 'national_id']);
    }

    public function test_initial_account_scope_is_enforced_in_server_eligibility(): void
    {
        $user = User::factory()->create(['username' => 'not_a_national_id']);
        $user->forceFill(['registration_status' => 'initial_account', 'identity_path' => 'no_identity'])->save();
        $eligibility = app(ServiceEligibility::class);
        $passport = GovernmentService::where('code', 'passport-first-issue')->firstOrFail();
        $result = $eligibility->evaluate($user, $passport);
        $this->assertFalse($result->eligible());
        $this->assertArrayHasKey('initial_account_scope', $result->checks);
        $first = $eligibility->evaluate($user, GovernmentService::where('code', 'identity-first-issue')->firstOrFail());
        $this->assertArrayNotHasKey('initial_account_scope', $first->checks);
        $this->assertNull($user->nationalIdentity());
    }

    public function test_initial_account_can_be_assessed_by_internal_id_for_first_issue_only(): void
    {
        $sms = $this->sms();
        $this->start(['identity_path' => 'no_identity', 'national_id' => '']);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]]);
        $citizen = User::sole();
        $this->post(route('logout'));
        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin'])->save();
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->fill(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST-ONLY service approval', 'required_fields' => ['username']])->save();
        $this->actingAs($admin)->post(route('admin.services.assessments.store', $service), [
            'citizen_account_id' => $citizen->id, 'expected_rules_version' => $service->rules_version,
            'identity_verified' => true, 'conditions_met' => true, 'is_blocked' => false,
            'verification_reference' => 'TEST-ONLY in-person evidence reference',
        ])->assertSessionHasNoErrors()->assertRedirect();
        $preview = app(ServiceRequestSubmission::class)->preview($citizen, $service, []);
        $this->assertSame($citizen->id, $preview['user_id']);
        $this->assertNull($citizen->fresh()->nationalIdentity());
        $this->assertSame('initial_account', $citizen->fresh()->registration_status);
    }

    public function test_profile_cannot_upgrade_identity_status_or_verification(): void
    {
        $user = User::factory()->create();
        $user->forceFill(['registration_status' => 'initial_account'])->save();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'registration_status' => 'identity_verified', 'phone_verified_at' => now()->toDateTimeString()])->assertSessionHasErrors(['registration_status', 'phone_verified_at']);
        $this->assertSame('initial_account', $user->fresh()->registration_status);
    }

    public function test_prune_only_removes_expired_attempts_and_terms_are_explicit_draft(): void
    {
        $this->sms();
        $this->start();
        $this->artisan('registration:prune')->assertSuccessful();
        $this->assertDatabaseCount('registration_attempts', 1);
        $this->travel(11)->minutes();
        $this->artisan('registration:prune')->assertSuccessful();
        $this->assertDatabaseCount('registration_attempts', 0);
        $this->get(route('terms'))->assertOk()->assertSee('مسودة للمراجعة');
    }

    public function test_captcha_image_is_random_private_and_expired_challenge_is_rejected(): void
    {
        $first = $this->get(route('register.captcha'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml')->assertHeader('Cache-Control', 'no-store, private')->getContent();
        $second = $this->get(route('register.captcha'))->getContent();
        $this->assertNotSame($first, $second);
        $this->withSession(['registration_captcha' => ['hash' => Hash::make('ABC234'), 'expires' => now()->subSecond()->timestamp]])->post(route('register.post'), $this->data())->assertSessionHasErrors('captcha');
    }

    public function test_resend_limit_is_not_reset_and_phone_correction_changes_destination(): void
    {
        $sms = $this->sms();
        $this->start();
        for ($i = 0; $i < 2; $i++) {
            $this->travel(61)->seconds();
            $this->post('/register/resend')->assertSessionHasNoErrors();
        }
        $this->travel(61)->seconds();
        $this->post('/register/resend')->assertSessionHasErrors('code');
        $this->assertCount(3, $sms->messages);
        $this->post('/register/edit');
        $this->start(['phone' => '771123456', 'confirmed_phone' => '771123456'])->assertRedirect('/register/verify');
        $this->assertSame('+967771123456', $sms->messages[3][0]);
        $this->post('/register/verify', ['code' => $sms->messages[2][1]])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_correct_code_cannot_create_account_when_unique_data_changed_while_pending(): void
    {
        $sms = $this->sms();
        $this->start();
        User::factory()->create(['email' => 'registration@example.test']);
        $this->post('/register/verify', ['code' => $sms->messages[0][1]])->assertSessionHasErrors('code');
        $this->assertDatabaseCount('users', 1);
        $this->assertGuest();
    }

    public function test_profile_phone_change_removes_phone_verification(): void
    {
        $user = User::factory()->create(['phone' => '+967777123456']);
        $user->forceFill(['phone_verified_at' => now(), 'registration_status' => 'identity_verification_pending'])->save();
        $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => '+967771123456'])->assertSessionHasNoErrors();
        $this->assertNull($user->fresh()->phone_verified_at);
    }

    public function test_official_identity_input_does_not_accept_empty_identity_or_fake_verification_fields(): void
    {
        $this->start(['national_id' => '', 'phone_verified_at' => now()->toDateTimeString(), 'email_verified_at' => now()->toDateTimeString()])->assertSessionHasErrors(['national_id', 'phone_verified_at', 'email_verified_at']);
        $this->assertDatabaseCount('users', 0);
    }

    public function test_parallel_sends_for_same_phone_are_locked(): void
    {
        $sms = $this->sms();
        $key = 'registration-sms:'.hash_hmac('sha256', '+967777123456', config('app.key'));
        $lock = Cache::lock($key.':lock', 20);
        $this->assertTrue($lock->get());
        try {
            $this->start()->assertSessionHasErrors('phone');
            $this->assertCount(0, $sms->messages);
        } finally {
            $lock->release();
        }
    }

    public function test_failed_resend_keeps_prior_code_and_database_rate_limit(): void
    {
        config(['cache.default' => 'database']);
        $sms = $this->sms();
        $this->start();
        $originalHash = DB::table('registration_attempts')->sole()->otp_hash;
        $this->travel(61)->seconds();
        $sms->fail = true;
        $this->post('/register/resend')->assertSessionHasErrors('phone');
        $key = 'registration-sms:'.hash_hmac('sha256', '+967777123456', config('app.key'));
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 1));
        $this->assertSame($originalHash, DB::table('registration_attempts')->sole()->otp_hash);
        $this->assertCount(1, $sms->messages);
    }

    public function test_initial_account_cannot_prepare_attachments_for_other_services(): void
    {
        $citizen = User::factory()->create();
        $citizen->forceFill(['registration_status' => 'initial_account'])->save();
        $service = GovernmentService::where('code', 'passport-first-issue')->firstOrFail();
        $service->fill(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST-ONLY approval'])->save();
        $this->actingAs($citizen)->get(route('citizen.services.attachments.index', $service))->assertForbidden();
    }

    public function test_spaces_do_not_replace_password_symbols_on_server(): void
    {
        $this->start(['password' => 'Strong 123', 'password_confirmation' => 'Strong 123'])->assertSessionHasErrors('password');
    }

    public function test_captcha_precheck_is_server_validated_before_enabling_next(): void
    {
        $this->withSession(['registration_captcha' => ['hash' => Hash::make('ABC234'), 'expires' => now()->addMinutes(5)->timestamp]]);
        $this->postJson('/register/captcha/check', ['captcha' => 'WRONG2'])->assertOk()->assertJson(['valid' => false]);
        $this->postJson('/register/captcha/check', ['captcha' => 'abc234'])->assertOk()->assertJson(['valid' => true]);
        $this->travel(6)->minutes();
        $this->postJson('/register/captcha/check', ['captcha' => 'ABC234'])->assertJson(['valid' => false]);
        $this->assertDatabaseCount('users', 0);
    }
}
