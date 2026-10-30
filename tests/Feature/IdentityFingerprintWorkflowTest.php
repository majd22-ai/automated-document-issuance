<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Observers\IdentityAttestationObserver;
use App\Services\FingerprintService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class IdentityFingerprintWorkflowTest extends TestCase
{
    private User $citizen;

    private User $employee;

    private User $admin;

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
        config(['identity_card.simulation_enabled' => true, 'identity_card.fingerprint.simulation_enabled' => true]);
        Storage::fake('attachments');
        $this->citizen = User::factory()->create(['username' => 'FLOW-TEST']);
        $this->employee = User::factory()->create(['role' => 'employee']);
        $this->admin = User::factory()->create(['role' => 'admin']);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST-only configured service']);
        $service->assessments()->create(['user_id' => $this->citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'reviewed_by' => $this->admin->id, 'reviewed_at' => now(), 'verification_reference' => 'TEST-only assessment']);
        (new ServiceReviewPermission)->forceFill(['user_id' => $this->employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'can_approve' => true, 'granted_by' => $this->admin->id])->save();
    }

    private function fields(int $step): array
    {
        $data = [];
        foreach (config('identity_card.fields.'.$step) as $key => $field) {
            $data[$key] = isset($field['options']) ? array_key_first($field['options']) : (($field['type'] ?? '') === 'date' ? '2000-01-01' : 'اختبار محلي');
            if (in_array($key, ['phone', 'alternate_phone'])) {
                $data[$key] = '777123456';
            }
        }

        return $data;
    }

    private function save(IdentityCardForm $form, int $step, array $data): void
    {
        $this->actingAs($this->citizen)->post('/citizen/identity-card/'.$form->id, $data + ['version' => $form->fresh()->version, 'step' => $step, 'action' => 'next'])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function completeWizard(IdentityCardForm $form, bool $photo = true): void
    {
        foreach ([1, 2, 3] as $step) {
            $this->save($form, $step, ['data' => $this->fields($step)]);
        }
        $this->save($form, 4, $photo ? ['photo' => UploadedFile::fake()->image('portrait.png', 600, 600)] : []);
        $witnesses = [];
        foreach ([1, 2] as $position) {
            $witnesses[] = ['name' => 'شاهد '.$position, 'document_type' => 'بطاقة', 'document_number' => '123456'.$position, 'issued_place' => 'صنعاء', 'issued_at' => '2020-01-01', 'phone' => '777123456', 'address' => 'عنوان الاختبار'];
        }
        $this->save($form, 5, ['witnesses' => $witnesses]);
        $this->actingAs($this->citizen)->get('/citizen/identity-card/'.$form->id.'?step=6')->assertOk();
        $reviews = session('identity_card_reviews');
        $this->post('/citizen/identity-card/'.$form->id.'/submit', ['version' => $form->fresh()->version, 'declaration' => '1', 'review_token' => array_key_last($reviews)])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function submitted(): IdentityCardForm
    {
        $this->actingAs($this->citizen)->post('/citizen/identity-card')->assertRedirect();
        $form = IdentityCardForm::sole();
        $this->completeWizard($form);
        $photo = $form->fresh()->photo;
        $this->actingAs($this->employee)->patch('/employee/attachments/'.$photo->id.'/review', ['expected_version' => $photo->version_token, 'review_status' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();

        return $form->fresh();
    }

    private function attest(IdentityCardForm $form, string $type = 'workplace'): void
    {
        $form = $form->fresh();
        // Continue exercising historical requests without exposing legacy choices to new applicants.
        (new IdentityCardAttestation)->forceFill([
            'identity_card_form_id' => $form->id, 'form_revision' => $form->revision, 'reference' => 'SIM-ATT-'.Str::ulid(),
            'authority_type' => $type, 'recipient_name' => 'جهة اختبار', 'created_by' => $this->citizen->id,
            'status' => 'pending', 'security_required' => $type === 'neighborhood', 'security_status' => $type === 'neighborhood' ? 'pending' : 'not_required',
            'notification_created_at' => now(),
        ])->save();
        $row = $form->fresh()->attestation;
        $this->actingAs($this->employee)->post('/simulation/identity-attestations/'.$row->id.'/decision', ['decision' => 'approved'])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function arrive(IdentityCardForm $form): void
    {
        $this->actingAs($this->employee)->post('/identity-workflow/'.$form->id.'/arrival', ['version' => $form->fresh()->version, 'identity_checked' => '1', 'documents_checked' => '1', 'evidence_reference' => 'TEST arrival record'])->assertSessionHasNoErrors()->assertRedirect();
    }

    private function capture(IdentityCardForm $form, bool $failure = false)
    {
        return $this->actingAs($this->employee)->postJson('/identity-workflow/'.$form->id.'/fingerprint', ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid(), 'simulate_failure' => $failure]);
    }

    private function decide(IdentityCardForm $form, string $action, array $data = [])
    {
        return $this->actingAs($this->admin)->post('/identity-workflow/'.$form->id.'/administration', $data + ['version' => $form->fresh()->version, 'decision_token' => (string) Str::uuid(), 'action' => $action]);
    }

    public function test_complete_citizen_attestation_arrival_simulation_administration_and_print_cycle(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        // An approved form from phase two must move to attendance when upgrading.
        DB::table('identity_card_forms')->where('id', $form->id)->update(['status' => 'pending_administrative_review']);
        (require database_path('migrations/2026_10_29_000003_complete_identity_trial_workflow.php'))->up();
        $this->assertSame('pending_fingerprint_attendance', $form->fresh()->status);
        $this->actingAs($this->employee)->get('/identity-workflow/reception?reference='.$form->application->reference)->assertOk()->assertSee('اختبار محلي');
        $this->arrive($form);
        $this->get('/identity-workflow/'.$form->id.'/fingerprint')->assertOk()->assertSee('جهاز بصمة الأصابع');
        $this->capture($form)->assertOk()->assertJson(['result' => 'success']);
        $operation = $form->operations()->firstOrFail();
        $this->assertSame('simulation', $operation->source);
        $this->assertSame($this->employee->id, $operation->recorded_by);
        $this->assertSame('pending_administrative_review', $form->fresh()->status);
        $this->actingAs($this->admin)->get('/identity-workflow/'.$form->id.'/administration')->assertOk()->assertSee('المراجعة الإدارية');
        $this->decide($form, 'approved', ['internal_note' => 'INTERNAL-PRIVATE-NOTE'])->assertSessionHasNoErrors()->assertRedirect();
        $this->decide($form, 'approved')->assertStatus(409);
        $this->assertSame(1, $form->administrativeDecisions()->count());
        $this->assertSame('demo_completed', $form->fresh()->status);
        $this->get('/identity-card/forms/'.$form->id.'/photo')->assertOk();
        $this->actingAs($this->citizen)->get('/identity-workflow/'.$form->id.'/print')->assertOk()->assertSee('نموذج تجريبي')->assertSee('class="attestation-stamp"', false)->assertSee('data-identity-print', false)->assertDontSee('INTERNAL-PRIVATE-NOTE')->assertDontSee('disabled', false);
        $this->get('/citizen/identity-card/'.$form->id)->assertOk()->assertDontSee('INTERNAL-PRIVATE-NOTE');
        $this->assertSame(0, $this->citizen->documents()->count());
        $this->assertNull($this->citizen->fresh()->identity_verified_at);
    }

    public function test_failure_retry_and_success_replay_never_change_the_result_source(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        $this->arrive($form);
        $this->capture($form, true)->assertOk()->assertJson(['result' => 'failure']);
        $this->assertSame('fingerprint_failed', $form->fresh()->status);
        $this->capture($form)->assertOk();
        $this->assertSame(2, $form->operations()->count());
        $operation = $form->operations()->where('result', 'success')->firstOrFail();
        $this->actingAs($this->employee)->postJson('/identity-workflow/'.$form->id.'/fingerprint', ['version' => 1, 'operation_token' => $operation->operation_token])->assertOk();
        $this->assertSame(2, $form->operations()->count());
        $this->capture($form)->assertStatus(409);
        $this->postJson('/identity-workflow/'.$form->id.'/fingerprint', ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid(), 'source' => 'trusted_integration'])->assertUnprocessable();
        try {
            $operation->forceFill(['source' => 'trusted_integration'])->save();
            $this->fail('Source mutation must fail');
        } catch (\LogicException) {
        }
        $this->assertSame('simulation', $operation->fresh()->source);
    }

    public function test_missing_attestation_arrival_and_permissions_block_capture_and_approval(): void
    {
        $form = $this->submitted();
        $this->capture($form)->assertStatus(409);
        $this->decide($form, 'approved')->assertStatus(409);
        $this->attest($form);
        $this->capture($form)->assertUnprocessable();
        $this->actingAs($this->citizen)->get('/identity-workflow/'.$form->id.'/fingerprint')->assertForbidden();
        $this->postJson('/identity-workflow/'.$form->id.'/fingerprint', ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid()])->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/identity-workflow/'.$form->id.'/print')->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'employee']))->get('/identity-workflow/reception')->assertForbidden();
    }

    public function test_correction_creates_new_revision_without_reusing_attestation_or_fingerprint_or_changing_original_snapshot(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        $this->arrive($form);
        $this->capture($form)->assertOk();
        $snapshot = $form->application->request_data;
        $reference = $form->application->reference;
        $oldAttestation = $form->fresh()->attestation;
        DB::table('citizen_service_requests')->where('id', $form->citizen_service_request_id)->update(['status' => 'accepted']);
        $this->decide($form, 'correction_required', ['citizen_reason' => 'صحح البيانات', 'internal_note' => 'PRIVATE-CORRECTION'])->assertSessionHasNoErrors();
        $this->assertSame(2, $form->fresh()->revision);
        $this->assertNull($form->fresh()->attestation);
        $this->actingAs($this->citizen)->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee('صحح البيانات')->assertDontSee('PRIVATE-CORRECTION');
        $this->completeWizard($form, false);
        $this->assertSame($snapshot, $form->application->fresh()->request_data);
        $this->assertSame($reference, $form->application->fresh()->reference);
        $this->assertSame(1, $this->citizen->serviceRequests()->count());
        $this->assertNull(app(FingerprintService::class)->successfulOperation($form->fresh()));
        $this->attest($form);
        $this->actingAs($this->employee)->post('/simulation/identity-attestations/'.$oldAttestation->id.'/decision', ['decision' => 'approved'])->assertStatus(409);
        $this->arrive($form);
        $this->capture($form)->assertOk();
        $this->decide($form, 'approved')->assertSessionHasNoErrors();
        $this->assertSame(2, $form->operations()->where('result', 'success')->count());
    }

    public function test_returned_request_can_open_a_correction_when_its_old_fingerprint_binding_changed(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        $this->arrive($form);
        $this->capture($form)->assertOk();
        $this->decide($form, 'returned_to_staff', ['internal_note' => 'استكمل المراجعة'])->assertSessionHasNoErrors();
        $form->fresh()->photo->forceFill(['version_token' => (string) Str::uuid()])->save();
        $this->actingAs($this->employee)->post('/identity-workflow/'.$form->id.'/requeue', ['version' => $form->fresh()->version, 'note' => 'تمت المراجعة'])->assertSessionHasErrors('requirements');
        $this->decide($form, 'correction_required', ['citizen_reason' => 'راجع الصورة'])->assertSessionHasNoErrors();
        $this->assertTrue($form->fresh()->correctionIsOpen());
    }

    public function test_security_paper_and_manual_result_are_explicit_and_never_a_trusted_integration(): void
    {
        $form = $this->submitted();
        $this->attest($form, 'neighborhood');
        $this->arriveBlocked($form);
        $this->actingAs($this->employee)->post('/identity-workflow/'.$form->id.'/security-paper', ['version' => $form->fresh()->version, 'paper_checked' => '1', 'evidence_reference' => 'TEST actual paper reference'])->assertSessionHasNoErrors();
        $this->arrive($form);
        $payload = ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid(), 'result' => 'success', 'manual_confirmed' => '1', 'evidence_reference' => 'TEST manually recorded result', 'executed_at' => now()->subMinute()->format('Y-m-d H:i:s')];
        $this->post('/identity-workflow/'.$form->id.'/manual-device-result', $payload)->assertForbidden();
        config(['identity_card.fingerprint.manual_result_enabled' => true]);
        $this->post('/identity-workflow/'.$form->id.'/manual-device-result', $payload)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('administrative_real_device', $form->operations()->first()->source);
        $this->assertNull($this->citizen->fresh()->identity_verified_at);
    }

    private function arriveBlocked(IdentityCardForm $form): void
    {
        $this->actingAs($this->employee)->post('/identity-workflow/'.$form->id.'/arrival', ['version' => $form->fresh()->version, 'identity_checked' => '1', 'documents_checked' => '1', 'evidence_reference' => 'TEST'])->assertStatus(409);
    }

    public function test_rejection_has_no_stamp_and_cancellation_closes_an_open_correction(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        $this->arrive($form);
        $this->capture($form)->assertOk();
        $this->decide($form, 'correction_required', ['citizen_reason' => 'صحح البيانات'])->assertSessionHasNoErrors();
        $application = $form->application->fresh();
        $application->forceFill(['status' => 'cancelled']);
        $application->syncChanges();
        DB::table('citizen_service_requests')->where('id', $application->id)->update(['status' => 'cancelled']);
        $this->actingAs($this->citizen)->post('/citizen/identity-card/'.$form->id, ['version' => $form->fresh()->version, 'step' => 1, 'action' => 'save', 'data' => ['first_name' => 'تغيير غير مسموح']])->assertStatus(409);
        app(IdentityAttestationObserver::class)->updated($application);
        $this->assertSame('cancelled', $form->fresh()->status);
        $this->assertFalse($form->fresh()->correctionIsOpen());
        $this->get('/identity-workflow/'.$form->id.'/print')->assertOk()->assertDontSee('class="attestation-stamp"', false)->assertSee('غير جاهزة للطباعة');
    }

    public function test_administrative_rejection_is_publicly_explained_but_internal_notes_are_private(): void
    {
        $form = $this->submitted();
        $this->attest($form);
        $this->arrive($form);
        $this->capture($form)->assertOk();
        $this->decide($form, 'rejected', ['citizen_reason' => 'سبب مسموح للمواطن', 'internal_note' => 'STAFF-ONLY-REJECTION'])->assertSessionHasNoErrors();
        $this->actingAs($this->citizen)->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee('سبب مسموح للمواطن')->assertDontSee('STAFF-ONLY-REJECTION');
        $this->get('/identity-workflow/'.$form->id.'/print')->assertOk()->assertDontSee('class="attestation-stamp"', false)->assertDontSee('STAFF-ONLY-REJECTION')->assertSee('غير جاهزة للطباعة');
        $this->app['env'] = 'production';
        $this->actingAs($this->employee)->postJson('/identity-workflow/'.$form->id.'/fingerprint', ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid()])->assertForbidden();
    }
}
