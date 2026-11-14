<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\IdentityCardForm;
use App\Models\IdentityCardWitness;
use App\Models\User;
use App\Models\CitizenAttachment;
use App\Models\IdentityCardAttestation;
use App\Models\ServiceReviewPermission;
use App\Services\Identity\InitialIdentityReview;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class InitialIdentityReviewTest extends TestCase
{
    use \Tests\Support\InitialIdentityEvidence;
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('In-memory database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->app['env'] = 'local';
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class);
        config(['citizen_mock.enabled' => true]);
        Storage::fake('attachments');
    }

    private function form(): IdentityCardForm
    {
        $owner = User::factory()->create();
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $request = $owner->serviceRequests()->create(['reference' => 'REVIEW-'.Str::ulid(), 'service_code' => $service->code, 'status' => 'under_review']);
        $form = (new IdentityCardForm)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'journey_version' => 1, 'reference' => 'FORM-'.Str::ulid(), 'status' => 'pending_photo_review', 'submitted_at' => now(), 'version' => 1, 'data' => []]);
        $form->save();
        return $form;
    }

    public function test_missing_evidence_is_never_a_match_or_an_approval(): void
    {
        $tasks = app(InitialIdentityReview::class)->tasks($this->form());
        $this->assertSame(['photo', 'witness_1', 'witness_2', 'attestation'], array_keys($tasks));
        foreach ($tasks as $task) {
            $this->assertFalse($task['ready']);
            $this->assertFalse($task['approved']);
        }
    }

    public function test_witness_is_not_linked_by_name_and_selfie_is_not_consent(): void
    {
        $form = $this->form();
        (new IdentityCardWitness)->forceFill(['identity_card_form_id' => $form->id, 'position' => 1, 'name' => 'اسم متشابه', 'document_type' => 'بطاقة شخصية', 'document_number' => 'NO-SUCH-DOCUMENT'])->save();
        $task = app(InitialIdentityReview::class)->tasks($form)['witness_1'];
        $this->assertFalse($task['ready']);
        $this->assertNull($task['source_id']);
        $this->assertStringContainsString('موافقة', $task['message']);
    }

    private function reviewed(): array
    {
        config(['identity_card.simulation_enabled' => true]);
        $form = $this->form();
        $staff = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => $form->government_service_id, 'can_review' => true, 'granted_by' => $staff->id])->save();
        $form->application->forceFill(['assigned_to' => $staff->id])->save();
        $file = UploadedFile::fake()->image('photo.png', 600, 600);
        $path = 'citizen-files/'.Str::uuid().'.png';
        Storage::disk('attachments')->put($path, file_get_contents($file->getRealPath()));
        $photo = (new CitizenAttachment)->forceFill(['user_id' => $form->user_id, 'government_service_id' => $form->government_service_id,
            'citizen_service_request_id' => $form->citizen_service_request_id, 'document_code' => 'identity_photo', 'document_name' => 'Synthetic photo',
            'kind' => 'photo', 'slot_key' => hash('sha256', (string) Str::uuid()), 'rules_version' => 1, 'version_token' => (string) Str::uuid(),
            'storage_path' => $path, 'display_name' => 'photo.png', 'mime_type' => 'image/png', 'size' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()), 'review_status' => 'pending']);
        $photo->save();
        $form->forceFill(['citizen_attachment_id' => $photo->id])->save();
        foreach ([1, 2] as $position) {
            (new IdentityCardWitness)->forceFill(['identity_card_form_id' => $form->id, 'position' => $position, 'name' => 'Synthetic witness '.$position,
                'document_type' => 'بطاقة شخصية', 'document_number' => 'TEST-'.Str::uuid(), 'issued_place' => 'Synthetic', 'issued_at' => '2020-01-01'])->save();
        }
        (new IdentityCardAttestation)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => 1, 'citizen_name' => $form->citizen->name,
            'reference' => 'ATT-'.Str::ulid(), 'authority_type' => 'neighborhood', 'recipient_name' => 'Synthetic official', 'status' => 'approved',
            'created_by' => $form->user_id, 'decided_by' => $staff->id, 'decided_at' => now(), 'verified_at' => now(), 'verification_source' => 'local_employee_confirmation'])->save();
        $this->completeInitialEvidence($form, $staff);
        return [$form->fresh(), $staff];
    }

    public function test_all_current_tasks_are_approved_and_render_in_request_file(): void
    {
        [$form, $staff] = $this->reviewed();
        foreach (app(InitialIdentityReview::class)->tasks($form) as $task) {
            $this->assertTrue((bool) $task['approved']);
        }
        $this->actingAs($staff)->get(route('employee.applications.review', $form->application))
            ->assertOk()->assertSee('٤ — الصورة الشخصية')->assertSee('٦ — مصادقة العاقل')
            ->assertSee('سجل وثائق محاكاة التسجيل')->assertSee('نتائج النظام وبيانات الطلب');
    }

    public function test_source_changes_invalidate_witness_proof_and_official_revocation_invalidates_attestation(): void
    {
        [$form] = $this->reviewed();
        $review = app(InitialIdentityReview::class);
        $before = $review->tasks($form);
        DB::table('mock_civil_documents')->where('id', $before['witness_1']['source_id'])->update(['issued_place' => 'Changed']);
        $form->attestation->official->forceFill(['is_active' => false])->save();
        $after = $review->tasks($form);
        $this->assertFalse((bool) $after['witness_1']['approved']);
        $this->assertFalse((bool) $after['attestation']['approved']);
        $this->assertTrue((bool) $after['witness_2']['approved']);
        $this->assertNotSame($before['witness_1']['fingerprint'], $after['witness_1']['fingerprint']);
    }

    public function test_missing_consent_cannot_be_approved_and_another_employee_cannot_record_decisions(): void
    {
        [$form, $staff] = $this->reviewed();
        $task = app(InitialIdentityReview::class)->tasks($form)['witness_1'];
        $input = ['task' => 'witness_1', 'outcome' => 'approved', 'fingerprint' => $task['fingerprint'], 'decision_token' => (string) Str::uuid(), 'confirmed' => '1'];
        $this->actingAs($staff)->post(route('employee.applications.identity-step', $form->application), $input)->assertSessionHasErrors('consent_reference');
        $other = User::factory()->create(['role' => 'employee']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $other->id, 'government_service_id' => $form->government_service_id, 'can_review' => true, 'granted_by' => $staff->id])->save();
        $this->actingAs($other)->post(route('employee.applications.identity-step', $form->application), $input)->assertForbidden();
    }

    public function test_replay_is_idempotent_and_cannot_be_reused_for_another_task(): void
    {
        [$form, $staff] = $this->reviewed();
        $review = app(InitialIdentityReview::class);
        $task = $review->tasks($form)['witness_1'];
        $input = ['task' => 'witness_1', 'outcome' => 'approved', 'fingerprint' => $task['fingerprint'], 'decision_token' => (string) Str::uuid(),
            'confirmed' => '1', 'consent_method' => 'signed_statement', 'consent_reference' => 'TEST-REFERENCE'];
        $first = $review->record($staff, $form->application, $input);
        $this->assertSame($first->id, $review->record($staff, $form->application, $input)->id);
        $input['task'] = 'witness_2';
        $this->expectException(ValidationException::class);
        $review->record($staff, $form->application, $input);
    }

    public function test_missing_task_proofs_block_preliminary_acceptance(): void
    {
        $form = $this->form();
        $checks = app(InitialIdentityReview::class)->requirements($form->application, \App\Enums\ServiceRequestStatus::Accepted);
        $this->assertCount(4, $checks);
        foreach ($checks as $check) {
            $this->assertTrue($check->mandatory);
            $this->assertSame(\App\Enums\RequirementState::NeedsReview, $check->state);
        }
        $form->forceFill(['journey_version' => null])->save();
        $this->assertSame([], app(InitialIdentityReview::class)->requirements($form->application->fresh(), \App\Enums\ServiceRequestStatus::Accepted));
    }

    public function test_existing_version_one_acceptance_keeps_its_downstream_path(): void
    {
        $form = $this->form();
        $staff = User::factory()->create(['role' => 'employee']);
        $application = $form->application;
        DB::table('citizen_service_requests')->where('id', $application->id)->update(['status' => 'accepted']);
        app(\App\Services\Review\ReviewActions::class)->save($application->fresh(), $staff, 'accept', 'under_review', null, []);
        $this->assertSame([], app(InitialIdentityReview::class)->requirements($application->fresh(), \App\Enums\ServiceRequestStatus::Processing));
    }

    public function test_service_rejects_false_confirmation_from_direct_callers(): void
    {
        [$form, $staff] = $this->reviewed();
        $task = app(InitialIdentityReview::class)->tasks($form)['witness_1'];
        $this->expectException(ValidationException::class);
        app(InitialIdentityReview::class)->record($staff, $form->application, ['task' => 'witness_1', 'outcome' => 'approved',
            'fingerprint' => $task['fingerprint'], 'decision_token' => (string) Str::uuid(), 'confirmed' => 'false',
            'consent_method' => 'anything', 'consent_reference' => ' ']);
    }

    public function test_stale_form_submission_is_rejected_and_request_notification_opens_review(): void
    {
        [$form, $staff] = $this->reviewed();
        $task = app(InitialIdentityReview::class)->tasks($form)['witness_1'];
        DB::table('mock_civil_documents')->where('id', $task['source_id'])->update(['status' => 'cancelled']);
        $this->actingAs($staff)->post(route('employee.applications.identity-step', $form->application), [
            'task' => 'witness_1', 'outcome' => 'approved', 'fingerprint' => $task['fingerprint'], 'decision_token' => (string) Str::uuid(),
            'confirmed' => '1', 'consent_method' => 'signed_statement', 'consent_reference' => 'TEST-REFERENCE',
        ])->assertSessionHasErrors('review');
        $notification = $staff->notifications()->create(['id' => (string) Str::uuid(), 'type' => 'test_request',
            'request_id' => $form->citizen_service_request_id, 'data' => ['kind' => 'request', 'title' => 'Synthetic request']]);
        $this->post(route('employee.notifications.open', $notification))->assertRedirect(route('employee.applications.review', $form->application));
        $this->assertNotNull($notification->fresh()->read_at);
    }
}
