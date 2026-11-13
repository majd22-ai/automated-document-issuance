<?php

namespace Tests\Feature;

use App\Casts\EncryptedJsonArray;
use App\Enums\ServiceRequestStatus;
use App\Models\AppointmentSlot;
use App\Models\CitizenAttachment;
use App\Models\GovernmentService;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\IdentityCardWitness;
use App\Models\PostalCollector;
use App\Models\PostalOffice;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\FingerprintService;
use App\Services\Identity\IdentityJourney;
use App\Services\Identity\VisitEvidence;
use App\Services\IdentityCardForms;
use App\Services\Issuance\ElectronicCopies;
use App\Services\Issuance\IssuanceLifecycle;
use App\Services\Issuance\IssueDocuments;
use App\Services\Issuance\ManageDocuments;
use App\Services\Operations\AppointmentBookings;
use App\Services\Operations\RequestCharges;
use App\Services\Oversight\AuditTrail;
use App\Services\Postal\PostalCollections;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class IdentityJourneyTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('In-memory database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        Storage::fake('attachments');
        Storage::fake('documents');
        $this->app['env'] = 'local';
        config(['postal_network.enabled' => true, 'identity_card.fingerprint.simulation_enabled' => true, 'identity_card.simulation_enabled' => true]);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['username' => 'JOURNEY-'.Str::random(8)]);
        $staff = User::factory()->create(['role' => 'employee']);
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'synthetic policy', 'fee' => '100.00', 'currency' => 'YER', 'issuance_rules' => ['enabled' => true, 'source_reference' => 'synthetic issuance', 'expiry' => ['required' => false], 'number_prefix' => 'TEST', 'include_year' => false, 'electronic_copy' => 'internal_html', 'citizen_download' => true, 'completion_stage' => 'issuance', 'delivery' => ['methods' => ['pickup'], 'centers' => ['مركز اصطناعي']]]]);
        (new ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => $service->id, 'can_review' => true, 'can_approve' => true, 'can_issue' => true, 'can_deliver' => true, 'granted_by' => $staff->id])->save();
        $service->assessments()->create(['user_id' => $owner->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'reviewed_by' => $staff->id, 'reviewed_at' => now(), 'verification_reference' => 'synthetic assessment']);
        $request = $owner->serviceRequests()->create(['reference' => 'JOURNEY-'.Str::ulid(), 'service_code' => $service->code, 'status' => 'submitted']);
        $form = (new IdentityCardForm)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id, 'citizen_service_request_id' => $request->id, 'journey_version' => 1, 'reference' => 'FORM-'.Str::ulid(), 'status' => 'pending_photo_review', 'submitted_at' => now(), 'current_step' => 7, 'version' => 1]);
        $form->save();
        $fields = [];
        foreach ([1, 2, 3] as $step) {
            foreach (config('identity_card.fields.'.$step) as $key => $field) {
                $fields[$key] = isset($field['options']) ? array_key_first($field['options']) : (($field['type'] ?? '') === 'date' ? '2000-01-01' : 'اختبار محاكاة اسم كامل');
                if (in_array($key, ['phone', 'alternate_phone'])) {
                    $fields[$key] = '777123456';
                }
            }
        }
        $photo = UploadedFile::fake()->image('synthetic.png', 600, 600);
        $path = 'citizen-files/'.Str::uuid().'.png';
        Storage::disk('attachments')->put($path, file_get_contents($photo->getRealPath()));
        $attachment = (new CitizenAttachment)->forceFill(['user_id' => $owner->id, 'government_service_id' => $service->id,
            'citizen_service_request_id' => $request->id, 'document_code' => 'synthetic_photo', 'document_name' => 'صورة اصطناعية', 'kind' => 'photo',
            'slot_key' => hash('sha256', (string) Str::uuid()), 'rules_version' => $service->rules_version, 'storage_path' => $path,
            'version_token' => (string) Str::uuid(), 'display_name' => 'synthetic.png', 'mime_type' => 'image/png', 'size' => $photo->getSize(),
            'sha256' => hash_file('sha256', $photo->getRealPath()), 'review_status' => 'approved', 'reviewed_by' => $staff->id, 'reviewed_at' => now()]);
        $attachment->save();
        $form->forceFill(['data' => $fields, 'citizen_attachment_id' => $attachment->id])->save();
        foreach ([1, 2] as $position) {
            (new IdentityCardWitness)->forceFill(['identity_card_form_id' => $form->id, 'position' => $position,
                'name' => 'شاهد محاكاة اسم كامل', 'document_type' => 'بطاقة شخصية', 'document_number' => '123456'.$position,
                'issued_place' => 'اختبار', 'issued_at' => '2020-01-01', 'phone' => '777123456', 'address' => 'عنوان اصطناعي'])->save();
        }
        (new IdentityCardAttestation)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => 1,
            'citizen_name' => 'مواطن اصطناعي', 'reference' => 'SIM-ATT-'.Str::ulid(), 'authority_type' => 'neighborhood',
            'recipient_name' => 'عاقل اصطناعي', 'status' => 'approved', 'created_by' => $owner->id, 'decided_by' => $staff->id, 'decided_at' => now(),
            'verification_source' => 'local_employee_confirmation', 'verified_at' => now()])->save();

        return [$owner, $staff, $service, $request, $form];
    }

    private function collector(): PostalCollector
    {
        $office = PostalOffice::create(['name' => 'مكتب اصطناعي', 'code' => Str::ulid(), 'area' => 'اختبار', 'address' => 'اختبار', 'status' => 'active', 'is_demo' => true]);

        return PostalCollector::create(['postal_office_id' => $office->id, 'name' => 'محصل اصطناعي', 'username' => 'synthetic-'.Str::random(8), 'password' => Str::random(32), 'is_active' => true, 'can_collect' => true]);
    }

    public function test_new_forms_are_versioned_and_historical_requests_are_unchanged(): void
    {
        [$owner, , , $request, $form] = $this->fixture();
        $this->assertTrue(IdentityJourney::applies($request));
        $form->forceFill(['journey_version' => null])->save();
        $this->assertFalse(IdentityJourney::applies($request->fresh()));
        $new = app(IdentityCardForms::class)->create($owner);
        $this->assertSame(1, $new->journey_version);
    }

    public function test_invoice_is_deferred_then_unique_and_amount_is_frozen(): void
    {
        [, , $service, $request] = $this->fixture();
        $this->assertNull(app(RequestCharges::class)->ensure($request));
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['status' => 'accepted']);
        $charge = app(RequestCharges::class)->ensure($request->fresh());
        $service->update(['fee' => '900.00']);
        $again = app(RequestCharges::class)->ensure($request->fresh());
        $this->assertSame($charge->id, $again->id);
        $this->assertSame('100.00', $again->amount);
        $this->assertNotEmpty($charge->invoice_number);
    }

    public function test_postal_receipt_is_required_and_replay_does_not_double_collect(): void
    {
        [$owner, $staff, , $request] = $this->fixture();
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['status' => 'accepted', 'assigned_to' => $staff->id]);
        $charge = app(RequestCharges::class)->ensure($request->fresh());
        $collector = $this->collector();
        $command = ['operation_token' => (string) Str::uuid()];
        $receipt = app(PostalCollections::class)->collect($collector, $charge, $command);
        $again = app(PostalCollections::class)->collect($collector, $charge, $command);
        $this->assertSame($receipt->id, $again->id);
        $this->assertTrue(app(IdentityJourney::class)->paid($request->fresh()));
        $this->assertDatabaseCount('postal_collections', 1);
        $this->assertDatabaseCount('manual_payments', 0);
        $this->assertTrue($owner->notifications()->exists());
        $this->assertTrue($staff->notifications()->exists());
        $this->expectException(ValidationException::class);
        app(PostalCollections::class)->collect($collector, $charge, ['operation_token' => (string) Str::uuid()]);
    }

    public function test_forged_paid_state_does_not_allow_an_appointment(): void
    {
        [, $staff, , $request] = $this->fixture();
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['status' => 'accepted']);
        $charge = app(RequestCharges::class)->ensure($request->fresh());
        $charge->forceFill(['status' => 'paid'])->save();
        $this->assertFalse(app(IdentityJourney::class)->paid($request->fresh()));
        $this->expectException(ValidationException::class);
        app(AppointmentBookings::class)->book($staff, $request->fresh(), 1);
    }

    private function acceptAndPay(): array
    {
        [$owner, $staff, $service, $request, $form] = $this->fixture();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $staff)->transitionTo(ServiceRequestStatus::Accepted, $staff);
        app(PostalCollections::class)->collect($this->collector(), $request->charge, ['operation_token' => (string) Str::uuid()]);

        return [$owner, $staff, $service, $request->fresh(), $form->fresh()];
    }

    private function visit(): array
    {
        [$owner, $staff, $service, $request, $form] = $this->acceptAndPay();
        $slot = (new AppointmentSlot)->forceFill(['government_service_id' => $service->id, 'center' => 'مركز اصطناعي', 'starts_at' => now()->addHour(), 'ends_at' => now()->addHours(2), 'capacity' => 2, 'is_active' => true, 'created_by' => $staff->id]);
        $slot->save();
        app(AppointmentBookings::class)->book($staff, $request, $slot->id);
        $this->travel(65)->minutes();
        app(FingerprintService::class)->arrive($staff, $form->fresh(), ['version' => $form->fresh()->version, 'evidence_reference' => 'فحص اصطناعي']);

        return [$owner, $staff, $service, $request->fresh(), $form->fresh()];
    }

    private function evidence($staff, $form): void
    {
        foreach (['blood_result', 'card_photo'] as $kind) {
            $row = app(VisitEvidence::class)->store($staff, $form->fresh(), $kind, UploadedFile::fake()->image($kind.'.png', 600, 600), $form->fresh()->version);
            app(VisitEvidence::class)->review($staff, $row, 'approved', null, $form->fresh()->version);
        }
    }

    public function test_preliminary_acceptance_creates_invoice_before_fingerprint_and_cannot_approve(): void
    {
        [, $staff, , $request] = $this->fixture();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $staff)->transitionTo(ServiceRequestStatus::Accepted, $staff);
        $this->assertSame('accepted', $request->status);
        $this->assertNotNull($request->charge->invoice_number);
        $this->assertSame(1, $request->reviewDecisions()->where('action', 'accept')->count());
        $this->expectException(ValidationException::class);
        $request->transitionTo(ServiceRequestStatus::Approved, $staff);
    }

    public function test_paid_staff_appointment_is_unique_and_citizen_cannot_book(): void
    {
        [$owner, $staff, $service, $request] = $this->acceptAndPay();
        $this->assertFalse($owner->can('operate', $request));
        $slot = (new AppointmentSlot)->forceFill(['government_service_id' => $service->id, 'center' => 'مركز اصطناعي', 'starts_at' => now()->addDay(), 'ends_at' => now()->addDay()->addHour(), 'capacity' => 2, 'is_active' => true, 'created_by' => $staff->id]);
        $slot->save();
        app(AppointmentBookings::class)->book($staff, $request, $slot->id);
        $this->assertSame(1, $request->appointments()->count());
        $this->assertTrue($owner->notifications()->get()->contains(fn ($notification) => str_contains($notification->data['body'] ?? '', 'فحص الدم')));
        $this->expectException(ValidationException::class);
        app(AppointmentBookings::class)->book($staff, $request, $slot->id);
    }

    public function test_visit_evidence_fingerprint_retry_final_approval_issuance_and_delivery_cycle(): void
    {
        [$owner, $staff, $service, $request, $form] = $this->visit();
        $this->assertNotNull($form->arrived_at);
        $this->evidence($staff, $form);
        $fingerprints = app(FingerprintService::class);
        $failed = $fingerprints->capture($staff, $form->fresh(), ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid(), 'simulate_failure' => true]);
        $this->assertSame('failure', $failed->result);
        $success = $fingerprints->capture($staff, $form->fresh(), ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid()]);
        $this->assertSame('success', $success->result);
        $this->assertSame(2, $form->operations()->count());
        $fingerprints->administer($staff, $form->fresh(), ['version' => $form->fresh()->version, 'decision_token' => (string) Str::uuid(), 'action' => 'approved']);
        $this->assertSame('approved', $request->fresh()->status);
        $document = app(IssueDocuments::class)->issue($staff, $request->fresh(), ['operation_token' => (string) Str::uuid()]);
        $this->assertSame('processing', $request->fresh()->status);
        $this->assertSame('delivery', $document->issuance_rules['completion_stage']);
        $this->assertStringStartsWith('INT-', $document->document_number);
        $this->assertStringContainsString('data:image/png;base64,', $document->copies()->first()->contents());
        $manager = app(ManageDocuments::class);
        $manager->choose($staff, $document->fresh(), ['operation_token' => (string) Str::uuid(), 'expected_version' => $document->fresh()->workflow_version, 'method' => 'pickup', 'center' => 'مركز اصطناعي']);
        try {
            $manager->status($staff, $document->fresh(), ['operation_token' => (string) Str::uuid(), 'expected_version' => $document->fresh()->workflow_version, 'status' => 'delivered', 'proof_reference' => 'TOO-EARLY']);
            $this->fail('Preparation must precede identity delivery');
        } catch (ValidationException) {
            $this->assertSame('ready', $document->fresh()->delivery->status);
        }
        try {
            $request->fresh()->transitionTo(ServiceRequestStatus::Completed, $staff);
            $this->fail('Document issuance does not complete the request');
        } catch (ValidationException) {
            $this->assertSame('processing', $request->fresh()->status);
        }
        $manager->status($staff, $document->fresh(), ['operation_token' => (string) Str::uuid(), 'expected_version' => $document->fresh()->workflow_version, 'status' => 'preparing']);
        $this->assertSame('processing', $request->fresh()->status);
        $manager->status($staff, $document->fresh(), ['operation_token' => (string) Str::uuid(), 'expected_version' => $document->fresh()->workflow_version, 'status' => 'delivered', 'proof_reference' => 'SYNTHETIC-RECEIPT']);
        $this->assertSame('completed', $request->fresh()->status);
        $this->assertDatabaseCount('medical_examinations', 0);
        $this->assertSame(2, $form->visitEvidence()->count());
    }

    public function test_arrival_without_paid_appointment_is_rejected(): void
    {
        [, $staff, , , $form] = $this->fixture();
        $form->forceFill(['status' => 'pending_fingerprint_attendance'])->save();
        $this->expectException(ValidationException::class);
        app(FingerprintService::class)->arrive($staff, $form, ['version' => $form->version, 'evidence_reference' => 'synthetic']);
    }

    public function test_visit_upload_and_file_are_permission_protected(): void
    {
        [, $staff, , $request, $form] = $this->visit();
        $row = app(VisitEvidence::class)->store($staff, $form, 'blood_result', UploadedFile::fake()->image('blood.png'), $form->version);
        $outsider = User::factory()->create(['role' => 'employee']);
        $this->actingAs($outsider)->get(route('identity-workflow.evidence.file', $row))->assertForbidden();
        $this->actingAs($staff)->get(route('identity-workflow.evidence.file', $row))->assertOk();
        $this->assertStringStartsWith('citizen-files/', $row->attachment->storage_path);
    }

    public function test_initial_return_opens_new_revision_and_keeps_reference_and_history(): void
    {
        [, $staff, , $request, $form] = $this->fixture();
        // Real submitted identity requests contain the full form data, not an empty generic-service payload.
        DB::table('citizen_service_requests')->where('id', $request->id)->update(['request_data' => (new EncryptedJsonArray)->set($request, 'request_data', $form->data, [])]);
        $reference = $request->reference;
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $staff);
        $request->transitionTo(ServiceRequestStatus::RequiresCorrection, $staff, 'استكمل البيانات الاصطناعية', ['deficiencies' => [['target_type' => 'other', 'target_key' => 'identity_form', 'description' => 'استكمال البيانات']]]);
        $this->assertSame($reference, $request->fresh()->reference);
        $this->assertTrue($form->fresh()->correctionIsOpen());
        $this->assertSame(2, $form->fresh()->revision);
        $this->assertSame(1, $request->deficiencies()->count());
        $this->assertSame(1, $request->reviewDecisions()->where('action', 'return')->count());
        $form = $form->fresh();
        // Re-submission still uses the protected identity form confirmation, with fresh attestation and private witness files.
        foreach ($form->witnesses as $witness) {
            $file = UploadedFile::fake()->image('witness.png', 600, 600);
            $path = 'witness-selfies/'.Str::uuid().'.png';
            Storage::disk('attachments')->put($path, file_get_contents($file->getRealPath()));
            $witness->forceFill(['selfie_path' => $path, 'selfie_sha256' => hash_file('sha256', $file->getRealPath()), 'selfie_size' => $file->getSize(), 'selfie_mime' => 'image/png'])->save();
        }
        $form->forceFill(['current_step' => 7, 'data' => array_replace($form->data, ['phone' => '777123457'])])->save();
        (new IdentityCardAttestation)->forceFill(['identity_card_form_id' => $form->id, 'form_revision' => $form->revision,
            'citizen_name' => 'مواطن اصطناعي', 'reference' => 'SIM-ATT-'.Str::ulid(), 'authority_type' => 'neighborhood', 'recipient_name' => 'عاقل اصطناعي',
            'status' => 'approved', 'created_by' => $form->user_id, 'decided_by' => $staff->id, 'decided_at' => now(), 'verified_at' => now(), 'verification_source' => 'local_employee_confirmation'])->save();
        Process::fake(['*photo_quality.py*' => Process::result(output: '{"version":1,"status":"completed","accepted":true,"errors":[]}')]);
        $owner = $form->citizen;
        $forms = app(IdentityCardForms::class);
        $this->actingAs($owner);
        $form = $form->fresh();
        $forms->submit($owner, $form, $form->version, ['expires_at' => now()->addMinutes(5)->timestamp, 'fingerprint' => $forms->reviewFingerprint($owner, $form)]);
        $this->assertSame('submitted', $request->fresh()->status);
        $this->assertSame($reference, $request->fresh()->reference);
        $this->assertSame('awaiting_review', $request->deficiencies()->first()->status);
        $this->assertSame(1, $request->reviewDecisions()->where('action', 'resubmit')->count());
        $this->assertSame('777123457', $request->fresh()->request_data['phone']);
        $this->assertSame('777123456', $request->fresh()->original_request_data['phone']);
    }

    public function test_postal_audit_failure_rolls_back_receipt_payment_and_notifications(): void
    {
        [$owner, $staff, , $request] = $this->fixture();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $staff)->transitionTo(ServiceRequestStatus::Accepted, $staff);
        $collector = $this->collector();
        $before = $owner->notifications()->count();
        $this->mock(AuditTrail::class)->shouldReceive('record')->andThrow(new \RuntimeException('synthetic audit failure'));
        try {
            app(PostalCollections::class)->collect($collector, $request->charge, ['operation_token' => (string) Str::uuid()]);
            $this->fail('Collection must roll back');
        } catch (\RuntimeException $e) {
            $this->assertSame('synthetic audit failure', $e->getMessage());
        }
        $this->assertDatabaseCount('postal_collections', 0);
        $this->assertSame('due', $request->charge->fresh()->status);
        $this->assertSame($before, $owner->notifications()->count());
    }

    public function test_manual_payment_does_not_substitute_for_postal_collection(): void
    {
        [, $staff, , $request] = $this->fixture();
        $this->expectException(ValidationException::class);
        app(RequestCharges::class)->record($staff, $request, ['amount' => '100.00', 'currency' => 'YER']);
    }

    public function test_fingerprint_cannot_precede_blood_and_card_photo_review(): void
    {
        [, $staff, , , $form] = $this->visit();
        $this->expectException(ValidationException::class);
        app(FingerprintService::class)->capture($staff, $form, ['version' => $form->version, 'operation_token' => (string) Str::uuid()]);
    }

    public function test_rejected_evidence_blocks_final_readiness_and_can_be_replaced(): void
    {
        [, $staff, , , $form] = $this->visit();
        $service = app(VisitEvidence::class);
        $row = $service->store($staff, $form, 'blood_result', UploadedFile::fake()->image('blood.png'), $form->version);
        $service->review($staff, $row, 'rejected', 'نتيجة غير مقروءة', $form->fresh()->version);
        $replacement = $service->store($staff, $form->fresh(), 'blood_result', UploadedFile::fake()->image('replacement.png'), $form->fresh()->version);
        $this->assertSame(2, $form->visitEvidence()->count());
        $this->assertTrue($row->attachment->intact());
        $this->assertSame($replacement->id, app(IdentityJourney::class)->evidence($form, 'blood_result')->id);
        $this->assertNotEmpty(app(IdentityJourney::class)->evidenceReasons($form));
    }

    public function test_separate_approver_is_still_required_with_comprehensive_permissions(): void
    {
        [, $staff, $service, $request] = $this->acceptAndPay();
        $service->update(['require_separate_approver' => true]);
        $this->assertFalse($staff->can('transition', [$request->fresh(), ServiceRequestStatus::Approved]));
        $this->assertSame('employee', $staff->fresh()->role);
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_transaction_file_and_citizen_invoice_render_without_exposing_private_storage(): void
    {
        [$owner, $staff, , $request] = $this->acceptAndPay();
        $this->actingAs($staff)->get(route('employee.applications.review', $request->id))->assertOk()->assertSee('ملف معاملة الهوية')->assertSee($request->charge->invoice_number)->assertDontSee('citizen-files/');
        $this->actingAs($owner)->get(route('citizen.applications.show', $request->id))->assertOk()->assertSee($request->charge->invoice_number)->assertDontSee('citizen-files/');
    }

    public function test_disabled_collector_cannot_confirm_cash(): void
    {
        [, , , $request] = $this->acceptAndPay();
        $collector = $this->collector();
        $collector->forceFill(['is_active' => false])->save();
        $this->expectException(HttpException::class);
        app(PostalCollections::class)->collect($collector, $request->charge, ['operation_token' => (string) Str::uuid()]);
    }

    public function test_admin_can_grant_every_existing_service_permission_without_changing_employee_role(): void
    {
        [, $staff, $service] = $this->fixture();
        $admin = User::factory()->create(['role' => 'admin']);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $fields = ['can_review', 'can_approve', 'can_assign', 'can_issue', 'can_deliver', 'can_verify', 'can_activate'];
        $this->actingAs($admin)->post(route('admin.review-permissions.save', $staff), ['government_service_id' => $service->id] + array_fill_keys($fields, true))->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('employee', $staff->fresh()->role);
        foreach ($fields as $field) {
            $this->assertTrue($staff->fresh()->canReviewService($service->id, $field));
        }
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_issuance_and_completion_are_blocked_before_their_proof(): void
    {
        [, $staff, , $request] = $this->acceptAndPay();
        try {
            app(IssueDocuments::class)->issue($staff, $request, ['operation_token' => (string) Str::uuid()]);
            $this->fail('Cannot issue before approval');
        } catch (ValidationException) {
            $this->assertDatabaseCount('citizen_documents', 0);
        }
        $this->expectException(ValidationException::class);
        app(IssuanceLifecycle::class)->assertTransition($request, ServiceRequestStatus::Completed);
    }

    public function test_approved_unissued_request_can_be_corrected_without_losing_payment(): void
    {
        [, $staff, , $request, $form] = $this->visit();
        $this->evidence($staff, $form);
        $workflow = app(FingerprintService::class);
        $workflow->capture($staff, $form->fresh(), ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid()]);
        $workflow->administer($staff, $form->fresh(), ['version' => $form->fresh()->version, 'decision_token' => (string) Str::uuid(), 'action' => 'approved']);
        $receipt = $request->charge->postalCollection->id;
        $workflow->administer($staff, $form->fresh(), ['version' => $form->fresh()->version, 'decision_token' => (string) Str::uuid(), 'action' => 'correction_required', 'citizen_reason' => 'تصحيح اصطناعي قبل الإصدار']);
        $this->assertSame('requires_correction', $request->fresh()->status);
        $this->assertSame(2, $form->fresh()->revision);
        $this->assertTrue($form->fresh()->correctionIsOpen());
        $this->assertNull($workflow->successfulOperation($form->fresh()));
        $this->assertSame($receipt, $request->charge->postalCollection->id);
        $this->assertTrue(app(IdentityJourney::class)->paid($request->fresh()));
        $this->assertDatabaseCount('postal_collections', 1);
    }

    public function test_postal_workspace_collects_only_the_invoice_and_shows_the_existing_receipt(): void
    {
        [$owner, $staff, , $request] = $this->fixture();
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $staff)->transitionTo(ServiceRequestStatus::Accepted, $staff);
        $collector = $this->collector()->fresh();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($collector, 'postal')->withSession(['postal_auth_version' => $collector->auth_version]);
        $this->get(route('postal.collections.show', ['invoice' => $request->charge->invoice_number]))->assertOk()->assertSee($request->charge->invoice_number)->assertDontSee($owner->name);
        $command = ['invoice' => $request->charge->invoice_number, 'operation_token' => (string) Str::uuid(), 'cash_received' => true];
        $this->post(route('postal.collections.store'), $command)->assertSessionHasNoErrors()->assertRedirect();
        $this->post(route('postal.collections.store'), $command)->assertSessionHasNoErrors()->assertRedirect();
        $this->assertDatabaseCount('postal_collections', 1);
        $receipt = $request->charge->fresh()->postalCollection;
        $this->get(route('postal.collections.show', ['invoice' => $request->charge->invoice_number]))->assertOk()->assertSee($receipt->receipt_reference)->assertDontSee('name="cash_received"', false);
    }

    public function test_generic_http_submission_cannot_bypass_the_identity_form(): void
    {
        [$owner, , $service] = $this->fixture();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        $this->actingAs($owner)->post('/citizen/services/'.$service->id.'/preview')->assertRedirect(route('citizen.identity-card.index'));
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => (string) Str::uuid(), 'declaration' => true])->assertRedirect(route('citizen.identity-card.index'));
        $this->assertSame(1, $owner->serviceRequests()->count());
    }

    public function test_copy_generation_failure_keeps_approval_and_records_failure_and_notice(): void
    {
        [$owner, $staff, , $request, $form] = $this->visit();
        $this->evidence($staff, $form);
        $workflow = app(FingerprintService::class);
        $workflow->capture($staff, $form->fresh(), ['version' => $form->fresh()->version, 'operation_token' => (string) Str::uuid()]);
        $workflow->administer($staff, $form->fresh(), ['version' => $form->fresh()->version, 'decision_token' => (string) Str::uuid(), 'action' => 'approved']);
        $this->mock(ElectronicCopies::class)->shouldReceive('generate')->andThrow(new \RuntimeException('synthetic storage failure'));
        $this->app->make(ElectronicCopies::class)->shouldReceive('discard')->andReturnNull();
        try {
            app(IssueDocuments::class)->issue($staff, $request->fresh(), ['operation_token' => (string) Str::uuid()]);
            $this->fail('Failed generation must not issue');
        } catch (ValidationException) {
            $this->assertSame('approved', $request->fresh()->status);
        }
        $this->assertDatabaseCount('citizen_documents', 0);
        $this->assertDatabaseHas('audit_events', ['event' => 'identity_issue_failed', 'subject_id' => (string) $request->id, 'result' => 'failed']);
        $this->assertTrue($owner->notifications()->get()->contains(fn ($n) => ($n->data['title'] ?? '') === 'تعذر إصدار الوثيقة'));
    }
}
