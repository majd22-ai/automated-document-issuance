<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Models\GovernmentService;
use App\Models\LostCardCase;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LostCardWorkflowTest extends TestCase
{
    public function test_citizen_and_staff_complete_loss_publication_and_replacement_review(): void
    {
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('Isolated database required');
        }
        $this->artisan('migrate')->assertSuccessful();
        $admin = User::factory()->create(['role' => 'admin']);
        $citizen = User::factory()->create(['name' => 'سالم أحمد ناصر العمري', 'username' => 'lost_citizen', 'national_id' => '99010000111']);
        $staff = User::factory()->create(['role' => 'employee']);
        $other = User::factory()->create(['role' => 'employee']);
        $service = GovernmentService::where('code', 'identity-lost')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'Synthetic test']);
        $service->assessments()->create([
            'user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true,
            'conditions_met' => true, 'verification_reference' => 'Synthetic test',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
        (new ServiceReviewPermission)->forceFill([
            'user_id' => $staff->id, 'government_service_id' => $service->id,
            'can_review' => true, 'can_approve' => true, 'granted_by' => $admin->id,
        ])->save();
        $entry = route('citizen.lost-cards.index');
        $this->get($entry)->assertRedirect(route('login'));
        $this->actingAs($admin)->get($entry)->assertRedirect(route('admin.lost-cards.index'));
        $this->actingAs($staff)->get($entry)->assertRedirect(route('employee.lost-cards.index'));
        $this->actingAs($other)->get($entry)->assertOk()->assertViewIs('lost-cards.access');
        $this->actingAs($citizen)->get($entry)->assertOk()->assertViewIs('lost-cards.citizen');

        $card = $citizen->documents()->create([
            'document_type' => 'بطاقة شخصية', 'document_type_code' => 'identity',
            'document_number' => 'TEST-CARD-1', 'status' => 'active', 'issued_at' => '2020-01-02',
        ]);

        $this->actingAs($citizen)->get(route('citizen.lost-cards.index'))->assertOk()->assertSee('فقدت بطاقتي');
        $report = ['card' => 'document:'.$card->id, 'lost_on' => '2026-01-01', 'loss_place' => 'موقع تجريبي'];
        $this->post(route('citizen.lost-cards.report'), $report)->assertRedirect();
        $case = LostCardCase::sole();
        $this->post(route('citizen.lost-cards.report'), $report)->assertRedirect(route('citizen.lost-cards.show', $case));
        $this->assertDatabaseCount('lost_card_cases', 1);
        $this->get(route('citizen.lost-cards.form', $case))->assertConflict();

        $this->actingAs($other)->post(route('employee.lost-cards.police', $case))->assertForbidden();
        $notice = $staff->notifications()->where('data->kind', 'lost_card_report')->sole();
        $this->actingAs($staff)->post(route('employee.notifications.open', $notice->id))->assertRedirect(route('employee.lost-cards.show', $case));
        $this->get(route('employee.lost-cards.show', $case))->assertOk()->assertSee('تقديم بلاغ الشرطة');
        $this->post(route('employee.lost-cards.notice', $case))->assertConflict();
        $this->post(route('employee.lost-cards.police', $case))->assertRedirect();
        $this->post(route('employee.lost-cards.police', $case))->assertRedirect();
        $this->post(route('employee.lost-cards.notice', $case))->assertRedirect();
        $this->assertDatabaseCount('mock_police_loss_reports', 1);
        $this->assertDatabaseCount('mock_newspaper_loss_notices', 1);

        $this->actingAs($citizen)->get(route('citizen.lost-cards.form', $case))->assertConflict();
        $publication = ['published_on' => '2026-01-02', 'issue_number' => 'TEST-12', 'page_number' => 3];
        $this->actingAs($staff)->post(route('employee.lost-cards.publish', $case), $publication)->assertRedirect();
        $this->post(route('employee.lost-cards.publish', $case), $publication)->assertRedirect();
        $this->assertSame(1, $citizen->notifications()->where('data->kind', 'lost_card_ready')->count());
        $this->get(route('employee.lost-cards.verify', [$case, 'newspaper']))->assertOk()->assertJsonPath('record.status', 'published');

        $this->actingAs($citizen)->get(route('citizen.lost-cards.form', $case))->assertOk()
            ->assertSee('2020-01-02')->assertSee('TEST-12');
        $replacement = [
            'given_name' => 'سالم', 'father_name' => 'أحمد', 'grandfather_name' => 'ناصر', 'family_name' => 'العمري',
            'issuing_governorate' => 'تعز', 'issuing_center' => 'مركز تجريبي', 'previous_issued_on' => '2020-01-02',
            'signature' => $citizen->name, 'declaration' => '1',
        ];
        $this->post(route('citizen.lost-cards.submit', $case), $replacement)->assertSessionHasNoErrors()->assertRedirect();
        $application = $case->fresh()->application;
        $this->assertSame($case->police->reference, $application->request_data['police_reference']);
        $this->assertSame($case->notice->reference, $application->request_data['notice_reference']);
        $this->post(route('citizen.lost-cards.submit', $case), $replacement)->assertRedirect();
        $this->assertDatabaseCount('citizen_service_requests', 1);
        $this->actingAs($staff)->get(route('employee.applications.review', $application->id))->assertOk()->assertSee('أدلة الفقد والنشر التجريبية');
        $application = $application->transitionTo(ServiceRequestStatus::UnderReview, $staff);
        $application = $application->transitionTo(ServiceRequestStatus::Accepted, $staff);
        $application = $application->transitionTo(ServiceRequestStatus::Approved, $staff);
        $this->assertSame('approved', $application->status);
        $this->assertDatabaseHas('audit_events', ['event' => 'lost_card.replacement_submitted', 'actor_id' => $citizen->id]);
    }
}
