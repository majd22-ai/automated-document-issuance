<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\RequestDeficiency;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Operations\RequestCharges;
use App\Services\Review\ApprovalReadiness;
use App\Services\Review\ReviewActions;
use App\Services\Review\ReviewSnapshot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ApplicationReviewTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing DB required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $db->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($db) {
            $db->rollBack();
            $db->disconnect();
        });
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'REVIEW-'.$user->id])->save();

        return $user;
    }

    private function fixtures(array $extra = []): array
    {
        $citizen = $this->user();
        $employee = $this->user('employee');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(array_merge(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST review policy'], $extra));
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST manual assessment', 'reviewed_by' => $this->user('admin')->id, 'reviewed_at' => now()]);
        $request = $citizen->serviceRequests()->create(['reference' => 'REVIEW-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        $request->forceFill(['request_type' => $service->operation, 'request_data' => $service->application_fields ? ['address' => 'Original address'] : [], 'submitted_at' => now()])->save();
        $this->grant($employee, $service, true, true, true);

        return [$citizen, $employee, $service, $request->fresh()];
    }

    private function grant(User $employee, GovernmentService $service, bool $review = true, bool $approve = false, bool $assign = false): void
    {
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => $review, 'can_approve' => $approve, 'can_assign' => $assign, 'granted_by' => $this->user('admin')->id])->save();
    }

    private function data(CitizenServiceRequest $request, string $action, array $extra = []): array
    {
        $request = $request->fresh();

        return array_merge(['action' => $action, 'decision_token' => (string) Str::uuid(), 'expected_version' => $request->review_version, 'expected_rules_version' => $request->service->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request)], $extra);
    }

    private function decide(User $actor, CitizenServiceRequest $request, string $action, array $extra = [])
    {
        return $this->actingAs($actor)->post('/employee/applications/'.$request->id.'/decisions', $this->data($request, $action, $extra));
    }

    public function test_explicit_service_permission_is_required_and_citizens_cannot_decide(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $outside = $this->user('employee');
        $this->actingAs($outside)->get('/employee/applications/'.$request->id.'/review')->assertForbidden();
        $this->get('/employee/applications')->assertOk()->assertDontSee($request->reference);
        $this->decide($outside, $request, 'start')->assertForbidden();
        $this->decide($citizen, $request, 'approve')->assertForbidden();
        $this->actingAs($reviewer)->get('/employee/applications/'.$request->id.'/review')->assertOk();
        $this->get('/employee/applications?search='.$request->reference.'&status=submitted')->assertOk()->assertSee($request->reference);
    }

    public function test_claim_accept_and_approve_write_history_without_issuing_document(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($reviewer->id, $request->fresh()->assigned_to);
        $this->decide($reviewer, $request, 'accept', ['internal_notes' => 'INTERNAL REVIEW NOTE'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $request->fresh()->status);
        $this->decide($reviewer, $request, 'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);
        $this->assertSame(3, $request->reviewDecisions()->count());
        $this->assertSame(0, $citizen->documents()->count());
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id)->assertOk()->assertSee('معتمد')->assertDontSee('INTERNAL REVIEW NOTE');
        $this->actingAs($this->user())->get('/citizen/applications/'.$request->id)->assertNotFound();
    }

    public function test_approval_is_separate_permission_and_optional_separation_is_enforced(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['require_separate_approver' => true]);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept');
        $this->decide($reviewer, $request, 'approve')->assertForbidden();
        $approver = $this->user('employee');
        $this->grant($approver, $service, false, true);
        $this->decide($approver, $request, 'approve')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_assignment_requires_permission_and_target_reviewer_for_the_same_service(): void
    {
        [$citizen,$manager,$service,$request] = $this->fixtures();
        $other = $this->user('employee');
        $this->grant($other, $service);
        $payload = ['assigned_to' => $other->id, 'expected_version' => $request->review_version, 'decision_token' => (string) Str::uuid(), 'reason' => 'TEST assignment'];
        $this->actingAs($other)->post('/employee/applications/'.$request->id.'/assignment', $payload)->assertForbidden();
        $this->actingAs($manager)->post('/employee/applications/'.$request->id.'/assignment', $payload)->assertRedirect();
        $this->decide($manager, $request, 'start')->assertForbidden();
        $this->decide($other, $request, 'start')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame($other->id, $request->fresh()->assigned_to);
    }

    public function test_missing_requirement_blocks_acceptance_and_keeps_state_and_decisions_atomic(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['required_documents' => ['Required proof']]);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept')->assertSessionHasErrors('requirements');
        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertSame(1, $request->reviewDecisions()->count());
    }

    public function test_unpaid_fees_cannot_be_deferred_past_final_review_approval(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['fee' => '50.25', 'currency' => 'TEST']);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept')->assertSessionHasNoErrors();
        $this->decide($reviewer, $request, 'approve')->assertSessionHasErrors('requirements');
        $this->assertSame('accepted', $request->fresh()->status);
    }

    public function test_return_requires_reason_and_structured_deficiencies_and_preserves_internal_privacy(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'return')->assertSessionHasErrors();
        $this->decide($reviewer, $request, 'return', ['public_reason' => 'أكمل المستند المطلوب', 'internal_notes' => 'PRIVATE RETURN NOTE', 'deficiencies' => [['target_type' => 'other', 'target_key' => 'supporting_evidence', 'description' => 'راجع الجهة المختصة للمستند المطلوب']]])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('requires_correction', $request->fresh()->status);
        $this->assertSame(1, RequestDeficiency::count());
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id)->assertSee('أكمل المستند المطلوب')->assertSee('راجع الجهة المختصة')->assertDontSee('PRIVATE RETURN NOTE');
        $this->assertArrayNotHasKey('internal_notes', $request->reviewDecisions()->latest('id')->first()->toArray());
    }

    public function test_citizen_correction_resubmission_preserves_original_data_and_needs_reviewer_resolution(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['application_fields' => [['key' => 'address', 'label' => 'العنوان', 'type' => 'text', 'required' => true]]]);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'return', ['public_reason' => 'صحح العنوان', 'deficiencies' => [['target_type' => 'field', 'target_key' => 'address', 'description' => 'أدخل العنوان الصحيح']]])->assertSessionHasNoErrors();
        $deficiency = $request->deficiencies()->firstOrFail();
        $data = $this->data($request, 'resubmit', ['data' => ['address' => 'Corrected address'], 'responses' => [$deficiency->id => 'تم تصحيح العنوان']]);
        $this->actingAs($citizen)->post('/citizen/applications/'.$request->id.'/resubmit', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertSame('Corrected address', $request->fresh()->request_data['address']);
        $this->assertSame('Original address', $request->fresh()->original_request_data['address']);
        $this->assertSame('awaiting_review', $deficiency->fresh()->status);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept')->assertSessionHasErrors('deficiencies');
        $this->actingAs($reviewer)->post('/employee/applications/'.$request->id.'/deficiencies/'.$deficiency->id.'/resolve', ['expected_version' => $request->fresh()->review_version, 'expected_rules_version' => $service->fresh()->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request), 'resolution_note' => 'تم التحقق من العنوان الصحيح'])->assertRedirect()->assertSessionHasNoErrors();
        $this->decide($reviewer, $request, 'accept')->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame('accepted', $request->fresh()->status);
        $this->assertSame(6, $request->reviewDecisions()->count());
    }

    public function test_rejection_is_terminal_and_requires_reason_with_no_reopening_endpoint(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'reject')->assertSessionHasErrors('public_reason');
        $this->decide($reviewer, $request, 'reject', ['public_reason' => 'سبب رفض موثق'])->assertRedirect()->assertSessionHasNoErrors();
        $this->decide($reviewer, $request, 'approve')->assertSessionHasErrors();
        $this->assertSame('rejected', $request->fresh()->status);
        $this->actingAs($citizen)->get('/citizen/applications/'.$request->id)->assertSee('سبب رفض موثق');
    }

    public function test_identical_decision_replay_is_idempotent_but_conflicting_and_stale_forms_are_rejected(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $data = $this->data($request, 'start');
        $this->actingAs($reviewer)->post('/employee/applications/'.$request->id.'/decisions', $data)->assertRedirect();
        $this->post('/employee/applications/'.$request->id.'/decisions', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(1, $request->reviewDecisions()->count());
        $this->post('/employee/applications/'.$request->id.'/decisions', array_merge($data, ['action' => 'reject', 'public_reason' => 'changed']))->assertSessionHasErrors('decision_token');
        $this->post('/employee/applications/'.$request->id.'/decisions', array_merge($data, ['decision_token' => (string) Str::uuid(), 'action' => 'accept']))->assertSessionHasErrors('review');
    }

    public function test_stale_evidence_and_browser_eligibility_flags_cannot_approve(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $data = $this->data($request, 'accept');
        $citizen->forceFill(['phone' => 'TEST changed contact'])->save();
        $this->actingAs($reviewer)->post('/employee/applications/'.$request->id.'/decisions', $data)->assertSessionHasErrors('review');
        $this->decide($reviewer, $request, 'accept', ['eligible' => true, 'actor_id' => $reviewer->id])->assertSessionHasErrors();
        $this->assertSame('under_review', $request->fresh()->status);
    }

    public function test_approval_without_acceptance_and_generic_direct_status_update_are_denied(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'approve')->assertSessionHasErrors('status');
        $this->actingAs($citizen)->patch('/citizen/applications/'.$request->id, ['status' => 'approved'])->assertStatus(405);
        $this->assertSame('submitted', $request->fresh()->status);
    }

    public function test_review_grants_are_admin_only_and_do_not_change_public_registration(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $other = $this->user('employee');
        $data = ['government_service_id' => $service->id, 'can_review' => true, 'can_approve' => false, 'can_assign' => false];
        $this->actingAs($citizen)->post('/admin/users/'.$other->id.'/review-permissions', $data)->assertForbidden();
        $this->actingAs($this->user('admin'))->post('/admin/users/'.$other->id.'/review-permissions', $data)->assertRedirect()->assertSessionHasNoErrors();
        $this->assertTrue($other->fresh()->canReviewService($service->id, 'can_review'));
        $this->assertFalse($other->fresh()->canReviewService($service->id, 'can_approve'));
    }

    public function test_review_and_correction_render_within_the_same_locked_snapshot(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['application_fields' => [['key' => 'address', 'label' => 'العنوان', 'type' => 'text', 'required' => true]]]);
        $baseline = DB::transactionLevel();
        $snapshots = \Mockery::mock(ReviewSnapshot::class)->makePartial();
        $snapshots->shouldReceive('fingerprint')->twice()->andReturnUsing(function ($request) use ($baseline) {
            $this->assertGreaterThan($baseline, DB::transactionLevel());

            return (new ReviewSnapshot)->fingerprint($request);
        });
        $this->app->instance(ReviewSnapshot::class, $snapshots);
        $this->actingAs($reviewer)->get('/employee/applications/'.$request->id.'/review')->assertOk();
        $this->app->instance(ReviewSnapshot::class, new ReviewSnapshot);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'return', ['public_reason' => 'صحح العنوان', 'deficiencies' => [['target_type' => 'field', 'target_key' => 'address', 'description' => 'أدخل العنوان الصحيح']]]);
        $this->app->instance(ReviewSnapshot::class, $snapshots);
        $row = $request->deficiencies()->firstOrFail();
        $url = '/citizen/applications/'.$request->id.'/correction';
        $this->actingAs($citizen)->from($url)->post('/citizen/applications/'.$request->id.'/resubmit', ['data' => ['address' => ['bad']], 'responses' => [$row->id => ['bad']]])->assertRedirect($url);
        $this->get($url)->assertOk();
    }

    public function test_document_changes_invalidate_a_review_form_and_readiness_rechecks_after_approval(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $data = $this->data($request, 'accept');
        $citizen->documents()->create(['document_type' => 'TEST document', 'document_number' => 'TEST-ONLY', 'status' => 'active']);
        $this->actingAs($reviewer)->post('/employee/applications/'.$request->id.'/decisions', $data)->assertSessionHasErrors('review');
        $this->decide($reviewer, $request, 'accept')->assertSessionHasNoErrors();
        $this->decide($reviewer, $request, 'approve')->assertSessionHasNoErrors();
        $this->assertTrue(app(ApprovalReadiness::class)->check($request)['eligible']);
        $citizen->forceFill(['is_active' => false])->save();
        $this->expectException(ValidationException::class);
        app(ApprovalReadiness::class)->check($request);
    }

    public function test_authorized_recorded_payment_allows_final_approval_and_history_is_immutable(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['fee' => '50.25', 'currency' => 'TEST']);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept')->assertSessionHasNoErrors();
        app(RequestCharges::class)->ensure($request);
        $this->actingAs($reviewer)->post('/employee/applications/'.$request->id.'/payments', [
            'amount' => '50.25', 'currency' => 'TEST', 'receipt_reference' => 'REVIEW-PAY-'.Str::uuid(),
            'paid_at' => today()->toDateString(), 'method' => 'cash',
        ])->assertRedirect()->assertSessionHasNoErrors();
        $this->decide($reviewer, $request, 'approve')->assertSessionHasNoErrors();
        $this->assertSame('approved', $request->fresh()->status);
        $decision = $request->reviewDecisions()->where('action', 'approve')->firstOrFail();
        try {
            $decision->forceFill(['public_reason' => 'overwritten'])->save();
            $this->fail('Decision must be immutable');
        } catch (\LogicException) {
            $this->assertNull($decision->fresh()->public_reason);
        }
        try {
            $decision->delete();
            $this->fail('Decision must be retained');
        } catch (\LogicException) {
            $this->assertSame(3, $request->reviewDecisions()->count());
        }
    }

    public function test_queue_paginates_and_never_leaks_requests_from_another_service(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        for ($i = 0; $i < 15; $i++) {
            $citizen->serviceRequests()->create(['reference' => 'TEST-PAGE-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        }
        $outside = GovernmentService::where('code', '!=', $service->code)->firstOrFail();
        $other = $citizen->serviceRequests()->create(['reference' => 'TEST-OUTSIDE-SCOPE', 'service_code' => $outside->code, 'status' => 'submitted']);
        $this->actingAs($reviewer)->get('/employee/applications')->assertOk()->assertDontSee($other->reference)
            ->assertViewHas('applications', fn ($rows) => $rows->total() === 16 && $rows->count() === 15);
        $this->get('/employee/applications?mine=1')->assertViewHas('applications', fn ($rows) => $rows->total() === 0);
        $this->get('/employee/applications/'.$other->id.'/review')->assertForbidden();
    }

    public function test_new_separation_policy_cannot_be_bypassed_by_older_request_snapshot(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'accept');
        $service->update(['require_separate_approver' => true]);
        $this->assertFalse($reviewer->can('transition', [$request->fresh(), ServiceRequestStatus::Approved]));
    }

    public function test_failure_writing_decision_rolls_back_request_state(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures();
        $this->decide($reviewer, $request, 'start');
        $actions = \Mockery::mock(ReviewActions::class)->makePartial();
        $actions->shouldReceive('save')->once()->andThrow(new \RuntimeException('TEST audit unavailable'));
        $this->app->instance(ReviewActions::class, $actions);
        try {
            $request->fresh()->transitionTo(ServiceRequestStatus::Accepted, $reviewer, null, $this->data($request, 'accept'));
            $this->fail('Decision failure must abort transaction');
        } catch (\RuntimeException $e) {
            $this->assertSame('TEST audit unavailable', $e->getMessage());
        }
        $this->assertSame('under_review', $request->fresh()->status);
        $this->assertSame(1, $request->reviewDecisions()->count());
    }

    public function test_citizen_cannot_resolve_gaps_or_change_fields_outside_the_returned_set(): void
    {
        [$citizen,$reviewer,$service,$request] = $this->fixtures(['application_fields' => [['key' => 'address', 'label' => 'العنوان', 'type' => 'text', 'required' => true]]]);
        $this->decide($reviewer, $request, 'start');
        $this->decide($reviewer, $request, 'return', ['public_reason' => 'صحح العنوان', 'deficiencies' => [['target_type' => 'field', 'target_key' => 'address', 'description' => 'أدخل عنوانًا واضحًا']]]);
        $row = $request->deficiencies()->firstOrFail();
        $data = $this->data($request, 'resubmit', ['data' => ['username' => 'ATTACK'], 'responses' => [$row->id => 'تم الاستكمال']]);
        $this->actingAs($citizen)->post('/citizen/applications/'.$request->id.'/resubmit', $data)->assertSessionHasErrors('data');
        $this->assertSame('requires_correction', $request->fresh()->status);
        $this->post('/employee/applications/'.$request->id.'/deficiencies/'.$row->id.'/resolve', ['expected_version' => $request->fresh()->review_version, 'expected_rules_version' => $service->fresh()->rules_version, 'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request), 'resolution_note' => 'pretend'])->assertForbidden();
        $this->assertSame('open', $row->fresh()->status);
    }
}
