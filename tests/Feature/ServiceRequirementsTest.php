<?php

namespace Tests\Feature;

use App\Contracts\ServiceEvidenceSource;
use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\Requirements\EvidenceLookup;
use App\Services\ServiceEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ServiceRequirementsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
    }

    private function citizen(): User
    {
        $user = User::factory()->create();
        $user->forceFill(['username' => 'CHECK-'.$user->id])->save();

        return $user;
    }

    private function service(User $citizen, string $code = 'identity-first-issue'): GovernmentService
    {
        $service = GovernmentService::where('code', $code)->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST policy']);
        $this->assess($citizen, $service);

        return $service;
    }

    private function assess(User $citizen, GovernmentService $service): void
    {
        $admin = $this->citizen();
        $admin->forceFill(['role' => 'admin'])->save();
        $service->assessments()->updateOrCreate(['user_id' => $citizen->id], ['rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'driving_requirements_met' => true, 'age_requirement_met' => true, 'is_blocked' => false, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'verification_reference' => 'TEST verification']);
    }

    private function files(User $citizen, GovernmentService $service): void
    {
        $service->update(['required_documents' => ['الصورة الشخصية'], 'verification_rules' => ['documents' => [['code' => 'photo', 'name' => 'الصورة الشخصية', 'kind' => 'photo', 'allowed_mime_types' => ['image/jpeg', 'image/png'], 'requires_review' => true]]]]);
        $this->assess($citizen, $service);
    }

    private function source(array $records = [], bool $available = true, bool $throws = false): void
    {
        $this->app->instance(ServiceEvidenceSource::class, new class($records, $available, $throws) implements ServiceEvidenceSource
        {
            public function __construct(private array $records, private bool $available, private bool $throws) {}

            public function lookup(User $citizen, GovernmentService $service, string $kind, string $key, ?CitizenServiceRequest $request = null): EvidenceLookup
            {
                if ($this->throws) {
                    throw new \RuntimeException('INTERNAL SECRET SOURCE FAILURE');
                }

                return new EvidenceLookup($this->available, $this->records[$kind.':'.$key] ?? []);
            }
        });
    }

    private function proof(User $citizen, GovernmentService $service, array $extra = []): array
    {
        return array_merge(['user_id' => $citizen->id, 'service_id' => $service->id, 'request_id' => null, 'trusted' => true, 'source_reference' => 'TEST source', 'verified_at' => now()->toIso8601String()], $extra);
    }

    public function test_all_known_required_checks_met_and_missing_basic_data_is_unmet(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertTrue($result->eligible());
        $this->assertTrue($result->allMet());
        $citizen->forceFill(['username' => null])->save();
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertFalse($result->eligible());
        $this->assertSame(RequirementState::Unmet, $result->checks['citizen_data']->state);
    }

    public function test_missing_source_is_unverifiable_and_absent_upload_from_available_source_is_unmet(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $this->source(available: false);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::Unverifiable, $result->checks['attachment.photo']->state);
        $this->source();
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::Unmet, $result->checks['attachment.photo']->state);
        $this->assertFalse($result->checks['attachment.photo']->details['uploaded']);
    }

    public function test_uploaded_file_requires_correct_type_and_authorized_review(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $file = $this->proof($citizen, $service, ['uploaded' => true, 'mime_type' => 'image/jpeg', 'review_status' => 'pending']);
        $this->source(['attachment:photo' => [$file]]);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::NeedsReview, $result->checks['attachment.photo']->state);
        $this->assertFalse($result->eligible());
        $this->source(['attachment:photo' => [array_merge($file, ['review_status' => 'approved'])]]);
        $report = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertTrue($report->eligible(), json_encode($report->toArray(), JSON_UNESCAPED_UNICODE));
        $this->source(['attachment:photo' => [array_merge($file, ['review_status' => 'approved', 'mime_type' => 'application/pdf'])]]);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
    }

    public function test_expired_document_is_detected_according_to_configured_renewal_policy(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen, 'identity-renewal');
        $service->update(['renewal_statuses' => ['valid'], 'verification_rules' => ['renewal_validity' => 'current']]);
        $this->assess($citizen, $service);
        $citizen->documents()->create(['document_type' => 'بطاقة', 'document_type_code' => 'identity', 'document_number' => 'TEST-DOC', 'status' => 'valid', 'issued_at' => today()->subYears(2), 'expires_at' => today()->subDay()]);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::Unmet, $result->checks['previous_document']->state);
        $this->assertSame('expired', $result->checks['previous_document']->details['validity']);
        $service->update(['verification_rules' => ['renewal_validity' => 'any']]);
        $this->assess($citizen, $service);
        $report = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertTrue($report->eligible(), json_encode($report->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_document_presence_does_not_prove_authenticity(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen, 'identity-renewal');
        $service->update(['renewal_statuses' => ['valid'], 'verification_rules' => ['document_authenticity_required' => true]]);
        $this->assess($citizen, $service);
        $document = $citizen->documents()->create(['document_type' => 'بطاقة', 'document_type_code' => 'identity', 'document_number' => 'UNVERIFIED', 'status' => 'valid', 'issued_at' => today()->subYear(), 'expires_at' => today()->addYear()]);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::Unverifiable, $result->checks['document_authenticity']->state);
        $this->assertFalse($result->eligible());
        $this->source(['document:'.$document->id => [$this->proof($citizen, $service, ['document_id' => $document->id, 'result' => 'verified'])]]);
        $report = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertTrue($report->eligible(), json_encode($report->toArray(), JSON_UNESCAPED_UNICODE));
    }

    public function test_medical_pass_failure_and_expiry_require_trusted_data(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen, 'license-first-issue');
        $service->update(['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]]]);
        $this->assess($citizen, $service);
        $exam = $this->proof($citizen, $service, ['result' => 'passed', 'examined_at' => today()->subDay()->toDateString(), 'expires_at' => today()->addMonth()->toDateString()]);
        $this->source(['medical:driving' => [$exam]]);
        $report = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertTrue($report->eligible(), json_encode($report->toArray(), JSON_UNESCAPED_UNICODE));
        $this->source(['medical:driving' => [array_merge($exam, ['result' => 'failed'])]]);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['medical']->state);
        $this->source(['medical:driving' => [array_merge($exam, ['expires_at' => today()->subDay()->toDateString()])]]);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['medical']->state);
    }

    public function test_source_outage_and_missing_external_restriction_clearance_never_pass(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $service->update(['verification_rules' => ['restriction_source' => 'external']]);
        $this->assess($citizen, $service);
        $this->source([], true, true);
        $result = app(ServiceEligibility::class)->evaluate($citizen, $service);
        $this->assertSame(RequirementState::Unverifiable, $result->checks['restrictions']->state);
        $this->assertStringNotContainsString('INTERNAL SECRET', json_encode($result->toArray()));
        $this->source();
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
    }

    public function test_private_block_reason_is_not_exposed_to_citizen(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $service->assessments()->update(['is_blocked' => true, 'block_reason' => 'CONFIDENTIAL CASE', 'public_block_reason' => 'راجع الجهة المختصة']);
        $this->actingAs($citizen)->get($service->publicUrl())->assertOk()->assertDontSee('CONFIDENTIAL CASE')->assertSee('راجع الجهة المختصة');
    }

    public function test_foreign_evidence_and_foreign_request_context_cannot_satisfy_requirements(): void
    {
        $citizen = $this->citizen();
        $other = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $this->source(['attachment:photo' => [$this->proof($other, $service, ['uploaded' => true, 'mime_type' => 'image/jpeg', 'review_status' => 'approved'])]]);
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
        $foreign = $other->serviceRequests()->create(['reference' => 'OTHER-REQUEST', 'service_code' => $service->code]);
        $this->actingAs($citizen)->post('/citizen/applications/'.$foreign->id.'/requirements/check')->assertNotFound();
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service, $foreign)->eligible());
    }

    public function test_pending_mandatory_file_blocks_direct_final_post_and_recheck_updates_snapshot(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $request = $citizen->serviceRequests()->create(['reference' => 'CURRENT', 'service_code' => $service->code, 'status' => 'under_review']);
        $this->files($citizen, $service);
        $this->source();
        $this->actingAs($citizen)->post('/citizen/applications/'.$request->id.'/requirements/check')->assertOk()->assertSee('غير مستوفى');
        $this->assertFalse($request->fresh()->requirements_result['eligible']);
        $file = $this->proof($citizen, $service, ['uploaded' => true, 'mime_type' => 'image/png', 'review_status' => 'approved']);
        $this->source(['attachment:photo' => [$file]]);
        $this->post('/citizen/applications/'.$request->id.'/requirements/check')->assertOk();
        $this->assertTrue($request->fresh()->requirements_result['eligible']);
        $this->assertSame('under_review', $request->fresh()->status);
    }

    public function test_future_acceptance_hook_rechecks_mandatory_requirements_without_granting_permissions(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $request = $citizen->serviceRequests()->create(['reference' => 'REVIEW-HOOK', 'service_code' => $service->code, 'status' => 'under_review']);
        $request->forceFill(['request_type' => 'issue'])->save();
        $this->files($citizen, $service);
        $this->source();
        $this->assertFalse($citizen->can('transition', $request));
        Gate::before(fn ($user, $ability) => $ability === 'transition' ? true : null); // Test-only future review authorization.
        $this->expectException(ValidationException::class);
        $request->transitionTo(ServiceRequestStatus::Accepted, $citizen);
    }

    public function test_pending_file_cannot_be_bypassed_at_final_submission_with_browser_flags(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $file = $this->proof($citizen, $service, ['uploaded' => true, 'mime_type' => 'image/jpeg', 'review_status' => 'approved']);
        $this->source(['attachment:photo' => [$file]]);
        $response = $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview');
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->source(['attachment:photo' => [array_merge($file, ['review_status' => 'pending'])]]);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true, 'requirements_result' => ['eligible' => true], 'verified' => true])->assertSessionHasErrors('service');
        $this->assertSame(0, $citizen->serviceRequests()->count());
    }

    public function test_unconfigured_or_untrusted_document_rules_do_not_implicitly_pass(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $service->update(['required_documents' => ['مستند غير مصنف']]);
        $this->assess($citizen, $service);
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
        $this->files($citizen, $service);
        $this->source(['attachment:photo' => [$this->proof($citizen, $service, ['uploaded' => true, 'mime_type' => 'image/jpeg', 'review_status' => 'approved', 'trusted' => false])]]);
        $this->assertSame(RequirementState::Unverifiable, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
    }

    public function test_admin_rules_validation_and_existing_role_boundaries_are_preserved(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $admin = $this->citizen();
        $admin->forceFill(['role' => 'admin'])->save();
        $this->actingAs($admin)->patch('/admin/services/'.$service->id, ['name' => $service->name, 'verification_rules' => ['documents' => [['name' => 'الصورة الشخصية', 'code' => 'photo', 'kind' => 'photo', 'allowed_mime_types' => ['application/pdf'], 'requires_review' => true]]]])->assertSessionHasErrors('verification_rules');
        $request = $citizen->serviceRequests()->create(['reference' => 'READONLY-RESULT', 'service_code' => $service->code, 'status' => 'under_review']);
        $this->post('/citizen/applications/'.$request->id.'/requirements/check')->assertForbidden();
        $employee = $this->citizen();
        $employee->forceFill(['role' => 'employee'])->save();
        $this->actingAs($employee)->post('/employee/applications/'.$request->id.'/requirements/check', ['eligible' => true])->assertOk();
        $this->assertFalse($request->fresh()->requirements_result['eligible']);
        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertFalse($employee->can('transition', $request));
    }

    public function test_audit_omits_private_reasons_identifiers_and_source_error_messages(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $service->assessments()->update(['is_blocked' => true, 'block_reason' => 'CONFIDENTIAL-AUDIT-DETAIL']);
        Log::spy();
        $engine = app(ServiceEligibility::class);
        $engine->audit($engine->evaluate($citizen, $service), $citizen, $service);
        Log::shouldHaveReceived('info')->with('service_requirements_checked', \Mockery::on(function ($context) use ($citizen) {
            $json = json_encode($context);

            return ! str_contains($json, 'CONFIDENTIAL-AUDIT-DETAIL') && ! str_contains($json, $citizen->email) && ! str_contains($json, $citizen->username);
        }))->once();
    }

    public function test_malformed_supplied_expiry_cannot_pass_even_when_expiry_is_optional(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $this->files($citizen, $service);
        $this->source(['attachment:photo' => [$this->proof($citizen, $service, ['uploaded' => true, 'mime_type' => 'image/jpeg', 'review_status' => 'approved', 'expires_at' => 'not-a-date'])]]);
        $this->assertSame(RequirementState::Unverifiable, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
        $driving = $this->service($citizen, 'license-first-issue');
        $driving->update(['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => false]]]);
        $this->assess($citizen, $driving);
        $this->source(['medical:driving' => [$this->proof($citizen, $driving, ['result' => 'passed', 'examined_at' => today()->subDay()->toDateString(), 'expires_at' => '2026-02-30'])]]);
        $this->assertSame(RequirementState::Unverifiable, app(ServiceEligibility::class)->evaluate($citizen, $driving)->checks['medical']->state);
    }

    public function test_editing_assessment_metadata_does_not_implicitly_change_review_time(): void
    {
        $citizen = $this->citizen();
        $service = $this->service($citizen);
        $assessment = $service->assessments()->where('user_id', $citizen->id)->firstOrFail();
        $assessment->update(['reviewed_at' => now()->subDays(2)->startOfSecond()]);
        $original = $assessment->fresh()->reviewed_at->toDateTimeString();
        $assessment->update(['public_block_reason' => 'TEST public metadata']);
        $this->assertSame($original, $assessment->fresh()->reviewed_at->toDateTimeString());
    }
}
