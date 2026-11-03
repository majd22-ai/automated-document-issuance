<?php

namespace Tests\Feature;

use App\Enums\ServiceRequestStatus;
use App\Models\BirthCertificate;
use App\Models\CitizenAttachment;
use App\Models\CitizenDocument;
use App\Models\CitizenServiceRequest;
use App\Models\GovernmentService;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Issuance\IssueDocuments;
use App\Services\Review\ReviewSnapshot;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class BirthCertificateServiceTest extends TestCase
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
        Storage::fake('documents');
        Storage::fake('attachments');
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['role' => $role, 'username' => 'BIRTH-'.$user->id])->save();

        return $user;
    }

    private function payload(): array
    {
        return [
            'child_name' => 'سارة أحمد',
            'child_gender' => 'أنثى',
            'birth_date' => '2025-01-20',
            'birth_place' => 'مستشفى المدينة',
            'father_name' => 'أحمد محمد',
            'father_national_id' => 'FATHER-TEST-1',
            'mother_name' => 'منى علي',
            'mother_national_id' => 'MOTHER-TEST-1',
            'birth_record_number' => 'RECORD-TEST-1',
            'hospital_name' => 'مستشفى المدينة',
            'birth_record_date' => '2025-01-21',
        ];
    }

    private function service(): GovernmentService
    {
        $service = GovernmentService::where('code', 'birth-certificate-first-issue')->firstOrFail();
        $service->update([
            'is_active' => true,
            'rules_approved' => true,
            'source_reference' => 'TEST ONLY: approved demo configuration',
            'issuance_rules' => [
                'enabled' => true, 'source_reference' => 'TEST ONLY', 'expiry' => ['required' => false],
                'number_prefix' => 'TEST', 'include_year' => true, 'electronic_copy' => 'internal_html',
                'citizen_download' => true, 'delivery' => ['methods' => ['pickup'], 'centers' => ['TEST only']],
                'completion_stage' => 'issuance',
            ],
        ]);

        return $service->fresh();
    }

    public function test_citizen_can_save_a_private_draft_and_submit_a_valid_birth_request(): void
    {
        $citizen = $this->user();
        $service = $this->service();
        $admin = $this->user('admin');
        $service->update(['required_documents' => [], 'verification_rules' => ['documents' => [], 'restriction_source' => 'manual']]);
        $service->assessments()->create([
            'user_id' => $citizen->id, 'rules_version' => $service->fresh()->rules_version,
            'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST ONLY',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
        $this->actingAs($citizen)->post('/citizen/birth-certificates/drafts', ['data' => $this->payload()])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('birth_certificate_drafts', ['user_id' => $citizen->id, 'government_service_id' => $service->id]);
        $previewResponse = $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview', ['data' => $this->payload()])
            ->assertRedirect();
        $previews = $previewResponse->getSession()->get('request_previews');
        $this->assertNotEmpty($previews);
        $token = array_key_first($previews);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => '1'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('citizen_service_requests', ['user_id' => $citizen->id, 'government_service_id' => $service->id, 'status' => 'submitted']);
        $this->assertDatabaseMissing('birth_certificate_drafts', ['user_id' => $citizen->id, 'government_service_id' => $service->id]);
        $request = $citizen->serviceRequests()->firstOrFail();
        $this->assertSame($this->payload(), $request->request_data);
        $this->assertStringNotContainsString('FATHER-TEST-1', $request->getRawOriginal('request_data'));
    }

    public function test_incomplete_or_invalid_birth_data_is_rejected(): void
    {
        $citizen = $this->user();
        $service = $this->service();
        $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/preview', ['data' => ['child_name' => 'ناقص']])
            ->assertSessionHasErrors(['data.child_gender', 'data.birth_date', 'data.father_name', 'data.mother_name']);
        $invalid = $this->payload();
        $invalid['birth_date'] = now()->addDay()->toDateString();
        $this->post('/citizen/services/'.$service->id.'/preview', ['data' => $invalid])
            ->assertSessionHasErrors('data.birth_date');
    }

    public function test_legacy_plain_json_request_data_remains_readable(): void
    {
        $citizen = $this->user();
        $request = $citizen->serviceRequests()->create([
            'reference' => 'LEGACY-'.Str::ulid(), 'service_code' => 'legacy-service', 'status' => 'submitted',
        ]);
        DB::table('citizen_service_requests')->where('id', $request->id)
            ->update(['request_data' => json_encode(['legacy_field' => 'preserved'], JSON_THROW_ON_ERROR)]);

        $this->assertSame(['legacy_field' => 'preserved'], CitizenServiceRequest::findOrFail($request->id)->request_data);
    }

    public function test_birth_evidence_upload_uses_private_validated_attachment_storage(): void
    {
        $citizen = $this->user();
        $service = $this->service();
        $file = function_exists('imagecreatefrompng')
            ? UploadedFile::fake()->image('birth.png', 100, 100)
            : UploadedFile::fake()->createWithContent('birth.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/r3sAAAAASUVORK5CYII=', true));
        $response = $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/attachments', [
            'document_code' => 'birth_notice', 'file' => $file,
        ])->assertRedirect();
        if (! function_exists('imagecreatefrompng')) {
            $response->assertSessionHasErrors('file');
            $this->assertSame(0, CitizenAttachment::where('user_id', $citizen->id)->count());

            return;
        }
        $response->assertSessionHasNoErrors();
        $attachment = CitizenAttachment::where('user_id', $citizen->id)->firstOrFail();
        $this->assertSame('pending', $attachment->review_status);
        $this->assertTrue($attachment->intact());
        $this->assertStringStartsWith('citizen-files/', $attachment->storage_path);
        $this->assertArrayNotHasKey('storage_path', $attachment->toArray());
    }

    public function test_reviewed_request_issues_one_pdf_with_private_qr_verification_and_owner_only_download(): void
    {
        [$citizen, $employee, $service, $request] = $this->approvedRequest();
        $command = [
            'operation_token' => (string) Str::uuid(),
            'expected_version' => $request->review_version,
            'expected_rules_version' => $service->rules_version,
            'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request),
        ];
        $this->actingAs($employee)->post('/employee/issuance/'.$request->id, $command)->assertRedirect()->assertSessionHasNoErrors();
        $certificate = BirthCertificate::where('citizen_service_request_id', $request->id)->firstOrFail();
        $document = CitizenDocument::findOrFail($certificate->citizen_document_id);
        $this->assertStringStartsWith('BC-', $certificate->certificate_number);
        $pdf = $certificate->pdfContents();
        $this->assertNotNull($pdf);
        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->actingAs($citizen)->get('/citizen/documents/'.$document->id.'/download')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->actingAs($this->user())->get('/citizen/documents/'.$document->id.'/download')->assertNotFound();
        $this->actingAs($this->user('employee'))->get('/employee/documents/'.$document->id.'/download')->assertForbidden();
        $this->get('/verify/birth-certificate/'.$certificate->verification_token)
            ->assertOk()->assertSee($certificate->certificate_number)->assertDontSee('سارة أحمد')->assertDontSee('FATHER-TEST-1');
        $this->actingAs($employee)->post('/employee/issuance/'.$request->id, [
            ...$command, 'operation_token' => (string) Str::uuid(),
            'expected_version' => $request->fresh()->review_version,
            'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request->fresh()),
        ])->assertSessionHasErrors();
        $this->assertSame(1, BirthCertificate::where('citizen_service_request_id', $request->id)->count());
    }

    public function test_private_certificate_pdf_integrity_and_invalid_verification_token_are_enforced(): void
    {
        [$citizen, $employee, $service, $request] = $this->approvedRequest();
        app(IssueDocuments::class)->issue($employee, $request, [
            'operation_token' => (string) Str::uuid(),
            'expected_version' => $request->review_version,
            'expected_rules_version' => $service->rules_version,
            'expected_fingerprint' => app(ReviewSnapshot::class)->fingerprint($request),
        ]);
        $certificate = BirthCertificate::where('citizen_service_request_id', $request->id)->firstOrFail();
        Storage::disk('documents')->put($certificate->pdf_path, 'tampered');
        $this->actingAs($citizen)->get('/citizen/documents/'.$certificate->citizen_document_id.'/download')->assertStatus(409);
        $this->get('/verify/birth-certificate/'.str_repeat('a', 64))->assertNotFound();
    }

    private function approvedRequest(): array
    {
        $citizen = $this->user();
        $employee = $this->user('employee');
        $admin = $this->user('admin');
        $service = $this->service();
        $service->assessments()->create([
            'user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true,
            'conditions_met' => true, 'verification_reference' => 'TEST ONLY',
            'reviewed_by' => $admin->id, 'reviewed_at' => now(),
        ]);
        (new ServiceReviewPermission)->forceFill([
            'user_id' => $employee->id, 'government_service_id' => $service->id,
            'can_review' => true, 'can_approve' => true, 'can_issue' => true, 'granted_by' => $admin->id,
        ])->save();
        $request = $citizen->serviceRequests()->create([
            'reference' => 'BIRTH-'.Str::ulid(), 'service_code' => $service->code, 'status' => 'submitted',
        ]);
        $request->request_data = $this->payload();
        $request->submitted_at = now();
        $request->save();
        foreach ([
            'birth_notice' => ['إشعار أو بلاغ واقعة الميلاد', 'notice'],
            'father_identity' => ['إثبات هوية الأب', 'father'],
            'mother_identity' => ['إثبات هوية الأم', 'mother'],
        ] as $code => [$name, $suffix]) {
            $path = 'citizen-files/'.Str::uuid().'.png';
            $bytes = 'TEST-'.$suffix;
            Storage::disk('attachments')->put($path, $bytes);
            (new CitizenAttachment)->forceFill([
                'user_id' => $citizen->id, 'government_service_id' => $service->id,
                'citizen_service_request_id' => $request->id, 'document_code' => $code, 'document_name' => $name,
                'kind' => 'document', 'rules_version' => $service->rules_version, 'storage_path' => $path, 'display_name' => $name.'.png',
                'size' => strlen($bytes), 'sha256' => hash('sha256', $bytes), 'mime_type' => 'image/png',
                'review_status' => 'approved', 'reviewed_by' => $employee->id, 'reviewed_at' => now(),
                'slot_key' => hash('sha256', $citizen->id.':'.$service->id.':'.$request->id.':'.$code),
                'version_token' => (string) Str::uuid(),
            ])->save();
        }
        $request = $request->transitionTo(ServiceRequestStatus::UnderReview, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::Accepted, $employee);
        $request = $request->transitionTo(ServiceRequestStatus::Approved, $employee);

        return [$citizen, $employee, $service, $request->fresh()];
    }
}
