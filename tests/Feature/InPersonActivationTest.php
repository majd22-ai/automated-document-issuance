<?php

namespace Tests\Feature;

use App\Contracts\RegistrationSms;
use App\Models\ActivationFingerprintAttempt;
use App\Models\CitizenAccountConfirmation;
use App\Models\CitizenActivationRequest;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Registration\UnavailableSms;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class InPersonActivationTest extends TestCase
{
    private User $staff;

    private User $citizen;

    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:' && ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        DB::beginTransaction();
        $this->beforeApplicationDestroyed(fn () => DB::rollBack());
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['registration.local_otp' => true, 'registration.activation_fingerprints' => true]);
        $this->staff = User::factory()->create(['role' => 'employee']);
        $this->citizen = User::factory()->create(['role' => 'citizen']);
        $this->citizen->forceFill(['national_id' => '1122334455', 'identity_path' => 'has_identity',
            'registration_status' => 'identity_verification_pending', 'phone' => '+967777123456'])->save();
        (new ServiceReviewPermission)->forceFill(['user_id' => $this->staff->id,
            'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'),
            'can_activate' => true, 'granted_by' => $this->staff->id])->save();
        $this->actingAs($this->staff);
    }

    private function prepare(array $overrides = []): CitizenActivationRequest
    {
        $this->post(route('employee.accounts.prepare', $this->citizen), array_replace([
            'identity_type' => 'national_id', 'identity_number' => $this->citizen->national_id, 'phone' => $this->citizen->phone,
        ], $overrides))->assertSessionHasNoErrors()->assertRedirect();

        return CitizenActivationRequest::where('user_id', $this->citizen->id)->sole();
    }

    public function test_popup_prepare_checks_identity_and_returns_server_operation_keys_for_lan_browsers(): void
    {
        $url = route('employee.accounts.prepare', $this->citizen);
        $this->postJson($url, ['identity_type' => 'national_id', 'identity_number' => '99999999', 'phone' => '777123456'])->assertUnprocessable()->assertJsonValidationErrors('identity_number');
        $this->assertDatabaseCount('citizen_activation_requests', 0);
        $this->postJson($url, ['identity_type' => 'national_id', 'identity_number' => $this->citizen->national_id, 'phone' => '777123457'])->assertUnprocessable()->assertJsonValidationErrors('phone_change_confirmed');
        $this->assertDatabaseCount('citizen_activation_requests', 0);
        $response = $this->postJson($url, ['identity_type' => 'national_id', 'identity_number' => $this->citizen->national_id, 'phone' => '777123456'])->assertOk();
        $response->assertJsonPath('phone', '+967777123456')->assertJsonPath('registry.source', 'account_national_id')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertTrue(Str::isUuid($response->json('right_token')));
        $this->assertTrue(Str::isUuid($response->json('left_token')));
        $html = $this->get(route('employee.accounts.show', $this->citizen))->assertOk()->assertSee('data-activation-dialog', false)->assertSee('data-activation-message', false)->assertSee('data-finger="right_index"', false)->assertSee('data-finger="left_index"', false)->getContent();
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);
        $xpath = new \DOMXPath($document);
        foreach (['activation-identity-number', 'activation-phone'] as $id) {
            $this->assertSame('', $xpath->query('//input[@id="'.$id.'"]')->item(0)->getAttribute('value'));
        }
    }

    public function test_separate_code_confirmation_does_not_activate_until_final_button_and_preserves_attempt_budget(): void
    {
        [$visit,$code] = $this->ready();
        $url = route('employee.accounts.verify-code', $this->citizen);
        $this->postJson($url, ['visit_token' => $visit->visit_token, 'otp' => '000000'])->assertUnprocessable();
        $this->postJson($url, ['visit_token' => $visit->visit_token, 'otp' => $code])->assertOk()->assertJsonPath('verified', true);
        $attempts = $visit->fresh()->otp_attempts;
        $this->assertNull($this->citizen->fresh()->account_activated_at);
        $this->postJson($url, ['visit_token' => $visit->visit_token, 'otp' => $code])->assertOk();
        $this->assertSame($attempts, $visit->fresh()->otp_attempts);
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasNoErrors();
        $this->assertTrue($this->citizen->fresh()->hasActivatedIdentity());
        $this->assertSame($attempts, $visit->fresh()->otp_attempts);
        $this->assertStringContainsString('تم تفعيل حسابك', $this->citizen->notifications()->latest()->first()->data['body']);
    }

    public function test_configured_registry_demo_must_match_name_and_number(): void
    {
        config(['civil_registry_demo.enabled' => true, 'civil_registry_demo.records' => [$this->citizen->national_id => ['name' => 'اسم لا يطابق المواطن', 'available' => true]]]);
        $payload = ['identity_type' => 'national_id', 'identity_number' => $this->citizen->national_id, 'phone' => $this->citizen->phone];
        $this->postJson(route('employee.accounts.prepare', $this->citizen), $payload)->assertUnprocessable()->assertJsonValidationErrors('identity_number');
        config(['civil_registry_demo.records' => [$this->citizen->national_id => ['name' => $this->citizen->name, 'available' => true]]]);
        $this->postJson(route('employee.accounts.prepare', $this->citizen), $payload)->assertOk()->assertJsonPath('registry.source', 'local_civil_registry_demo');
    }

    private function finger(CitizenActivationRequest $visit, string $finger, bool $fail = false, ?string $token = null)
    {
        return $this->postJson(route('employee.accounts.finger', $this->citizen), ['visit_token' => $visit->visit_token,
            'operation_token' => $token ?? (string) Str::uuid(), 'finger' => $finger, 'force_failure' => $fail]);
    }

    private function ready(): array
    {
        $visit = $this->prepare();
        $this->finger($visit, 'right_index')->assertOk();
        $this->finger($visit, 'left_index')->assertOk();
        $response = $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertOk();

        return [$visit->fresh(), $response->json('local_code')];
    }

    private function proof(CitizenActivationRequest $visit, string $code): array
    {
        return ['visit_token' => $visit->visit_token, 'otp' => $code, 'operation_token' => (string) Str::uuid(),
            'action' => 'activate', 'national_id' => $this->citizen->national_id, 'identity_confirmed' => true,
            'official_issuance_confirmed' => false, 'evidence_reference' => 'TEST-ONLY reviewed official reference'];
    }

    public function test_complete_local_flow_preserves_account_and_password_and_sources(): void
    {
        $password = $this->citizen->getRawOriginal('password');
        $count = User::count();
        [$visit, $code] = $this->ready();
        $this->assertMatchesRegularExpression('/^[0-9]{6}$/', $code);
        $this->assertNotSame($code, $visit->otp_hash);
        $this->assertTrue(Hash::check($code, $visit->otp_hash));
        $this->assertNull(session('registration_local_code'));
        $data = $this->proof($visit, $code);
        $this->post(route('employee.accounts.confirm', $this->citizen), $data)->assertSessionHasNoErrors();
        $this->assertTrue($this->citizen->fresh()->hasActivatedIdentity());
        $this->assertSame($password, $this->citizen->fresh()->getRawOriginal('password'));
        $this->assertSame($count, User::count());
        $this->assertNull($visit->fresh()->otp_hash);
        $this->assertSame('local_simulation', CitizenAccountConfirmation::sole()->fingerprint_source);
        $this->assertSame('local_trial', CitizenAccountConfirmation::sole()->phone_verification_source);
        $this->post(route('employee.accounts.confirm', $this->citizen), $data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('citizen_account_confirmations', 1);
        $this->assertStringNotContainsString($code, DB::table('audit_events')->get()->toJson());
        $this->assertStringNotContainsString('image', json_encode(ActivationFingerprintAttempt::first()->toArray()));
    }

    public function test_each_finger_can_fail_and_retry_and_no_step_can_be_skipped(): void
    {
        $visit = $this->prepare();
        $this->finger($visit, 'left_index')->assertUnprocessable();
        $this->finger($visit, 'right_index', true)->assertOk()->assertJsonPath('result', 'failed');
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->finger($visit, 'right_index')->assertOk();
        $this->finger($visit, 'left_index', true)->assertOk()->assertJsonPath('result', 'failed');
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->finger($visit, 'left_index')->assertOk();
        $this->assertDatabaseCount('activation_fingerprint_attempts', 4);
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertOk();
    }

    public function test_invalid_expired_and_exhausted_codes_do_not_activate_or_flash_otp(): void
    {
        [$visit, $code] = $this->ready();
        $wrong = $code === '100000' ? '100001' : '100000';
        config(['registration.max_attempts' => 2]);
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $wrong))->assertSessionHasErrors('otp');
        $this->assertArrayNotHasKey('otp', session('_old_input', []));
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $wrong))->assertSessionHasErrors('otp');
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasErrors('otp');
        $this->assertSame(2, $visit->fresh()->otp_attempts);
        $visit->forceFill(['otp_attempts' => 0, 'otp_expires_at' => now()->subSecond()])->save();
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasErrors('otp');
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }

    public function test_production_cannot_send_local_code_or_activate_trial_evidence(): void
    {
        [$visit, $code] = $this->ready();
        $this->app['env'] = 'production';
        $this->finger($visit, 'left_index')->assertForbidden();
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable()->assertJsonMissing(['local_code' => $code]);
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasErrors('otp');
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }

    public function test_permission_actor_and_citizen_scope_checked_at_each_stage(): void
    {
        [$visit, $code] = $this->ready();
        $this->actingAs($this->citizen);
        $this->get(route('employee.accounts.show', $this->citizen))->assertForbidden();
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'employee']));
        $this->finger($visit, 'right_index')->assertForbidden();
        $this->actingAs($this->staff);
        $other = User::factory()->create();
        $this->postJson(route('employee.accounts.code', $other), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->staff->reviewPermissions()->update(['can_activate' => false]);
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertForbidden();
    }

    public function test_duplicate_finger_token_is_idempotent_but_cannot_change_result_or_source(): void
    {
        $visit = $this->prepare();
        $this->post(route('employee.accounts.finger', $this->citizen), ['visit_token' => $visit->visit_token,
            'operation_token' => (string) Str::uuid(), 'finger' => 'right_index', 'template' => 'TEST-ONLY forbidden template'])
            ->assertSessionHasErrors('template');
        $this->assertArrayNotHasKey('template', session('_old_input', []));
        $token = (string) Str::uuid();
        $this->finger($visit, 'right_index', false, $token)->assertOk();
        $this->finger($visit, 'right_index', false, $token)->assertOk();
        $this->finger($visit, 'right_index', true, $token)->assertConflict();
        $this->finger($visit, 'right_index')->assertUnprocessable();
        $this->assertDatabaseCount('activation_fingerprint_attempts', 1);
        $this->expectException(\LogicException::class);
        ActivationFingerprintAttempt::sole()->forceFill(['source' => 'real_device'])->save();
    }

    public function test_phone_change_requires_acknowledgment_and_is_only_applied_on_success(): void
    {
        $newPhone = '+967777123457';
        $this->post(route('employee.accounts.prepare', $this->citizen), ['identity_type' => 'national_id', 'identity_number' => $this->citizen->national_id, 'phone' => $newPhone])->assertSessionHasErrors('phone_change_confirmed');
        $visit = $this->prepare(['phone' => $newPhone, 'phone_change_confirmed' => '1']);
        $this->assertNotSame($newPhone, $this->citizen->fresh()->phone);
        $this->finger($visit, 'right_index')->assertOk();
        $this->finger($visit, 'left_index')->assertOk();
        $code = $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertOk()->json('local_code');
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasNoErrors();
        $this->assertSame($newPhone, $this->citizen->fresh()->phone);
        $this->assertStringContainsString('phone', DB::table('audit_events')->where('event', 'citizen_account.visit_consumed')->value('changes'));
        $this->assertStringNotContainsString($newPhone, DB::table('audit_events')->get()->toJson());
    }

    public function test_birth_certificate_never_substitutes_for_official_issuance(): void
    {
        $this->citizen->forceFill(['national_id' => null, 'identity_path' => 'no_identity', 'registration_status' => 'initial_account'])->save();
        $visit = $this->prepare(['identity_type' => 'birth_certificate', 'identity_number' => '123456789']);
        $this->finger($visit, 'right_index')->assertOk();
        $this->finger($visit, 'left_index')->assertOk();
        $code = $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertOk()->json('local_code');
        $proof = array_replace($this->proof($visit, $code), ['national_id' => '123456789']);
        $this->post(route('employee.accounts.confirm', $this->citizen), $proof)->assertSessionHasErrors('national_id');
        $proof = array_replace($proof, ['action' => 'confirm_issuance', 'official_issuance_confirmed' => true, 'request_id' => 999999]);
        $this->post(route('employee.accounts.confirm', $this->citizen), $proof)->assertSessionHasErrors('request_id');
        $this->assertNull($this->citizen->fresh()->national_id);
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }

    public function test_resend_cooldown_limit_and_new_visit_invalidate_previous_evidence(): void
    {
        [$visit, $oldCode] = $this->ready();
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $newVisit = $this->prepare();
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($newVisit, $oldCode))->assertSessionHasErrors('otp');
        $this->assertDatabaseCount('activation_fingerprint_attempts', 2);
    }

    public function test_unavailable_or_failing_sms_never_leaves_a_valid_code(): void
    {
        $visit = $this->prepare();
        $this->finger($visit, 'right_index')->assertOk();
        $this->finger($visit, 'left_index')->assertOk();
        $this->app->instance(RegistrationSms::class, new UnavailableSms);
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable()->assertJsonMissingPath('local_code');
        $this->app->instance(RegistrationSms::class, new class implements RegistrationSms
        {
            public function send(string $phone, string $code): void
            {
                throw new \RuntimeException('TEST-ONLY provider unavailable');
            }
        });
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->assertNull($visit->fresh()->otp_hash);
        $this->assertSame(1, $visit->fresh()->otp_sends);
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }

    public function test_resends_invalidate_old_code_and_stop_after_send_limit(): void
    {
        [$visit, $oldCode] = $this->ready();
        config(['registration.max_sends' => 2]);
        $this->travel(61)->seconds();
        $newCode = $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertOk()->json('local_code');
        $this->assertNotSame($oldCode, $newCode);
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $oldCode))->assertSessionHasErrors('otp');
        $this->travel(61)->seconds();
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }

    public function test_changed_account_invalidates_visit_and_another_granted_employee_cannot_use_it(): void
    {
        [$visit, $code] = $this->ready();
        $otherStaff = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $otherStaff->id,
            'government_service_id' => GovernmentService::where('code', 'identity-first-issue')->value('id'),
            'can_activate' => true, 'granted_by' => $this->staff->id])->save();
        $this->actingAs($otherStaff)->get(route('employee.accounts.show', $this->citizen))->assertOk()->assertDontSee('id="activation-checks"', false);
        $this->postJson(route('employee.accounts.code', $this->citizen), ['visit_token' => $visit->visit_token])->assertUnprocessable();
        $this->actingAs($this->staff);
        $this->citizen->forceFill(['phone' => '+967777123459'])->save();
        $this->post(route('employee.accounts.confirm', $this->citizen), $this->proof($visit, $code))->assertSessionHasErrors('activation');
        $this->assertNull($this->citizen->fresh()->account_activated_at);
    }
}
