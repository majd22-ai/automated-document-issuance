<?php

namespace Tests\Feature;

use App\Enums\RequirementState;
use App\Enums\ServiceRequestStatus;
use App\Models\AppointmentSlot;
use App\Models\CitizenAppointment;
use App\Models\GovernmentService;
use App\Models\ManualPayment;
use App\Models\MedicalExamination;
use App\Models\RequestCharge;
use App\Models\User;
use App\Services\Operations\RequestCharges;
use App\Services\ServiceEligibility;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RequestOperationsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $db = DB::connection();
        if ($db->getDatabaseName() !== ':memory:' && ! str_ends_with($db->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated database required');
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
        $user->forceFill(['role' => $role, 'username' => 'OPS-'.$user->id])->save();

        return $user;
    }

    private function service(User $citizen, array $extra = [], string $code = 'identity-first-issue'): GovernmentService
    {
        $service = GovernmentService::where('code', $code)->firstOrFail();
        $service->update(array_merge(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST trusted rule'], $extra));
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST only', 'reviewed_by' => $this->user('admin')->id, 'reviewed_at' => now()]);

        return $service;
    }

    private function application(User $citizen, GovernmentService $service, string $status = 'submitted')
    {
        $request = $citizen->serviceRequests()->create(['reference' => 'OPS-'.Str::uuid(), 'service_code' => $service->code, 'status' => $status]);
        $request->forceFill(['request_type' => $service->operation])->save();

        return $request;
    }

    private function slot(GovernmentService $service, array $extra = []): AppointmentSlot
    {
        $slot = (new AppointmentSlot)->forceFill(array_merge(['government_service_id' => $service->id, 'center' => 'TEST center', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'capacity' => 1, 'is_active' => true, 'created_by' => $this->user('employee')->id], $extra));
        $slot->save();

        return $slot;
    }

    public function test_citizen_books_owned_request_and_duplicate_or_full_slot_is_rejected(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $application = $this->application($citizen, $service);
        $slot = $this->slot($service);
        $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id])->assertRedirect();
        $booking = CitizenAppointment::firstOrFail();
        $this->assertSame($citizen->id, $booking->user_id);
        $this->assertSame($application->id, $booking->citizen_service_request_id);
        $this->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id])->assertSessionHasErrors('appointment');
        $other = $this->user();
        $otherRequest = $this->application($other, $service);
        $this->actingAs($other)->post('/citizen/applications/'.$otherRequest->id.'/appointments', ['slot_id' => $slot->id])->assertSessionHasErrors('appointment');
        $this->assertSame(1, CitizenAppointment::count());
    }

    public function test_past_cancelled_foreign_service_and_foreign_request_are_rejected(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $application = $this->application($citizen, $service);
        foreach ([$this->slot($service, ['starts_at' => now()->subDay(), 'ends_at' => now()->subHour()]), $this->slot($service, ['is_active' => false])] as $slot) {
            $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id])->assertSessionHasErrors('appointment');
        }
        $foreignService = GovernmentService::where('code', 'passport-first-issue')->firstOrFail();
        $this->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $this->slot($foreignService)->id])->assertSessionHasErrors('appointment');
        $foreign = $this->application($this->user(), $service);
        $this->get('/citizen/applications/'.$foreign->id.'/operations')->assertNotFound();
        $this->post('/citizen/applications/'.$foreign->id.'/appointments', ['slot_id' => 1])->assertNotFound();
    }

    public function test_reschedule_and_cancel_release_capacity_and_closed_request_cannot_book(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $application = $this->application($citizen, $service);
        $old = $this->slot($service);
        $next = $this->slot($service);
        $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $old->id]);
        $booking = CitizenAppointment::firstOrFail();
        $this->patch('/citizen/appointments/'.$booking->id, ['action' => 'reschedule', 'slot_id' => $next->id])->assertRedirect();
        $this->assertSame('cancelled', $booking->fresh()->status);
        $current = CitizenAppointment::where('status', 'booked')->firstOrFail();
        $this->patch('/citizen/appointments/'.$current->id, ['action' => 'cancel'])->assertRedirect();
        $this->assertNull($current->fresh()->active_request_key);
        $closed = $this->application($citizen, $service, 'completed');
        $this->post('/citizen/applications/'.$closed->id.'/appointments', ['slot_id' => $old->id])->assertForbidden();
    }

    public function test_only_employee_manages_slots_and_logical_booking_states(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $data = ['government_service_id' => $service->id, 'center' => 'TEST', 'starts_at' => now('Asia/Riyadh')->addDays(3)->format('Y-m-d\TH:i'), 'ends_at' => now('Asia/Riyadh')->addDays(3)->addHour()->format('Y-m-d\TH:i'), 'capacity' => 2];
        $this->actingAs($citizen)->post('/employee/appointment-slots', $data)->assertForbidden();
        $employee = $this->user('employee');
        $this->actingAs($employee)->post('/employee/appointment-slots', $data)->assertRedirect();
        $application = $this->application($citizen, $service);
        $slot = AppointmentSlot::firstOrFail();
        $this->post('/employee/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id])->assertRedirect();
        $booking = CitizenAppointment::firstOrFail();
        $this->patch('/employee/appointments/'.$booking->id, ['action' => 'completed'])->assertSessionHasErrors('appointment');
        $this->patch('/employee/appointments/'.$booking->id, ['action' => 'confirmed'])->assertRedirect();
        $this->assertSame('confirmed', $booking->fresh()->status);
    }

    public function test_charges_use_request_snapshot_and_are_idempotent_after_catalog_changes(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['fee' => '125.35', 'currency' => 'TEST']);
        $application = $this->application($citizen, $service);
        $charge = app(RequestCharges::class)->ensure($application);
        $service->update(['fee' => '999.99']);
        $this->assertSame($charge->id, app(RequestCharges::class)->ensure($application)->id);
        $this->assertSame('125.35', $charge->fresh()->amount);
        $this->assertSame(1, RequestCharge::count());
    }

    public function test_manual_payment_is_employee_only_exact_and_reference_unique(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['fee' => '100.10', 'currency' => 'TEST']);
        $application = $this->application($citizen, $service);
        $charge = app(RequestCharges::class)->ensure($application);
        $data = ['amount' => '100.10', 'currency' => 'TEST', 'receipt_reference' => 'TEST-RECEIPT-1', 'paid_at' => today()->toDateString(), 'method' => 'cash'];
        $this->actingAs($citizen)->post('/employee/applications/'.$application->id.'/payments', $data)->assertForbidden();
        $this->actingAs($this->user('employee'))->post('/employee/applications/'.$application->id.'/payments', array_merge($data, ['amount' => '100.09']))->assertSessionHasErrors('amount');
        $this->post('/employee/applications/'.$application->id.'/payments', $data)->assertRedirect();
        $this->post('/employee/applications/'.$application->id.'/payments', $data)->assertRedirect();
        $this->assertSame('paid', $charge->fresh()->status);
        $this->assertSame(1, ManualPayment::count());
        $this->assertSame('submitted', $application->fresh()->status);
        $second = $this->application($citizen, $service);
        $this->post('/employee/applications/'.$second->id.'/payments', $data)->assertSessionHasErrors('receipt_reference');
    }

    public function test_explicit_zero_and_unknown_fee_are_not_conflated(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $unknown = $this->application($citizen, $service);
        $this->assertNull(app(RequestCharges::class)->ensure($unknown));
        $service->update(['fee' => '0.00', 'currency' => 'TEST']);
        $zero = $this->application($citizen, $service);
        $this->assertSame('not_required', app(RequestCharges::class)->ensure($zero)->status);
    }

    public function test_medical_result_is_request_scoped_employee_only_and_private_notes_are_hidden(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']], 'license-first-issue');
        $application = $this->application($citizen, $service);
        $data = ['expected_rules_version' => $service->rules_version, 'expected_id' => 0, 'result' => 'passed', 'examined_at' => today()->toDateString(), 'expires_at' => today()->addMonth()->toDateString(), 'source_reference' => 'TEST manual medical record', 'private_notes' => 'PRIVATE-MEDICAL-DETAIL'];
        $this->actingAs($citizen)->post('/employee/applications/'.$application->id.'/examinations', $data)->assertForbidden();
        $this->actingAs($this->user('employee'))->post('/employee/applications/'.$application->id.'/examinations', $data)->assertRedirect();
        $this->assertSame(1, MedicalExamination::count());
        $this->assertSame(RequirementState::Met, app(ServiceEligibility::class)->evaluate($citizen, $service, $application)->checks['medical']->state);
        $this->actingAs($citizen)->get('/citizen/applications/'.$application->id.'/operations')->assertOk()->assertDontSee('PRIVATE-MEDICAL-DETAIL')->assertSee('ناجح');
        $this->actingAs($this->user())->get('/citizen/applications/'.$application->id.'/operations')->assertNotFound();
    }

    public function test_missing_failed_pending_expired_and_stale_exams_do_not_pass(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']], 'license-first-issue');
        $application = $this->application($citizen, $service);
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service, $application)->eligible());
        $this->actingAs($this->user('employee'));
        foreach (['pending', 'failed', 'retest', 'passed'] as $result) {
            $last = MedicalExamination::latest('id')->first();
            $this->post('/employee/applications/'.$application->id.'/examinations', ['expected_rules_version' => $service->rules_version, 'expected_id' => $last?->id ?? 0, 'result' => $result, 'examined_at' => today()->subDays(3)->toDateString(), 'expires_at' => today()->subDay()->toDateString(), 'source_reference' => 'TEST trusted manual'])->assertRedirect();
            $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service, $application)->eligible());
        }
        $this->post('/employee/applications/'.$application->id.'/examinations', ['expected_rules_version' => $service->rules_version, 'expected_id' => 0, 'result' => 'passed', 'examined_at' => today()->toDateString(), 'expires_at' => today()->addDay()->toDateString(), 'source_reference' => 'TEST stale'])->assertSessionHasErrors('examination');
        $this->assertSame(4, MedicalExamination::count());
    }

    public function test_medical_deferral_is_explicit_and_no_exam_is_imposed_on_other_services(): void
    {
        $citizen = $this->user();
        $plain = $this->service($citizen);
        $this->assertArrayNotHasKey('medical', app(ServiceEligibility::class)->evaluate($citizen, $plain)->checks);
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']], 'license-first-issue');
        $this->assertTrue(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
        $service->update(['workflow_rules' => null]);
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
    }

    public function test_transition_hook_blocks_required_appointment_and_unpaid_fees_without_granting_review_permissions(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['fee' => '20.00', 'currency' => 'TEST', 'workflow_rules' => ['appointment' => ['required' => true, 'stage' => 'accepted', 'statuses' => ['confirmed']], 'payment' => ['required' => true, 'stage' => 'accepted']]]);
        $application = $this->application($citizen, $service, 'under_review');
        $this->assertFalse($citizen->can('transition', $application));
        Gate::before(fn ($actor, $ability) => $ability === 'transition' ? true : null); // Test-only future review authority.
        try {
            $application->transitionTo(ServiceRequestStatus::Accepted, $citizen);
            $this->fail('Missing operations must block transition');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requirements', $e->errors());
        }
        $slot = $this->slot($service);
        $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id]);
        $booking = CitizenAppointment::firstOrFail();
        $employee = $this->user('employee');
        $this->actingAs($employee)->patch('/employee/appointments/'.$booking->id, ['action' => 'confirmed'])->assertRedirect();
        try {
            $application->transitionTo(ServiceRequestStatus::Accepted, $citizen);
            $this->fail('Unpaid fees must block transition');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('السداد', implode(' ', $e->errors()['requirements']));
        }
        $this->post('/employee/applications/'.$application->id.'/payments', ['amount' => '20.00', 'currency' => 'TEST', 'receipt_reference' => 'TEST-PHASE', 'paid_at' => today()->toDateString(), 'method' => 'cash'])->assertRedirect();
        $this->assertSame('accepted', $application->transitionTo(ServiceRequestStatus::Accepted, $citizen)->status);
    }

    public function test_payment_remains_required_before_issuance_and_processing_requires_a_document(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['fee' => '10.25', 'currency' => 'TEST']);
        $application = $this->application($citizen, $service, 'approved');
        Gate::before(fn ($actor, $ability) => $ability === 'transition' ? true : null);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service, $application, ServiceRequestStatus::Processing)->checks['payment']->state);
        $this->actingAs($this->user('employee'))->post('/employee/applications/'.$application->id.'/payments', ['amount' => '10.25', 'currency' => 'TEST', 'receipt_reference' => 'TEST-APPROVED', 'paid_at' => today()->toDateString(), 'method' => 'cash'])->assertRedirect()->assertSessionHasNoErrors();
        $this->assertSame(RequirementState::Met, app(ServiceEligibility::class)->evaluate($citizen, $service, $application->fresh(), ServiceRequestStatus::Processing)->checks['payment']->state);
        try {
            $application->transitionTo(ServiceRequestStatus::Processing, $citizen);
            $this->fail('Actual issuance record required');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('document', $e->errors());
        }
    }

    public function test_deferred_medical_rule_allows_real_submission_but_cannot_pass_acceptance_without_exam(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']], 'license-first-issue');
        $preview = $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview');
        $preview->assertRedirect()->assertSessionHasNoErrors();
        $token = basename(parse_url($preview->headers->get('Location'), PHP_URL_PATH));
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertRedirect()->assertSessionHasNoErrors();
        $application = $citizen->serviceRequests()->firstOrFail();
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service, $application)->eligible());
    }

    public function test_non_driving_medical_configuration_cannot_be_deferred_to_bypass_invalid_rules(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']]);
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
    }

    public function test_reschedule_failure_preserves_existing_booking_and_available_list_hides_full_slots(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $application = $this->application($citizen, $service);
        $slot = $this->slot($service, ['center' => 'FULL-SLOT']);
        $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/appointments', ['slot_id' => $slot->id]);
        $booking = CitizenAppointment::firstOrFail();
        $unavailable = $this->slot($service, ['is_active' => false]);
        $this->patch('/citizen/appointments/'.$booking->id, ['action' => 'reschedule', 'slot_id' => $unavailable->id])->assertSessionHasErrors('appointment');
        $this->assertSame('booked', $booking->fresh()->status);
        $this->assertSame(1, CitizenAppointment::count());
        $this->get('/citizen/applications/'.$application->id.'/operations')->assertOk();
        $this->assertFalse($slot->available());
    }

    public function test_final_approval_requires_even_processing_stage_medical_result(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]], 'workflow_rules' => ['medical_stage' => 'processing']], 'license-first-issue');
        $application = $this->application($citizen, $service, 'under_review');
        Gate::before(fn ($actor, $ability) => $ability === 'transition' ? true : null);
        $application = $application->transitionTo(ServiceRequestStatus::Accepted, $citizen);
        try {
            $application->transitionTo(ServiceRequestStatus::Approved, $citizen);
            $this->fail('Missing exam must block its configured stage');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('requirements', $e->errors());
        }
        $this->actingAs($this->user('employee'))->post('/employee/applications/'.$application->id.'/examinations', ['expected_id' => 0, 'expected_rules_version' => $service->rules_version, 'result' => 'passed', 'examined_at' => today()->toDateString(), 'expires_at' => today()->addMonth()->toDateString(), 'source_reference' => 'TEST manual result'])->assertRedirect()->assertSessionHasNoErrors();
        $application = $application->transitionTo(ServiceRequestStatus::Approved, $citizen);
        $this->assertSame('approved', $application->status);
        $this->assertSame(RequirementState::Met, app(ServiceEligibility::class)->evaluate($citizen, $service, $application, ServiceRequestStatus::Processing)->checks['medical']->state);
    }

    public function test_zero_fee_fulfills_explicit_payment_policy_but_unknown_fee_does_not(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['workflow_rules' => ['payment' => ['required' => true, 'stage' => 'accepted']]]);
        $unknown = $this->application($citizen, $service);
        $this->assertSame(RequirementState::Unverifiable, app(ServiceEligibility::class)->evaluate($citizen, $service, $unknown)->checks['payment']->state);
        $service->update(['fee' => '0.00', 'currency' => 'TEST']);
        $zero = $this->application($citizen, $service);
        app(RequestCharges::class)->ensure($zero);
        $this->assertSame(RequirementState::Met, app(ServiceEligibility::class)->evaluate($citizen, $service, $zero)->checks['payment']->state);
    }

    public function test_medical_form_cannot_attest_to_new_service_rules_it_has_not_reviewed(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, ['verification_rules' => ['medical' => ['required' => true]], 'workflow_rules' => ['medical_stage' => 'accepted']], 'license-first-issue');
        $application = $this->application($citizen, $service);
        $version = $service->rules_version;
        $service->update(['verification_rules' => ['medical' => ['required' => true, 'requires_expiry' => true]]]);
        $this->actingAs($this->user('employee'))->post('/employee/applications/'.$application->id.'/examinations', ['expected_id' => 0, 'expected_rules_version' => $version, 'result' => 'passed', 'examined_at' => today()->toDateString(), 'expires_at' => today()->addMonth()->toDateString(), 'source_reference' => 'TEST old form'])->assertSessionHasErrors('examination');
        $this->assertSame(0, MedicalExamination::count());
    }
}
