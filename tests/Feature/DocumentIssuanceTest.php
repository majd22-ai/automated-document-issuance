<?php

namespace Tests\Feature;

use App\Contracts\ServiceEvidenceSource;
use App\Enums\ServiceRequestStatus;
use App\Models\CitizenDocument;
use App\Models\GovernmentService;
use App\Models\RequestDeficiency;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Issuance\DocumentAudit;
use App\Services\Issuance\DocumentNumbers;
use App\Services\Issuance\ElectronicCopies;
use App\Services\Issuance\IssueDocuments;
use App\Services\Requirements\EvidenceLookup;
use App\Services\Review\ReviewSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class DocumentIssuanceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
        });
        Storage::fake('documents');
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'ISSUE-'.$user->id])->save();

        return $user;
    }

    private function rules(array $extra = []): array
    {
        $rules = array_replace_recursive(['enabled' => true, 'source_reference' => 'TEST trusted issuance settings', 'expiry' => ['required' => true, 'validity_days' => 365], 'number_prefix' => 'TEST', 'include_year' => true, 'electronic_copy' => 'internal_html', 'citizen_download' => true, 'delivery' => ['methods' => ['pickup', 'address'], 'centers' => ['TEST service center']], 'completion_stage' => 'delivery'], $extra);
        if (isset($extra['delivery']['methods'])) {
            $rules['delivery']['methods'] = $extra['delivery']['methods'];
        }

        return $rules;
    }

    private function fixtures(array $serviceExtra = [], bool $approve = true, string $code = 'identity-first-issue'): array
    {
        $citizen = $this->user();
        $employee = $this->user('employee');
        $admin = $this->user('admin');
        $service = GovernmentService::where('code', $code)->firstOrFail();
        $service->update(array_merge(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST service policy', 'issuance_rules' => $this->rules()], $serviceExtra));
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY', 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'can_approve' => true, 'can_issue' => true, 'can_deliver' => true, 'granted_by' => $admin->id])->save();
        $request = $citizen->serviceRequests()->create(['reference' => 'ISSUE-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        if ($approve) {
            $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
            $request = $request->transitionTo(ServiceRequestStatus::Accepted, $employee);
            $request = $request->transitionTo(ServiceRequestStatus::Approved, $employee);
        }

        return [$citizen, $employee, $service, $request->fresh()];
    }

    private function issue(User $actor, $request, array $extra = [])
    {
        $request = $request->fresh();

        return $this->actingAs($actor)->post('/employee/issuance/'.$request->id, array_merge(['operation_token' => (string) Str::uuid(), 'expected_version' => $request->review_version, 'expected_rules_version' => $request->service->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request)], $extra));
    }

    private function command($document, array $extra = []): array
    {
        return array_merge(['operation_token' => (string) Str::uuid(), 'expected_version' => $document->fresh()->workflow_version], $extra);
    }

    private function approve($request, User $employee)
    {
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::Accepted, $employee);

        return $request->transitionTo(ServiceRequestStatus::Approved, $employee);
    }

    private function renewal(): array
    {
        [$citizen,$employee,$service,$request] = $this->fixtures(['renewal_statuses' => ['active']], false, 'identity-renewal');
        $previous = $citizen->documents()->create(['document_type' => 'TEST identity', 'document_type_code' => 'identity', 'document_number' => 'OLD-'.Str::uuid(), 'status' => 'active', 'issued_at' => today()->subYears(2), 'expires_at' => today()->subDay()]);
        $request = $this->approve($request, $employee);

        return [$citizen, $employee, $service, $request, $previous];
    }

    public function test_issue_creates_unique_linked_record_private_copy_and_waits_for_delivery(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request)->assertRedirect()->assertSessionHasNoErrors();
        $document = $request->fresh()->issuedDocument;
        $this->assertNotNull($document);
        $this->assertSame($citizen->id, $document->user_id);
        $this->assertSame($service->id, $document->government_service_id);
        $this->assertStringStartsWith('INT-TEST-', $document->document_number);
        $this->assertSame(today()->toDateString(), $document->issued_at->toDateString());
        $this->assertSame(today()->addDays(365)->toDateString(), $document->expires_at->toDateString());
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame('awaiting_choice', $document->delivery->status);
        $this->assertSame(1, $document->copies()->count());
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertOk()->assertSee('نموذج داخلي')->assertSee($document->document_number);
        $this->assertSame(1, $document->events()->where('action', 'issued')->count());
        $this->issue($employee, $request)->assertSessionHasErrors();
        $this->assertSame(1, CitizenDocument::where('citizen_service_request_id', $request->id)->count());
    }

    public function test_unapproved_unconfigured_and_unauthorized_requests_cannot_issue(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures([], false);
        $this->issue($employee, $request)->assertSessionHasErrors('review');
        $this->issue($citizen, $request)->assertForbidden();
        $this->issue($this->user('employee'), $request)->assertForbidden();
        $this->assertSame(0, CitizenDocument::count());
    }

    public function test_ownership_scope_and_file_integrity_are_checked_on_every_download(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($this->user())->get('/citizen/documents/'.$document->id)->assertNotFound();
        $this->get('/citizen/documents/'.$document->id.'/download')->assertNotFound();
        $this->actingAs($this->user('employee'))->get('/employee/documents/'.$document->id.'/download')->assertForbidden();
        Storage::disk('documents')->put($document->copies()->latest('id')->first()->storage_path, 'CORRUPTED');
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertStatus(409)->assertDontSee('CORRUPTED');
    }

    public function test_missing_policy_or_required_validity_never_guesses_expiry(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $service->update(['issuance_rules' => null]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $this->issue($employee, $request)->assertSessionHasErrors('issuance');
        $service->update(['issuance_rules' => $this->rules(['expiry' => ['validity_days' => null]])]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $this->issue($employee, $request)->assertSessionHasErrors('issuance');
        $this->assertSame(0, CitizenDocument::count());
    }

    public function test_copy_failure_rolls_back_document_request_and_audit_and_allows_retry(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $copies = \Mockery::mock(ElectronicCopies::class)->makePartial();
        $copies->shouldReceive('generate')->once()->andThrow(new \RuntimeException('TEST unavailable storage'));
        $this->app->instance(ElectronicCopies::class, $copies);
        $this->issue($employee, $request)->assertSessionHasErrors('copy');
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(0, CitizenDocument::count());
        $this->assertSame(0, DB::table('document_events')->count());
        $this->app->forgetInstance(ElectronicCopies::class);
        $this->issue($employee, $request)->assertSessionHasNoErrors();
        $this->assertNotNull($request->fresh()->issuedDocument);
    }

    public function test_pickup_delivery_completes_request_only_with_authorized_receipt(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'pickup', 'center' => 'TEST service center']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertNull($document->delivery->fresh()->address);
        $payload = $this->command($document, ['status' => 'delivered', 'proof_reference' => 'TEST handover register 12']);
        $this->post('/employee/documents/'.$document->id.'/delivery-status', $payload)->assertForbidden();
        $this->assertSame('processing', $request->fresh()->status);
        $this->actingAs($employee)->post('/employee/documents/'.$document->id.'/delivery-status', $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertSame($employee->id, $document->delivery->fresh()->delivered_by);
        $this->assertNotNull($document->delivery->fresh()->delivered_at);
    }

    public function test_address_dispatch_freezes_choice_and_failed_delivery_can_be_retried(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'address', 'address' => 'TEST private delivery address']))->assertSessionHasNoErrors();
        foreach (['preparing', 'out_for_delivery', 'failed', 'returned', 'ready', 'preparing', 'out_for_delivery', 'delivered'] as $state) {
            $this->actingAs($employee)->post('/employee/documents/'.$document->id.'/delivery-status', $this->command($document, ['status' => $state, 'reason' => 'TEST recorded reason', 'proof_reference' => 'TEST receipt']))->assertSessionHasNoErrors();
            if ($state === 'preparing') {
                $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'pickup', 'center' => 'TEST service center']))->assertSessionHasErrors('delivery');
            }
        }
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertStringNotContainsString('TEST private delivery address', DB::table('document_deliveries')->where('citizen_document_id', $document->id)->value('address'));
    }

    public function test_renewal_links_previous_document_and_preserves_its_history(): void
    {
        [$citizen,$employee,$service,$request,$previous] = $this->renewal();
        $this->issue($employee, $request, ['previous_document_id' => $previous->id])->assertSessionHasNoErrors();
        $document = $request->fresh()->issuedDocument;
        $this->assertSame($previous->id, $document->previous_document_id);
        $this->assertSame('superseded', $previous->fresh()->status);
        $this->assertSame(2, $citizen->documents()->count());
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame(1, $previous->events()->where('action', 'renewed')->count());
    }

    public function test_failed_renewal_copy_leaves_old_document_unchanged(): void
    {
        [$citizen,$employee,$service,$request,$previous] = $this->renewal();
        $copies = \Mockery::mock(ElectronicCopies::class)->makePartial();
        $copies->shouldReceive('generate')->once()->andThrow(new \RuntimeException('TEST copy failure'));
        $this->app->instance(ElectronicCopies::class, $copies);
        $this->issue($employee, $request, ['previous_document_id' => $previous->id])->assertSessionHasErrors('copy');
        $this->assertSame('active', $previous->fresh()->status);
        $this->assertNull($previous->fresh()->successor);
        $this->assertSame(1, $citizen->documents()->count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_selected_renewal_must_belong_to_citizen_and_pass_its_own_rules(): void
    {
        [$citizen,$employee,$service,$request,$previous] = $this->renewal();
        $foreign = $this->user()->documents()->create(['document_type' => 'TEST', 'document_type_code' => 'identity', 'document_number' => 'FOREIGN-'.Str::uuid(), 'status' => 'active', 'issued_at' => today()->subYear(), 'expires_at' => today()->subDay()]);
        $this->issue($employee, $request, ['previous_document_id' => $foreign->id])->assertSessionHasErrors('previous_document_id');
        $bad = $citizen->documents()->create(['document_type' => 'TEST', 'document_type_code' => 'identity', 'document_number' => 'BAD-'.Str::uuid(), 'status' => 'cancelled', 'issued_at' => today()->subYear(), 'expires_at' => today()->subDay()]);
        $this->issue($employee, $request, ['previous_document_id' => $bad->id])->assertSessionHasErrors('previous_document_id');
        $this->assertSame('active', $previous->fresh()->status);
        $this->assertNull($request->fresh()->issuedDocument);
    }

    public function test_same_prior_document_cannot_have_two_successors(): void
    {
        [$citizen,$employee,$service,$request,$previous] = $this->renewal();
        $this->issue($employee, $request, ['previous_document_id' => $previous->id])->assertSessionHasNoErrors();
        // Deliberately allow the historical status in TEST policy to isolate the successor constraint.
        $service->update(['renewal_statuses' => ['active', 'superseded']]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $second = $citizen->serviceRequests()->create(['reference' => 'SECOND-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        // Existing active request prevents this earlier too; retain explicit application audit fixture.
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['status' => 'completed', 'active_request_key' => null]);
        $second = $this->approve($second, $employee);
        $this->issue($employee, $second, ['previous_document_id' => $previous->id])->assertSessionHasErrors('previous_document_id');
        $this->assertSame(1, CitizenDocument::where('previous_document_id', $previous->id)->count());
    }

    public function test_regeneration_keeps_copy_history_and_repairs_missing_file(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $old = $document->copies()->firstOrFail();
        Storage::disk('documents')->delete($old->storage_path);
        $payload = $this->command($document, ['reason' => 'TEST restore missing copy']);
        $this->actingAs($employee)->post('/employee/documents/'.$document->id.'/regenerate', $payload)->assertSessionHasNoErrors();
        $this->post('/employee/documents/'.$document->id.'/regenerate', $payload)->assertSessionHasNoErrors();
        $this->assertSame(2, $document->copies()->count());
        $this->assertSame(1, $document->events()->where('action', 'copy_regenerated')->count());
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertOk();
        $this->post('/employee/documents/'.$document->id.'/regenerate', $this->command($document, ['reason' => 'TEST cannot self regenerate']))->assertForbidden();
    }

    public function test_copy_or_audit_failure_removes_only_new_file_and_rolls_back_database(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        Storage::disk('documents')->put('preserve.txt', 'TEST unrelated file');
        $audit = \Mockery::mock(DocumentAudit::class)->makePartial();
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('TEST audit failure'));
        $this->app->instance(DocumentAudit::class, $audit);
        try {
            app(IssueDocuments::class)->issue($employee, $request, ['operation_token' => (string) Str::uuid()]);
            $this->fail('Audit failure must roll back issuance');
        } catch (\RuntimeException $e) {
            $this->assertSame('TEST audit failure', $e->getMessage());
        }
        $this->assertSame(0, CitizenDocument::count());
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(['preserve.txt'], Storage::disk('documents')->allFiles());
    }

    public function test_issuance_completion_and_no_expiry_are_explicit_per_service(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures(['issuance_rules' => $this->rules(['expiry' => ['required' => false, 'validity_days' => null], 'completion_stage' => 'issuance', 'electronic_copy' => 'none'])]);
        $this->issue($employee, $request)->assertSessionHasNoErrors();
        $document = $request->fresh()->issuedDocument;
        $this->assertNull($document->expires_at);
        $this->assertSame(0, $document->copies()->count());
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertNotNull($request->fresh()->completed_at);
        $this->assertSame('awaiting_choice', $document->delivery->status);
    }

    public function test_unresolved_gap_and_new_payment_requirement_block_issuance_after_approval(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $decision = $request->reviewDecisions()->latest('id')->first();
        $gap = (new RequestDeficiency)->forceFill(['citizen_service_request_id' => $request->id, 'review_decision_id' => $decision->id, 'target_type' => 'other', 'target_key' => 'TEST', 'description' => 'TEST unresolved gap', 'status' => 'open']);
        $gap->save();
        $this->issue($employee, $request)->assertSessionHasErrors('deficiencies');
        $gap->forceFill(['status' => 'resolved'])->save();
        // TEST historical snapshot: issue must still run payment prerequisite checks.
        $snapshot = $request->service_snapshot;
        $snapshot['workflow_rules'] = ['payment' => ['required' => true, 'stage' => 'processing']];
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['service_snapshot' => json_encode($snapshot)]);
        $this->issue($employee, $request)->assertSessionHasErrors('requirements');
        $this->assertNull($request->fresh()->issuedDocument);
    }

    public function test_new_mandatory_medical_and_attachment_rules_are_rechecked(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures([], true, 'license-first-issue');
        $service->update(['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]]]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $this->issue($employee, $request)->assertSessionHasErrors('requirements');
        $service->update(['verification_rules' => ['documents' => [['code' => 'photo', 'name' => 'TEST photo', 'kind' => 'photo', 'allowed_mime_types' => ['image/png'], 'requires_review' => true]]], 'required_documents' => ['TEST photo']]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $this->issue($employee, $request)->assertSessionHasErrors('requirements');
        $this->assertSame(0, CitizenDocument::count());
    }

    public function test_stale_issuance_fingerprint_and_browser_number_are_rejected(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $fingerprint = app(ReviewSnapshot::class)->fingerprint($request);
        $citizen->forceFill(['phone' => 'TEST changed phone'])->save();
        $this->issue($employee, $request, ['expected_fingerprint' => $fingerprint])->assertSessionHasErrors('review');
        $this->issue($employee, $request, ['document_number' => 'OFFICIAL-SPOOF', 'expires_at' => '2099-01-01'])->assertSessionHasErrors(['document_number', 'expires_at']);
        $this->assertSame(0, CitizenDocument::count());
    }

    public function test_idempotent_issuance_and_conflicting_tokens_are_distinguished(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $token = (string) Str::uuid();
        $this->issue($employee, $request, ['operation_token' => $token])->assertSessionHasNoErrors();
        $this->issue($employee, $request, ['operation_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame(1, CitizenDocument::count());
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['operation_token' => $token, 'method' => 'pickup', 'center' => 'TEST service center']))->assertSessionHasErrors('operation_token');
    }

    public function test_early_completion_and_direct_status_bypass_are_denied(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        try {
            $request->fresh()->transitionTo(ServiceRequestStatus::Completed, $employee);
            $this->fail('Receipt required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('delivery', $e->errors());
        }
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'pickup', 'center' => 'TEST service center', 'status' => 'delivered']))->assertSessionHasErrors('status');
        $this->assertSame('processing', $request->fresh()->status);
    }

    public function test_unavailable_delivery_method_bad_center_and_stale_updates_are_rejected(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures(['issuance_rules' => $this->rules(['delivery' => ['methods' => ['pickup']]])]);
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'address', 'address' => 'TEST address unavailable']))->assertSessionHasErrors('method');
        $this->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'pickup', 'center' => 'untrusted center']))->assertSessionHasErrors('center');
        $stale = $this->command($document, ['method' => 'pickup', 'center' => 'TEST service center']);
        $this->post('/citizen/documents/'.$document->id.'/delivery', $stale)->assertSessionHasNoErrors();
        $stale['operation_token'] = (string) Str::uuid();
        $this->post('/citizen/documents/'.$document->id.'/delivery', $stale)->assertSessionHasErrors('document');
    }

    public function test_electronic_copy_download_can_be_disabled_without_losing_staff_access(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures(['issuance_rules' => $this->rules(['citizen_download' => false])]);
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertForbidden();
        $this->actingAs($employee)->get('/employee/documents/'.$document->id.'/download')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/employee/documents/'.$document->id)->assertOk();
    }

    public function test_regeneration_preserves_holder_name_at_issuance_after_profile_change(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $name = $citizen->name;
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $citizen->forceFill(['name' => 'TEST changed holder name'])->save();
        $this->actingAs($employee)->post('/employee/documents/'.$document->id.'/regenerate', $this->command($document, ['reason' => 'TEST stable holder snapshot']))->assertSessionHasNoErrors();
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertOk()->assertSee($name)->assertDontSee('TEST changed holder name');
    }

    public function test_renewal_authenticity_must_validate_the_exact_selected_prior_document(): void
    {
        [$citizen,$employee,$service,$request,$previous] = $this->renewal();
        $other = $citizen->documents()->create(['document_type' => 'TEST identity', 'document_type_code' => 'identity', 'document_number' => 'VERIFIED-'.Str::uuid(), 'status' => 'active', 'issued_at' => today()->subYear(), 'expires_at' => today()->subDay()]);
        $service->update(['verification_rules' => ['document_authenticity_required' => true]]);
        $service->assessments()->update(['rules_version' => $service->rules_version]);
        $source = \Mockery::mock(ServiceEvidenceSource::class);
        $source->shouldReceive('lookup')->andReturnUsing(function ($owner, $service, $kind, $key, $application) use ($other) {
            return new EvidenceLookup(true, $key === (string) $other->id ? [['user_id' => $owner->id, 'service_id' => $service->id, 'request_id' => $application->id, 'document_id' => $other->id, 'trusted' => true, 'source_reference' => 'TEST trusted source', 'verified_at' => now()->toIso8601String(), 'result' => 'verified']] : []);
        });
        $this->app->instance(ServiceEvidenceSource::class, $source);
        $this->issue($employee, $request, ['previous_document_id' => $previous->id])->assertSessionHasErrors('previous_document_id');
        $this->assertSame('active', $previous->fresh()->status);
        $this->assertNull($request->fresh()->issuedDocument);
    }

    public function test_database_unique_number_rolls_back_conflicting_issuance_without_overwriting(): void
    {
        $numbers = \Mockery::mock(DocumentNumbers::class);
        $numbers->shouldReceive('next')->twice()->andReturn('INT-TEST-COLLISION');
        $this->app->instance(DocumentNumbers::class, $numbers);
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request)->assertSessionHasNoErrors();
        [$other,$otherEmployee,$sameService,$second] = $this->fixtures();
        $this->issue($otherEmployee, $second)->assertSessionHasErrors('document');
        $this->assertSame(1, CitizenDocument::where('document_number', 'INT-TEST-COLLISION')->count());
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertNull($second->fresh()->issuedDocument);
        $this->assertSame($citizen->id, CitizenDocument::first()->user_id);
    }

    public function test_missing_copy_prevents_delivery_and_completion_until_regenerated(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        $this->actingAs($citizen)->post('/citizen/documents/'.$document->id.'/delivery', $this->command($document, ['method' => 'pickup', 'center' => 'TEST service center']));
        Storage::disk('documents')->delete($document->copies()->first()->storage_path);
        $this->actingAs($employee)->post('/employee/documents/'.$document->id.'/delivery-status', $this->command($document, ['status' => 'delivered', 'proof_reference' => 'TEST receipt']))->assertSessionHasErrors('copy');
        $this->assertSame('ready', $document->delivery->fresh()->status);
        $this->assertNull($document->delivery->fresh()->delivered_at);
        $this->assertSame('processing', $request->fresh()->status);
        $this->post('/employee/documents/'.$document->id.'/regenerate', $this->command($document, ['reason' => 'TEST required copy restore']))->assertSessionHasNoErrors();
        $this->post('/employee/documents/'.$document->id.'/delivery-status', $this->command($document, ['status' => 'delivered', 'proof_reference' => 'TEST receipt']))->assertSessionHasNoErrors();
        $this->assertSame('completed', $request->fresh()->status);
    }

    public function test_document_templates_support_all_existing_types_and_explicit_no_expiry(): void
    {
        foreach (['identity-first-issue', 'passport-first-issue', 'license-first-issue'] as $code) {
            [$citizen,$employee,$service,$request] = $this->fixtures(['issuance_rules' => $this->rules(['expiry' => ['required' => false, 'validity_days' => null]])], true, $code);
            $this->issue($employee, $request)->assertSessionHasNoErrors();
            $document = $request->fresh()->issuedDocument;
            $this->assertSame($service->document_type, $document->document_type_code);
            $this->assertNull($document->expires_at);
            $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertOk()->assertSee($document->document_number);
        }
    }

    public function test_issuance_delivery_grants_are_admin_only_and_queue_is_scoped(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $outside = $this->user('employee');
        $this->actingAs($outside)->get('/employee/issuance')->assertOk()->assertDontSee($request->reference);
        $this->get('/employee/issuance/'.$request->id)->assertForbidden();
        $payload = ['government_service_id' => $service->id, 'can_review' => false, 'can_approve' => false, 'can_assign' => false, 'can_issue' => true, 'can_deliver' => false];
        $this->post('/admin/users/'.$outside->id.'/review-permissions', $payload)->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/admin/users/'.$outside->id.'/review-permissions', $payload)->assertSessionHasNoErrors();
        $this->assertTrue($outside->fresh()->canReviewService($service->id, 'can_issue'));
        $this->assertFalse($outside->fresh()->canReviewService($service->id, 'can_deliver'));
        $this->actingAs($outside)->get('/employee/issuance')->assertSee($request->reference);
        $this->get('/employee/issuance/'.$request->id)->assertOk();
        $this->issue($outside, $request)->assertSessionHasNoErrors();
        $document = $request->fresh()->issuedDocument;
        $this->get('/employee/documents/'.$document->id)->assertOk();
        $this->post('/employee/documents/'.$document->id.'/delivery-status', $this->command($document, ['status' => 'delivered', 'proof_reference' => 'TEST']))->assertForbidden();
    }

    public function test_admin_can_configure_issuance_but_missing_validity_is_rejected(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $admin = $this->user('admin');
        $this->actingAs($admin)->get('/admin/services/'.$service->id.'/edit')->assertOk()->assertSee('issuance_rules', false);
        $this->patch('/admin/services/'.$service->id, ['name' => $service->name, 'issuance_rules' => $this->rules(['expiry' => ['validity_days' => null]])])->assertSessionHasErrors('issuance_rules');
        $rules = $this->rules(['expiry' => ['validity_days' => 180], 'number_prefix' => 'REVISED']);
        $version = $service->rules_version;
        $this->patch('/admin/services/'.$service->id, ['name' => $service->name, 'issuance_rules' => $rules])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($version + 1, $service->fresh()->rules_version);
        $this->assertSame(180, $service->fresh()->issuance_rules['expiry']['validity_days']);
        $this->actingAs($citizen)->patch('/admin/services/'.$service->id, ['name' => $service->name, 'issuance_rules' => $rules])->assertForbidden();
    }

    public function test_staff_cannot_forge_issued_identity_or_delete_audit_history(): void
    {
        [$citizen,$employee,$service,$request] = $this->fixtures();
        $this->issue($employee, $request);
        $document = $request->fresh()->issuedDocument;
        try {
            $document->forceFill(['document_number' => 'FORGED'])->save();
            $this->fail('Identity immutable');
        } catch (\LogicException) {
            $this->assertStringStartsWith('INT-', $document->fresh()->document_number);
        }
        try {
            $document->events()->first()->delete();
            $this->fail('Audit retained');
        } catch (\LogicException) {
            $this->assertSame(1, $document->events()->count());
        }
        try {
            $document->delete();
            $this->fail('Official history retained');
        } catch (\LogicException) {
            $this->assertSame(1, CitizenDocument::count());
        }
        $raw = DB::table('citizen_documents')->where('id', $document->id)->value('holder_snapshot');
        $this->assertStringNotContainsString($citizen->name, $raw);
    }
}
