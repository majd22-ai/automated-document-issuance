<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\IdentityCardWitness;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\IdentityAttestations;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityAttestationTest extends TestCase
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
        $this->app['env'] = 'local';
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['identity_card.simulation_enabled' => true]);
    }

    private function form(): IdentityCardForm
    {
        $citizen = User::factory()->create();
        $application = $citizen->serviceRequests()->create(['reference' => 'REQ-'.Str::ulid(), 'service_code' => 'identity-first-issue', 'status' => 'submitted']);
        $form = new IdentityCardForm;
        $form->forceFill(['user_id' => $citizen->id, 'government_service_id' => $application->government_service_id, 'citizen_service_request_id' => $application->id,
            'reference' => 'IDFORM-'.Str::ulid(), 'status' => 'pending_photo_review', 'submitted_at' => now(), 'current_step' => 7, 'data' => ['applicant_name' => 'مواطن اختبار', 'phone' => '777123456', 'house' => 'عنوان خاص']])->save();
        for ($position = 1; $position <= 2; $position++) {
            (new IdentityCardWitness)->forceFill(['identity_card_form_id' => $form->id, 'position' => $position, 'name' => 'شاهد '.$position, 'document_number' => 'PRIVATE-WITNESS-'.$position, 'document_type' => 'بطاقة', 'issued_at' => '2020-01-01'])->save();
        }

        return $form;
    }

    private function employee(IdentityCardForm $form): User
    {
        $employee = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $form->government_service_id, 'can_review' => true, 'granted_by' => $employee->id])->save();

        return $employee;
    }

    private function choose(IdentityCardForm $form, string $type = 'workplace'): IdentityCardAttestation
    {
        $this->actingAs($form->citizen)->post('/citizen/identity-card/'.$form->id.'/attestation', ['authority_type' => $type, 'recipient_name' => 'جهة اختبار'])->assertSessionHasNoErrors()->assertRedirect();

        return $form->attestation()->firstOrFail();
    }

    public function test_workplace_and_neighborhood_create_one_persistent_notice_and_open_the_correct_request(): void
    {
        foreach (['workplace', 'neighborhood'] as $type) {
            $form = $this->form();
            $row = $this->choose($form, $type);
            $this->assertNotNull($row->notification_created_at);
            $this->assertNull($row->notification_read_at);
            $this->assertSame('pending', $row->status);
            $this->assertSame($type === 'neighborhood', $row->security_required);
            $this->choose($form, $type);
            $this->assertSame(1, $form->attestation()->count());
            $this->actingAs($this->employee($form));
            $feed = $this->getJson('/simulation/identity-attestations/notifications')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            $feed->assertJsonFragment(['request_reference' => $form->application->reference, 'recipient_name' => 'جهة اختبار', 'read' => false]);
            $feed->assertDontSee('777123456')->assertDontSee('عنوان خاص')->assertDontSee('PRIVATE-WITNESS');
            $this->post('/simulation/identity-attestations/'.$row->id.'/open')->assertRedirect('/simulation/identity-attestations/'.$row->id);
            $this->assertNotNull($row->fresh()->notification_read_at);
            $this->assertSame('pending', $row->fresh()->status);
            $this->get('/simulation/identity-attestations/'.$row->id)->assertOk()->assertSee($form->application->reference)->assertDontSee('class="attestation-stamp"', false);
        }
    }

    public function test_approval_is_atomic_stamped_and_not_a_security_office_approval(): void
    {
        foreach (['workplace', 'neighborhood'] as $type) {
            $form = $this->form();
            $row = $this->choose($form, $type);
            $employee = $this->employee($form);
            $this->actingAs($employee)->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
            $this->assertSame('approved', $row->fresh()->status);
            $this->assertSame($employee->id, $row->fresh()->decided_by);
            $this->assertNotNull($row->fresh()->decided_at);
            $this->assertSame($type === 'neighborhood' ? 'pending_security_review' : 'pending_fingerprint_attendance', $form->fresh()->status);
            $this->assertSame($type === 'neighborhood' ? 'pending' : 'not_required', $row->fresh()->security_status);
            $this->get('/simulation/identity-attestations/'.$row->id)->assertOk()->assertSee('class="attestation-stamp"', false)->assertSee($row->reference);
            $this->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'rejected', 'reason' => 'محاولة ثانية'])->assertStatus(409);
            $this->assertSame('approved', $row->fresh()->status);
            $this->assertSame(1, $form->citizen->notifications()->where('data->kind', 'request_activity')->count());
        }
    }

    public function test_rejection_requires_reason_and_blocks_dependent_stage_without_a_stamp(): void
    {
        $form = $this->form();
        $row = $this->choose($form);
        $this->actingAs($this->employee($form))->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'rejected'])->assertSessionHasErrors('reason');
        $this->assertSame('pending', $row->fresh()->status);
        $this->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'rejected', 'reason' => 'سبب تجريبي موثق'])->assertRedirect();
        $this->assertSame('rejected', $form->fresh()->status);
        $this->assertSame('سبب تجريبي موثق', $row->fresh()->rejection_reason);
        $this->get('/simulation/identity-attestations/'.$row->id)->assertOk()->assertSee('سبب تجريبي موثق')->assertDontSee('class="attestation-stamp"', false);
        $checks = app(IdentityAttestations::class)->requirements($form->application->fresh(), ServiceRequestStatus::Approved);
        $this->assertTrue($checks[0]->mandatory);
        $this->assertSame('unmet', $checks[0]->state->value);
        $this->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved'])->assertStatus(409);
        $this->actingAs($form->citizen)->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee('سبب تجريبي موثق')->assertDontSee('class="attestation-stamp"', false);
    }

    public function test_witness_path_has_no_mobile_notification_and_needs_both_present_and_verified(): void
    {
        $form = $this->form();
        $row = $this->choose($form, 'witnesses');
        $this->assertNull($row->notification_created_at);
        $this->actingAs($this->employee($form))->getJson('/simulation/identity-attestations/notifications')->assertJson(['unread' => 0, 'notifications' => []]);
        $url = '/simulation/identity-attestations/'.$row->id.'/decision';
        $this->post($url, ['decision' => 'approved'])->assertSessionHasErrors('witnesses');
        $results = [['attendance' => 'present', 'verification' => 'approved'], ['attendance' => 'absent', 'verification' => 'approved']];
        $this->post($url, ['decision' => 'approved', 'witnesses' => $results])->assertSessionHasErrors('witnesses.1.attendance');
        $this->assertSame(['not_recorded'], $form->witnesses()->pluck('attendance_status')->unique()->values()->all());
        $results[1]['attendance'] = 'present';
        $this->post($url, ['decision' => 'approved', 'witnesses' => $results])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame(['present'], $form->witnesses()->pluck('attendance_status')->unique()->values()->all());
        $this->assertSame(['approved'], $form->witnesses()->pluck('verification_status')->unique()->values()->all());
        $this->assertSame('pending_fingerprint_attendance', $form->fresh()->status);
    }

    public function test_citizens_ungranted_staff_disabled_accounts_and_production_cannot_simulate(): void
    {
        $form = $this->form();
        $row = $this->choose($form);
        $url = '/simulation/identity-attestations/'.$row->id;
        foreach ([$form->citizen, User::factory()->create(), User::factory()->create(['role' => 'employee'])] as $actor) {
            $this->actingAs($actor)->get($url)->assertForbidden();
            $this->getJson('/simulation/identity-attestations/notifications')->assertForbidden();
            $this->post($url.'/decision', ['decision' => 'approved'])->assertForbidden();
            $this->post($url.'/open')->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin', 'is_active' => false]))->get($url)->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->post('/citizen/identity-card/'.$form->id.'/attestation', ['authority_type' => 'workplace', 'recipient_name' => 'غير مخول'])->assertForbidden();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get($url)->assertOk()->assertSee('محاكاة إشعارات الجوال');
        $this->app['env'] = 'production';
        $this->get($url)->assertNotFound();
        $this->getJson('/simulation/identity-attestations/notifications')->assertNotFound();
        $this->post($url.'/decision', ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $row->fresh()->status);
    }

    public function test_csrf_is_required_for_simulation_decisions(): void
    {
        $form = $this->form();
        $row = $this->choose($form);
        $this->withMiddleware(ValidateCsrfToken::class);
        $this->actingAs($this->employee($form))->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved'])->assertStatus(419);
        $this->assertSame('pending', $row->fresh()->status);
    }

    public function test_promoting_the_applicant_never_allows_self_approval(): void
    {
        $form = $this->form();
        $row = $this->choose($form);
        $owner = $form->citizen;
        $owner->forceFill(['role' => 'admin'])->save();
        $this->actingAs($owner)->getJson('/simulation/identity-attestations/notifications')->assertJson(['unread' => 0, 'notifications' => []]);
        $this->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved'])->assertForbidden();
        $this->assertSame('pending', $row->fresh()->status);
    }
}
