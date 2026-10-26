<?php

namespace Tests\Feature;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\GovernmentService;
use App\Models\ManualPayment;
use App\Models\MedicalExamination;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Integrations\IntegrationManager;
use App\Services\Issuance\IssueDocuments;
use App\Services\Operations\RequestCharges;
use App\Services\Requirements\EvidenceRequirements;
use App\Services\Review\ApprovalReadiness;
use App\Services\Review\ReviewSnapshot;
use App\Services\ServiceEligibility;
use App\Services\ServiceRequestSubmission;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\TestVerificationProvider;
use Tests\TestCase;

class ElectronicIntegrationTest extends TestCase
{
    public function test_overlapping_new_key_is_explicitly_rejected_instead_of_implicitly_consumed(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $overlapStatus = null;
        $secondKey = (string) Str::uuid();
        $this->fake(function ($http) use ($employee, $request, $secondKey, &$overlapStatus) {
            try {
                app(IntegrationManager::class)->start($employee, $request, 'identity', $secondKey);
                $overlapStatus = 200;
            } catch (HttpException $e) {
                $overlapStatus = $e->getStatusCode();
            }

            return Http::response(['status' => 'verified', 'correlation' => $http['correlation'], 'subject' => $http['subject']]);
        });
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $this->assertSame(409, $overlapStatus);
        $this->assertSame('verified', $attempt->status);
        Http::assertSentCount(1);
    }

    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
            $this->travelBack();
        });
        Http::preventStrayRequests();
    }

    private function fixtures(array $extra = []): array
    {
        $citizen = User::factory()->create(['username' => 'TEST-ID-'.Str::uuid()]);
        $employee = User::factory()->create(['role' => 'employee']);
        $admin = User::factory()->create(['role' => 'admin']);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', ...$extra]);
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_verify' => true, 'granted_by' => $admin->id])->save();
        $submit = app(ServiceRequestSubmission::class);
        $service->refresh();
        $citizen->refresh();
        $request = $submit->submit($citizen, $service, (string) Str::uuid(), $submit->preview($citizen, $service, []));

        return [$citizen, $employee, $service, $request, $admin];
    }

    private function configure(GovernmentService $service): void
    {
        config(['integrations.providers.test' => ['label' => 'TEST ONLY provider', 'enabled' => true, 'approved' => true, 'adapter' => TestVerificationProvider::class, 'base_url' => 'https://verification.example.test', 'allowed_host' => 'verification.example.test', 'endpoints' => ['identity' => '/verify', 'document' => '/verify', 'medical' => '/verify', 'payment' => '/verify'], 'token' => 'TEST-SECRET', 'timeout' => 5, 'connect_timeout' => 2, 'retries' => 0]]);
        $service->update(['integration_rules' => ['identity' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
    }

    private function response(string $status = 'verified'): void
    {
        $this->fake(fn ($request) => Http::response(['status' => $status, 'correlation' => $request['correlation'], 'subject' => $request['subject']], 200));
    }

    private function fake(callable $callback): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callback);
    }

    public function test_unconfigured_provider_is_explicit_and_cannot_verify(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $service->update(['integration_rules' => ['identity' => ['required' => true, 'provider' => 'missing', 'max_age_minutes' => 60]]]);
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $this->assertSame('not_configured', $attempt->status);
        Http::assertNothingSent();
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id.'/verifications')->assertOk()->assertSee('خدمة التحقق غير مهيأة');
    }

    public function test_bound_identity_response_is_verified_and_replay_does_not_call_provider_twice(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->response();
        $key = (string) Str::uuid();
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', $key);
        $this->assertSame('verified', $attempt->status);
        $this->assertSame(1, $attempt->attempts);
        $again = app(IntegrationManager::class)->start($employee, $request, 'identity', $key);
        $this->assertSame($attempt->id, $again->id);
        Http::assertSentCount(1);
        $json = json_encode($attempt->toArray());
        $this->assertStringNotContainsString('TEST-SECRET', $json);
        $this->assertStringNotContainsString($citizen->username, $json);
        $this->assertSame('verified', app(IntegrationManager::class)->status($request->fresh(), 'identity')['status']);
    }

    public function test_rejected_mismatch_and_unknown_are_distinct(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        foreach (['rejected', 'mismatch', 'unknown'] as $state) {
            $this->response($state);
            $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
            $this->assertSame($state, $attempt->status);
        }
    }

    public function test_invalid_or_unbound_response_is_never_verified(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->fake(fn () => Http::response(['status' => 'verified', 'correlation' => 'other-request', 'subject' => 'other-citizen']));
        $this->assertSame('unknown', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
        $this->fake(fn () => Http::response('not json', 200));
        $this->assertSame('unknown', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
    }

    public function test_connection_failure_and_rate_limit_cannot_become_verified(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->fake(fn () => throw new ConnectionException('SECRET raw timeout'));
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $this->assertSame('unavailable', $attempt->status);
        $this->assertStringNotContainsString('SECRET', json_encode($attempt->toArray()));
        $this->fake(fn () => Http::response([], 429));
        $this->assertSame('unavailable', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
    }

    public function test_only_explicitly_authorized_employee_can_start_and_other_citizen_cannot_read(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $body = ['kind' => 'identity', 'idempotency_key' => (string) Str::uuid()];
        $this->actingAs($citizen)->post('/employee/applications/'.$request->id.'/verifications', $body)->assertForbidden();
        $other = User::factory()->create();
        $this->actingAs($other)->get('/citizen/applications/'.$request->id.'/verifications')->assertNotFound();
        $staff = User::factory()->create(['role' => 'employee']);
        $this->actingAs($staff)->post('/employee/applications/'.$request->id.'/verifications', $body)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_expiry_and_changed_identity_invalidate_verified_result(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->response();
        app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $citizen->forceFill(['username' => 'CHANGED TEST ID'])->save();
        $this->assertSame('expired', app(IntegrationManager::class)->status($request->fresh(), 'identity')['status']);
        app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $this->travel(61)->minutes();
        $this->assertSame('expired', app(IntegrationManager::class)->status($request->fresh(), 'identity')['status']);
    }

    public function test_no_external_requirement_does_not_block_existing_service(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->assertTrue(app(ServiceEligibility::class)->evaluate($citizen, $service, $request)->eligible());
        $this->assertSame('not_required', app(IntegrationManager::class)->status($request, 'identity')['status']);
        Http::assertNothingSent();
    }

    public function test_required_external_check_blocks_approval_and_expired_result_blocks_issuance_readiness(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->assessments()->update(['rules_version' => $service->fresh()->rules_version]);
        ServiceReviewPermission::where('user_id', $employee->id)->update(['can_review' => true, 'can_approve' => true]);
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::Accepted, $employee);
        try {
            $request->transitionTo(ServiceRequestStatus::Approved, $employee);
            $this->fail('Approval bypassed mandatory integration');
        } catch (ValidationException) {
            $this->assertSame('accepted', $request->fresh()->status);
        }
        $this->response();
        app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $request = $request->fresh()->transitionTo(ServiceRequestStatus::Approved, $employee);
        $this->assertSame('approved', $request->status);
        $this->assertTrue(app(ApprovalReadiness::class)->check($request)['eligible']);
        $this->travel(61)->minutes();
        $this->expectException(ValidationException::class);
        app(ApprovalReadiness::class)->check($request);
    }

    public function test_forged_results_and_provider_urls_are_rejected_and_admin_log_is_protected(): void
    {
        [$citizen,$employee,$service,$request,$admin] = $this->fixtures();
        $this->configure($service);
        $body = ['kind' => 'identity', 'idempotency_key' => (string) Str::uuid(), 'status' => 'verified', 'url' => 'http://127.0.0.1'];
        $this->actingAs($employee)->postJson('/employee/applications/'.$request->id.'/verifications', $body)->assertUnprocessable();
        $this->actingAs($citizen)->get('/admin/integrations')->assertForbidden();
        $this->actingAs($admin)->get('/admin/integrations')->assertOk()->assertDontSee('TEST-SECRET')->assertDontSee('verification.example.test');
        Http::assertNothingSent();
    }

    public function test_insecure_endpoint_and_redirect_are_not_trusted(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->response();
        config(['integrations.providers.test.base_url' => 'http://verification.example.test']);
        $this->assertSame('failed', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
        Http::assertNothingSent();
        config(['integrations.providers.test.base_url' => 'https://verification.example.test']);
        $this->fake(fn () => Http::response([], 302, ['Location' => 'http://127.0.0.1/secret']));
        $this->assertSame('failed', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
        Http::assertSentCount(1);
    }

    public function test_local_attachment_success_does_not_prove_official_document_authenticity(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->update(['integration_rules' => ['document' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        $doc = $citizen->documents()->create(['document_number' => 'TEST-DOC-'.Str::uuid(), 'document_type' => 'TEST identity', 'document_type_code' => 'identity', 'status' => 'active', 'issued_at' => today()->subYear(), 'expires_at' => today()->addYear()]);
        $this->response();
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'document', (string) Str::uuid(), $doc->id);
        $this->assertSame('verified', $attempt->status);
        $this->assertSame($doc->id, $attempt->target_document_id);
        Http::assertSent(fn ($r) => ! array_key_exists('file', $r['subject']) && $r['subject']['document_number'] === $doc->document_number);
        $this->assertSame(0, $request->attachments()->count());
    }

    public function test_medical_query_sends_no_private_notes_and_does_not_replace_local_result(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->update(['integration_rules' => ['medical' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        $exam = (new MedicalExamination)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'rules_version' => $service->rules_version, 'result' => 'pending', 'source_reference' => 'TEST medical reference', 'private_notes' => 'SECRET DIAGNOSIS', 'recorded_by' => $employee->id, 'recorded_at' => now()]);
        $exam->save();
        $this->response();
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'medical', (string) Str::uuid());
        $this->assertSame('verified', $attempt->status);
        Http::assertSent(fn ($r) => ! str_contains(json_encode($r->data()), 'SECRET DIAGNOSIS') && ! array_key_exists('result', $r['subject']));
        $this->assertSame('pending', $exam->fresh()->result);
        $this->assertStringNotContainsString('SECRET DIAGNOSIS', json_encode($attempt->toArray()));
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id.'/verifications')->assertOk()->assertDontSee('TEST medical reference')->assertDontSee('SECRET DIAGNOSIS');
    }

    public function test_payment_confirmation_is_bound_to_existing_receipt_and_never_creates_duplicate_local_receipt(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures(['fee' => '100.00', 'currency' => 'YER']);
        $this->configure($service);
        $service->update(['integration_rules' => ['payment' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);

        $charge = app(RequestCharges::class)->ensure($request);
        // Existing local receipt is evidence for a read-only reconciliation query, never manufactured by integration.
        (new ManualPayment)->forceFill(['request_charge_id' => $charge->id, 'amount' => '100.00', 'currency' => 'YER', 'receipt_reference' => 'TEST-RECEIPT-'.Str::uuid(), 'method' => 'cash', 'paid_at' => today(), 'recorded_by' => $employee->id, 'recorded_at' => now()])->save();
        $this->response();
        $key = (string) Str::uuid();
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'payment', $key);
        $this->assertSame('verified', $attempt->status);
        app(IntegrationManager::class)->start($employee, $request, 'payment', $key);
        Http::assertSentCount(1);
        $this->assertSame('due', $charge->fresh()->status);
        $this->assertSame(1, $charge->payment()->count());
        $this->fake(fn ($r) => Http::response(['status' => 'verified', 'correlation' => $r['correlation'], 'subject' => array_merge($r['subject'], ['amount' => '1.00'])]));
        $this->assertSame('unknown', app(IntegrationManager::class)->start($employee, $request, 'payment', (string) Str::uuid())->status);
    }

    public function test_transient_retry_is_bounded_and_plain_auth_error_is_not_retried(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        config(['integrations.providers.test.retries' => 2]);
        $this->fake(fn () => Http::response([], 503));
        $attempt = app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        $this->assertSame('unavailable', $attempt->status);
        $this->assertSame(3, $attempt->attempts);
        Http::assertSentCount(3);
        $this->fake(fn () => Http::response(['secret' => 'DO NOT STORE'], 401));
        $this->assertSame('failed', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
        Http::assertSentCount(1);
    }

    public function test_snapshot_requirement_cannot_be_removed_for_existing_request_and_conflicting_provider_fails_closed(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $snapshot = $request->service_snapshot;
        $snapshot['integration_rules'] = $service->fresh()->integration_rules;
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['service_snapshot' => json_encode($snapshot)]);
        $service->update(['integration_rules' => []]);
        $this->assertTrue(app(IntegrationManager::class)->status($request->fresh(), 'identity')['required']);
        $service->update(['integration_rules' => ['identity' => ['required' => true, 'provider' => 'different', 'max_age_minutes' => 60]]]);
        $this->expectException(ValidationException::class);
        app(IntegrationManager::class)->status($request->fresh(), 'identity');
    }

    public function test_disabled_provider_and_changed_configuration_invalidate_previous_success(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->response();
        app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid());
        config(['integrations.providers.test.enabled' => false]);
        $this->assertSame('not_configured', app(IntegrationManager::class)->status($request->fresh(), 'identity')['status']);
        config(['integrations.providers.test.enabled' => true, 'integrations.providers.test.token' => 'ROTATED TEST SECRET']);
        $this->assertSame('expired', app(IntegrationManager::class)->status($request->fresh(), 'identity')['status']);
    }

    public function test_missing_medical_or_payment_evidence_is_unknown_and_makes_no_call(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->update(['integration_rules' => ['medical' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60], 'payment' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        foreach (['medical', 'payment'] as $kind) {
            $attempt = app(IntegrationManager::class)->start($employee, $request, $kind, (string) Str::uuid());
            $this->assertSame('unknown', $attempt->status);
            $this->assertSame('missing_data', $attempt->error_code);
            $this->assertSame(0, $attempt->attempts);
        }
        Http::assertNothingSent();
        $this->assertSame(0, $request->examinations()->count());
    }

    public function test_foreign_document_is_rejected_before_any_outbound_request(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->update(['integration_rules' => ['document' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        $other = User::factory()->create();
        $doc = $other->documents()->create(['document_number' => 'OTHER-'.Str::uuid(), 'document_type' => 'TEST identity', 'document_type_code' => 'identity', 'status' => 'active']);
        $this->actingAs($employee)->postJson('/employee/applications/'.$request->id.'/verifications', ['kind' => 'document', 'idempotency_key' => (string) Str::uuid(), 'target_document_id' => $doc->id])->assertUnprocessable();
        Http::assertNothingSent();
    }

    public function test_admin_can_grant_only_specific_service_verification_and_invalid_rules_are_rejected(): void
    {
        [$citizen,$employee,$service,$request,$admin] = $this->fixtures();
        ServiceReviewPermission::where('user_id', $employee->id)->update(['can_verify' => false]);
        $this->actingAs($admin)->get('/admin/review-permissions')->assertOk()->assertSee('can_verify', false);
        $this->post('/admin/users/'.$employee->id.'/review-permissions', ['government_service_id' => $service->id, 'can_review' => false, 'can_approve' => false, 'can_assign' => false, 'can_verify' => true])->assertRedirect();
        $this->assertTrue($employee->fresh()->can('verifyExternal', $request));
        $otherService = GovernmentService::whereKeyNot($service->id)->first();
        $this->assertFalse($employee->fresh()->canReviewService($otherService->id, 'can_verify'));
        $this->patchJson('/admin/services/'.$service->id, ['name' => $service->name, 'integration_rules' => ['identity' => ['required' => true, 'provider' => 'https://evil.test', 'max_age_minutes' => 0]]])->assertUnprocessable();
        $this->actingAs($employee)->get('/employee/verifications')->assertOk()->assertSee($request->reference);
    }

    public function test_provider_simulation_is_rejected_outside_testing_environment(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->response();
        $this->app->instance('env', 'production');
        try {
            $this->assertFalse(app(IntegrationManager::class)->configured('test'));
            $this->assertSame('not_configured', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
            Http::assertNothingSent();
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public function test_verified_document_is_reused_by_existing_authenticity_evidence_without_trusting_other_documents(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $service->update(['integration_rules' => ['document' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        $doc = $citizen->documents()->create(['document_number' => 'AUTH-'.Str::uuid(), 'document_type' => 'TEST', 'document_type_code' => 'identity', 'status' => 'active']);
        $other = $citizen->documents()->create(['document_number' => 'AUTH-OTHER-'.Str::uuid(), 'document_type' => 'TEST', 'document_type_code' => 'identity', 'status' => 'active']);
        $evidence = app(EvidenceRequirements::class);
        $this->assertSame(RequirementState::Unverifiable, $evidence->authenticity($citizen, $service, $doc, $request)->state);
        $this->response();
        app(IntegrationManager::class)->start($employee, $request, 'document', (string) Str::uuid(), $doc->id);
        $this->assertSame(RequirementState::Met, $evidence->authenticity($citizen, $service, $doc, $request->fresh())->state);
        $this->assertSame(RequirementState::Unverifiable, $evidence->authenticity($citizen, $service, $other, $request->fresh())->state);
    }

    public function test_external_document_check_can_be_performed_after_submission_without_bypassing_approval(): void
    {
        [$citizen,$employee,$original,$request,$admin] = $this->fixtures();
        $this->configure($original);
        $service = GovernmentService::where('code', 'identity-renewal')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST ONLY', 'renewal_statuses' => ['active'], 'verification_rules' => ['document_authenticity_required' => true],
            'issuance_rules' => ['enabled' => true, 'source_reference' => 'TEST ONLY', 'expiry' => ['required' => true, 'validity_days' => 60], 'include_year' => true, 'electronic_copy' => 'none', 'citizen_download' => false, 'delivery' => ['methods' => ['pickup'], 'centers' => ['TEST center']], 'completion_stage' => 'delivery'],
            'integration_rules' => ['document' => ['required' => true, 'provider' => 'test', 'max_age_minutes' => 60]]]);
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        $doc = $citizen->documents()->create(['document_number' => 'RENEW-INTEGRATION-'.Str::uuid(), 'document_type' => 'TEST', 'document_type_code' => 'identity', 'status' => 'active', 'issued_at' => today()->subYear(), 'expires_at' => today()->addYear()]);
        $citizen->documents()->create(['document_number' => 'NEWER-'.Str::uuid(), 'document_type' => 'TEST', 'document_type_code' => 'identity', 'status' => 'active', 'issued_at' => today()->subMonth(), 'expires_at' => today()->addYear()]);
        $submit = app(ServiceRequestSubmission::class);
        $service->refresh();
        $citizen->refresh();
        $application = $submit->submit($citizen, $service, (string) Str::uuid(), $submit->preview($citizen, $service, []));
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_verify' => true, 'granted_by' => $admin->id])->save();
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service, $application, ServiceRequestStatus::Processing)->eligible());
        $this->response();
        app(IntegrationManager::class)->start($employee, $application, 'document', (string) Str::uuid(), $doc->id);
        $this->assertTrue(app(ServiceEligibility::class)->evaluate($citizen, $service, $application->fresh(), ServiceRequestStatus::Processing)->eligible());
        ServiceReviewPermission::where('user_id', $employee->id)->where('government_service_id', $service->id)->update(['can_review' => true, 'can_approve' => true, 'can_issue' => true]);
        foreach ([ServiceRequestStatus::UnderReview, ServiceRequestStatus::Accepted, ServiceRequestStatus::Approved] as $next) {
            $application = $application->fresh()->transitionTo($next, $employee);
        }
        $issued = app(IssueDocuments::class)->issue($employee, $application, ['operation_token' => (string) Str::uuid(), 'previous_document_id' => $doc->id, 'expected_version' => $application->review_version, 'expected_rules_version' => $service->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($application)]);
        $this->assertSame($doc->id, $issued->previous_document_id);
        $this->assertSame('superseded', $doc->fresh()->status);
    }

    public function test_input_change_while_provider_is_running_prevents_acceptance(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->configure($service);
        $this->fake(function ($http) use ($citizen) {
            $citizen->forceFill(['username' => 'CHANGED WHILE RUNNING'])->save();

            return Http::response(['status' => 'verified', 'correlation' => $http['correlation'], 'subject' => $http['subject']]);
        });
        $this->assertSame('expired', app(IntegrationManager::class)->start($employee, $request, 'identity', (string) Str::uuid())->status);
    }
}
