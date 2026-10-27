<?php

namespace Tests\Feature;

use App\Models\AppointmentSlot;
use App\Models\CitizenAttachment;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\IntegrationAttempt;
use App\Models\MedicalExamination;
use App\Models\ReviewDecision;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Operations\AppointmentBookings;
use App\Services\Operations\RequestCharges;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class OperationalNotificationsTest extends TestCase
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
        config(['citizen_notifications.mail_enabled' => false]);
    }

    private function user(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_admin_employee_changes_are_private_deduplicated_and_rollback_with_operation(): void
    {
        $admin = $this->user('admin');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $this->actingAs($admin);
        $employee->update(['name' => 'Changed employee']);
        $this->assertSame(1, $employee->notifications()->count());
        $employee->save();
        $this->assertSame(1, $employee->notifications()->count());
        DB::beginTransaction();
        $employee->forceFill(['password' => 'PRIVATE-PASSWORD'])->save();
        $this->assertSame(2, $employee->notifications()->count());
        DB::rollBack();
        $this->assertSame(1, $employee->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertStringNotContainsString('PRIVATE-PASSWORD', $employee->notifications()->first()->data ? json_encode($employee->notifications()->first()->data) : '');
    }

    public function test_assignment_notifies_only_new_active_employee_and_retains_later_reassignment_events(): void
    {
        $admin = $this->user('admin');
        $citizen = $this->user('citizen');
        $employee = $this->user('employee');
        $other = $this->user('employee');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        foreach ([$employee, $other] as $target) {
            (new ServiceReviewPermission)->forceFill(['user_id' => $target->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $admin->id])->save();
            $target->notifications()->delete();
        }
        $request = $citizen->serviceRequests()->create(['reference' => 'OPS-TEST', 'service_code' => $service->code, 'status' => 'submitted']);
        $this->actingAs($admin);
        $request->forceFill(['assigned_to' => $employee->id])->save();
        $request->forceFill(['assigned_to' => $other->id])->save();
        $request->forceFill(['assigned_to' => $employee->id])->save();
        $this->assertSame(2, $employee->notifications()->count());
        $this->assertSame(1, $other->notifications()->count());
        $note = $employee->notifications()->first();
        $this->assertSame('request_assignment', $note->data['kind']);
        $this->assertSame('OPS-TEST', $note->data['request_reference']);
        $this->assertSame($request->id, $note->request_id);
        $this->assertArrayHasKey('occurred_at', $note->data);
        $this->assertSame(0, $citizen->notifications()->count());
    }

    private function assignedRequest(): array
    {
        $citizen = $this->user('citizen');
        $employee = $this->user('employee');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $employee->id])->save();
        $request = $citizen->serviceRequests()->create(['reference' => 'OPS-'.Str::uuid(), 'service_code' => $service->code, 'status' => 'submitted']);
        $request->forceFill(['assigned_to' => $employee->id])->save();
        $employee->notifications()->delete();

        return [$citizen, $employee, $request, $service];
    }

    public function test_request_created_with_an_authorized_assignee_notifies_once(): void
    {
        $citizen = $this->user('citizen');
        $employee = $this->user('employee');
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $employee->id])->save();
        $request = (new CitizenServiceRequest)->forceFill(['user_id' => $citizen->id, 'reference' => 'OPS-CREATED-ASSIGNED', 'service_code' => $service->code, 'status' => 'submitted', 'assigned_to' => $employee->id]);
        $request->save();
        $request->save();
        $this->assertSame(1, $employee->notifications()->count());
        $note = $employee->notifications()->first();
        $this->assertSame('request_assignment', $note->data['kind']);
        $this->assertSame($request->id, $note->request_id);
        $this->assertSame(0, $citizen->notifications()->count());
    }

    private function attachment(User $citizen, CitizenServiceRequest $request, GovernmentService $service): CitizenAttachment
    {
        $file = (new CitizenAttachment)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'document_code' => 'test', 'document_name' => 'TEST', 'kind' => 'supporting', 'rules_version' => 1, 'slot_key' => hash('sha256', Str::uuid()), 'version_token' => (string) Str::uuid(), 'storage_path' => 'PRIVATE-PATH', 'display_name' => 'PRIVATE-NAME', 'mime_type' => 'application/pdf', 'size' => 10, 'sha256' => str_repeat('a', 64), 'review_status' => 'pending']);
        $file->save();

        return $file;
    }

    public function test_citizen_attachment_changes_and_resubmission_reach_only_authorized_assignee(): void
    {
        [$citizen, $employee, $request, $service] = $this->assignedRequest();
        $other = $this->user('employee');
        $this->actingAs($citizen);
        $file = $this->attachment($citizen, $request, $service);
        $this->assertSame(1, $employee->notifications()->count());
        $file->save();
        $this->assertSame(1, $employee->notifications()->count());
        $decision = (new ReviewDecision)->forceFill(['citizen_service_request_id' => $request->id, 'actor_id' => $citizen->id, 'action' => 'resubmit', 'decision_token' => (string) Str::uuid(), 'command_hash' => str_repeat('b', 64), 'review_version' => 2, 'internal_notes' => 'PRIVATE-NOTE', 'decided_at' => now()]);
        $decision->save();
        $this->assertSame(2, $employee->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertSame(0, $citizen->notifications()->count());
        $json = json_encode($employee->notifications()->get()->toArray());
        foreach (['PRIVATE-PATH', 'PRIVATE-NAME', 'PRIVATE-NOTE'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $employee->reviewPermissions()->update(['can_review' => false]);
        $file->forceFill(['version_token' => (string) Str::uuid(), 'storage_path' => 'REPLACED-PRIVATE'])->save();
        $this->assertSame(2, $employee->notifications()->count());
    }

    public function test_staff_attachment_medical_and_finished_integration_events_are_private_and_owner_scoped(): void
    {
        [$citizen, $employee, $request, $service] = $this->assignedRequest();
        $other = $this->user('citizen');
        $file = $this->attachment($citizen, $request, $service);
        $this->actingAs($employee);
        $file->forceFill(['review_status' => 'rejected', 'reviewed_by' => $employee->id, 'public_reason' => 'PRIVATE-REASON'])->save();
        (new MedicalExamination)->forceFill(['user_id' => $citizen->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'rules_version' => 1, 'result' => 'passed', 'source_reference' => 'PRIVATE-SOURCE', 'private_notes' => 'PRIVATE-MEDICAL', 'recorded_by' => $employee->id, 'recorded_at' => now()])->save();
        $attempt = (new IntegrationAttempt)->forceFill(['id' => (string) Str::uuid(), 'citizen_service_request_id' => $request->id, 'user_id' => $citizen->id, 'actor_id' => $employee->id, 'kind' => 'identity', 'provider' => 'test', 'status' => 'pending', 'idempotency_hash' => hash('sha256', Str::uuid()), 'input_fingerprint' => 'PRIVATE-FINGERPRINT', 'started_at' => now()]);
        $attempt->save();
        $this->assertSame(2, $citizen->notifications()->count());
        $attempt->forceFill(['status' => 'verified', 'finished_at' => now()])->save();
        $this->assertSame(3, $citizen->notifications()->count());
        $this->assertSame(0, $other->notifications()->count());
        $this->assertEqualsCanonicalizing(['attachment_update', 'examination_update', 'integration_update'], $citizen->notifications()->get()->pluck('data.kind')->all());
        $json = json_encode($citizen->notifications()->get()->toArray());
        foreach (['PRIVATE-REASON', 'PRIVATE-SOURCE', 'PRIVATE-MEDICAL', 'PRIVATE-FINGERPRINT'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
    }

    public function test_admin_permission_grant_change_revocation_and_employee_creation_are_targeted(): void
    {
        $this->actingAs($admin = $this->user('admin'));
        $employee = $this->user('employee');
        $this->assertSame('employee_account', $employee->notifications()->first()->data['kind']);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $permission = (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $admin->id]);
        $permission->save();
        $permission->save();
        $permission->forceFill(['can_review' => false])->save();
        $permission->delete();
        $this->assertSame(4, $employee->notifications()->count());
        $employee->forceFill(['is_active' => false])->save();
        $this->assertSame(5, $employee->notifications()->count());
        $this->assertSame(0, $admin->notifications()->count());
    }

    public function test_real_booking_and_manual_payment_services_notify_success_and_dedupe_replay(): void
    {
        [$citizen, $employee, $request, $service] = $this->assignedRequest();
        $service->forceFill(['is_active' => true, 'rules_approved' => true])->save();
        $request->forceFill(['service_fee' => '25.00', 'service_currency' => 'SAR'])->save();
        $slot = (new AppointmentSlot)->forceFill(['government_service_id' => $service->id, 'center' => 'TEST', 'starts_at' => now()->addDays(2), 'ends_at' => now()->addDays(2)->addHour(), 'capacity' => 1, 'is_active' => true, 'created_by' => $employee->id]);
        $slot->save();
        // No authenticated global actor: direct services must use their authorized actor.
        $booking = app(AppointmentBookings::class)->book($employee, $request, $slot->id);
        $this->assertSame('appointment_update', $citizen->notifications()->first()->data['kind']);
        app(AppointmentBookings::class)->change($citizen, $booking, 'cancel');
        $this->assertSame('appointment_update', $employee->notifications()->first()->data['kind']);
        $data = ['amount' => '25.00', 'currency' => 'SAR', 'receipt_reference' => 'PRIVATE-RECEIPT', 'method' => 'cash', 'paid_at' => today()->toDateString()];
        $charges = app(RequestCharges::class);
        $charges->record($employee, $request, $data);
        $charges->record($employee, $request, $data);
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'payment_update')->count());
        $this->assertStringNotContainsString('PRIVATE-RECEIPT', json_encode($citizen->notifications()->get()->toArray()));
    }
}
