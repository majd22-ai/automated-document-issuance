<?php

namespace Tests\Feature;

use App\Enums\RequirementState;
use App\Models\CitizenAttachment;
use App\Models\GovernmentService;
use App\Models\User;
use App\Services\ServiceEligibility;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\DatabaseTransactionsManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CitizenAttachmentsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $connection = DB::connection();
        if ($connection->getDatabaseName() !== ':memory:' && ! str_ends_with($connection->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated testing database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $manager = new DatabaseTransactionsManager([$connection->getName()]);
        $this->app->instance('db.transactions', $manager);
        $connection->setTransactionManager($manager);
        $connection->beginTransaction();
        $this->beforeApplicationDestroyed(function () use ($connection) {
            $connection->rollBack();
            $connection->disconnect();
        });
        Storage::fake('attachments');
    }

    private function user(string $role = 'citizen'): User
    {
        $user = User::factory()->create();
        $user->forceFill(['username' => 'FILE-'.$user->id, 'role' => $role])->save();

        return $user;
    }

    private function service(User $citizen, bool $review = false): GovernmentService
    {
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST policy', 'required_documents' => ['صورة شخصية'], 'verification_rules' => ['documents' => [['code' => 'photo', 'name' => 'صورة شخصية', 'kind' => 'photo', 'allowed_mime_types' => ['image/png', 'image/jpeg'], 'requires_review' => $review]]]]);
        $service->assessments()->create(['user_id' => $citizen->id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'verification_reference' => 'TEST only', 'reviewed_by' => $this->user('admin')->id, 'reviewed_at' => now()]);

        return $service;
    }

    private function upload(User $citizen, GovernmentService $service, array $extra = [])
    {
        return $this->actingAs($citizen)->post('/citizen/services/'.$service->id.'/attachments', array_merge(['document_code' => 'photo', 'file' => UploadedFile::fake()->image('portrait.png', 80, 100)], $extra));
    }

    public function test_valid_upload_is_private_scoped_and_has_protected_preview(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service)->assertRedirect();
        $file = CitizenAttachment::firstOrFail();
        $this->assertSame($citizen->id, $file->user_id);
        $this->assertSame($service->id, $file->government_service_id);
        $this->assertNull($file->citizen_service_request_id);
        $this->assertSame('uploaded', $file->review_status);
        Storage::disk('attachments')->assertExists($file->storage_path);
        $this->assertStringNotContainsString('portrait', $file->storage_path);
        $this->assertArrayNotHasKey('storage_path', $file->toArray());
        $this->get('/attachments/'.$file->id.'/preview')->assertOk()->assertHeader('Content-Type', 'image/png')->assertHeader('Cache-Control', 'no-store, private')->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->get('/attachments/'.$file->id.'/download')->assertOk();
        $this->get('/citizen/services/'.$service->id.'/attachments')->assertOk()->assertSee('portrait.png');
        $other = $this->user();
        $this->actingAs($other)->get('/attachments/'.$file->id.'/preview')->assertForbidden();
        $this->actingAs($this->user('admin'))->get('/attachments/'.$file->id.'/download')->assertForbidden();
        $this->actingAs($this->user('employee'))->get('/attachments/'.$file->id.'/preview')->assertOk();
    }

    public function test_bad_extension_type_size_empty_corrupt_or_multiple_files_are_rejected(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $realPng = UploadedFile::fake()->image('real.png');
        foreach ([UploadedFile::fake()->createWithContent('evil.php', '<?php echo 1;'), UploadedFile::fake()->createWithContent('broken.png', 'not an image'), UploadedFile::fake()->createWithContent('empty.png', ''), UploadedFile::fake()->createWithContent('spoof.jpg', file_get_contents($realPng->path()))] as $file) {
            $this->upload($citizen, $service, ['file' => $file])->assertSessionHasErrors('file');
        }
        config(['attachments.photo_max_kb' => 1]);
        $this->upload($citizen, $service, ['file' => UploadedFile::fake()->image('big.png')->size(100)])->assertSessionHasErrors('file');
        $this->upload($citizen, $service, ['file' => [UploadedFile::fake()->image('a.png'), UploadedFile::fake()->image('b.png')]])->assertSessionHasErrors('file');
        $this->assertSame(0, CitizenAttachment::count());
    }

    public function test_guest_and_inactive_citizen_cannot_upload_or_read_files(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->post('/citizen/services/'.$service->id.'/attachments')->assertRedirect('/login');
        $this->get('/attachments/1/preview')->assertRedirect('/login');
        $citizen->forceFill(['is_active' => false])->save();
        $this->upload($citizen, $service)->assertRedirect('/login');
        $this->assertSame(0, CitizenAttachment::count());
    }

    public function test_spoofed_owner_status_path_or_unknown_document_cannot_be_saved(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        foreach (['user_id' => 42, 'review_status' => 'approved', 'storage_path' => '../../secret', 'request_id' => 42] as $key => $value) {
            $this->upload($citizen, $service, [$key => $value])->assertSessionHasErrors($key);
        }
        $this->upload($citizen, $service, ['document_code' => 'other'])->assertSessionHasErrors('document_code');
        $this->assertSame(0, CitizenAttachment::count());
    }

    public function test_pending_upload_requires_employee_review_and_citizen_cannot_approve_it(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, true);
        $this->upload($citizen, $service)->assertRedirect();
        $file = CitizenAttachment::firstOrFail();
        $this->assertSame(RequirementState::NeedsReview, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
        $this->patch('/employee/attachments/'.$file->id.'/review', ['review_status' => 'approved'])->assertForbidden();
        $employee = $this->user('employee');
        $this->actingAs($employee)->patch('/employee/attachments/'.$file->id.'/review', ['review_status' => 'approved', 'expected_version' => $file->version_token])->assertRedirect();
        $this->assertSame($employee->id, $file->fresh()->reviewed_by);
        $this->assertTrue(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
        $this->patch('/employee/attachments/'.$file->id.'/review', ['review_status' => 'rejected', 'public_reason' => 'الصورة غير واضحة', 'expected_version' => $file->fresh()->version_token])->assertRedirect();
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
    }

    public function test_submission_binds_own_files_and_tampered_storage_blocks_final_confirmation(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $response = $this->post('/citizen/services/'.$service->id.'/preview');
        $response->assertRedirect();
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        Storage::disk('attachments')->put($file->storage_path, 'changed bytes');
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertSessionHasErrors();
        $this->assertSame(0, $citizen->serviceRequests()->count());
        $this->upload($citizen, $service);
        $response = $this->post('/citizen/services/'.$service->id.'/preview');
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertRedirect();
        $application = $citizen->serviceRequests()->firstOrFail();
        $this->assertSame($application->id, $file->fresh()->citizen_service_request_id);
        $this->assertSame(1, $application->attachments()->count());
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
    }

    public function test_replacement_validates_first_resets_review_and_removes_old_bytes_after_success(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, true);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $old = $file->storage_path;
        $this->upload($citizen, $service, ['file' => UploadedFile::fake()->createWithContent('bad.png', 'broken')])->assertSessionHasErrors('file');
        Storage::disk('attachments')->assertExists($old);
        $this->assertSame($old, $file->fresh()->storage_path);
        $this->upload($citizen, $service)->assertRedirect();
        Storage::disk('attachments')->assertMissing($old);
        $this->assertSame(1, CitizenAttachment::count());
        $this->assertSame('pending', $file->fresh()->review_status);
        $this->assertNull($file->fresh()->reviewed_by);
        $this->delete('/citizen/attachments/'.$file->id)->assertSessionHasErrors('attachment');
        Storage::disk('attachments')->assertExists($file->fresh()->storage_path);
    }

    public function test_foreign_request_and_closed_request_cannot_receive_or_replace_files(): void
    {
        $citizen = $this->user();
        $other = $this->user();
        $service = $this->service($citizen);
        $foreign = $other->serviceRequests()->create(['reference' => 'FILE-FOREIGN', 'service_code' => $service->code, 'status' => 'requires_correction']);
        $this->actingAs($citizen)->post('/citizen/applications/'.$foreign->id.'/attachments', ['document_code' => 'photo', 'file' => UploadedFile::fake()->image('a.png')])->assertNotFound();
        $own = $citizen->serviceRequests()->create(['reference' => 'FILE-CLOSED', 'service_code' => $service->code, 'status' => 'approved']);
        $this->post('/citizen/applications/'.$own->id.'/attachments', ['document_code' => 'photo', 'file' => UploadedFile::fake()->image('a.png')])->assertForbidden();
        $this->assertSame(0, CitizenAttachment::count());
    }

    public function test_stale_review_cannot_approve_an_unseen_replacement(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen, true);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $version = $file->version_token;
        $this->upload($citizen, $service);
        $this->actingAs($this->user('employee'))->patch('/employee/attachments/'.$file->id.'/review', ['review_status' => 'approved', 'expected_version' => $version])->assertSessionHasErrors('attachment');
        $this->assertSame('pending', $file->fresh()->review_status);
    }

    public function test_explicit_rejection_blocks_even_a_service_without_mandatory_review(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $this->actingAs($this->user('employee'))->patch('/employee/attachments/'.$file->id.'/review', ['review_status' => 'replacement_required', 'public_reason' => 'نسخة غير واضحة', 'expected_version' => $file->version_token])->assertRedirect();
        $this->assertFalse(app(ServiceEligibility::class)->evaluate($citizen, $service)->eligible());
    }

    public function test_storage_write_failure_preserves_previous_file_and_its_record(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $disk = Storage::disk('attachments');
        Storage::shouldReceive('disk')->with('attachments')->andReturn($failing = \Mockery::mock(Filesystem::class));
        $failing->shouldReceive('put')->once()->andThrow(new \RuntimeException('TEST write failure'));
        $failing->shouldReceive('delete')->once()->andReturn(true);
        $this->upload($citizen, $service)->assertSessionHasErrors('file');
        $disk->assertExists($file->storage_path);
        $this->assertSame($file->storage_path, $file->fresh()->storage_path);
    }

    public function test_optional_file_can_be_removed_but_draft_and_request_files_are_isolated(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $rules = $service->verification_rules;
        $rules['documents'][0]['optional'] = true;
        $this->actingAs($this->user('admin'))->patch('/admin/services/'.$service->id, ['name' => $service->name, 'required_documents' => [], 'verification_rules' => $rules])->assertSessionHasNoErrors()->assertRedirect();
        $service->refresh();
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $this->delete('/citizen/attachments/'.$file->id)->assertRedirect();
        $this->assertNull($file->fresh());
        Storage::disk('attachments')->assertMissing($file->storage_path);
    }

    public function test_missing_and_pdf_without_configured_parser_cannot_pass(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
        $rules = $service->verification_rules;
        $rules['documents'][0] = ['code' => 'photo', 'name' => 'صورة شخصية', 'kind' => 'document', 'allowed_mime_types' => ['application/pdf'], 'requires_review' => true];
        $service->update(['verification_rules' => $rules]);
        config(['attachments.pdfinfo_binary' => null]);
        $this->upload($citizen, $service, ['file' => UploadedFile::fake()->createWithContent('file.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n%%EOF")])->assertSessionHasErrors('file');
        $this->assertSame(0, CitizenAttachment::count());
    }

    public function test_correction_files_are_request_scoped_and_foreign_deletion_is_forbidden(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $application = $citizen->serviceRequests()->create(['reference' => 'CORRECT-FILES', 'service_code' => $service->code, 'status' => 'requires_correction']);
        $this->actingAs($citizen)->post('/citizen/applications/'.$application->id.'/attachments', ['document_code' => 'photo', 'file' => UploadedFile::fake()->image('corrected.png')])->assertRedirect();
        $file = $application->attachments()->firstOrFail();
        $this->assertSame($citizen->id, $file->user_id);
        $this->assertSame(RequirementState::Met, app(ServiceEligibility::class)->evaluate($citizen, $service, $application)->checks['attachment.photo']->state);
        $this->assertSame(RequirementState::Unmet, app(ServiceEligibility::class)->evaluate($citizen, $service)->checks['attachment.photo']->state);
        $this->assertNull($application->fresh()->requirements_result);
        $this->actingAs($this->user())->delete('/citizen/attachments/'.$file->id)->assertForbidden();
        Storage::disk('attachments')->assertExists($file->storage_path);
    }

    public function test_attachment_replacement_invalidates_preview_even_when_both_files_are_valid(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $response = $this->post('/citizen/services/'.$service->id.'/preview');
        $token = basename(parse_url($response->headers->get('Location'), PHP_URL_PATH));
        $this->upload($citizen, $service, ['file' => UploadedFile::fake()->image('new.png', 90, 110)]);
        $this->post('/citizen/services/'.$service->id.'/submit', ['preview_token' => $token, 'declaration' => true])->assertSessionHasErrors('preview_token');
        $this->assertSame(0, $citizen->serviceRequests()->count());
    }

    public function test_failed_after_commit_audit_does_not_delete_committed_replacement(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        Log::shouldReceive('info')->andThrow(new \RuntimeException('TEST log destination unavailable'));
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        $this->upload($citizen, $service)->assertRedirect()->assertSessionHasNoErrors();
        Storage::disk('attachments')->assertExists($file->fresh()->storage_path);
    }

    public function test_retained_attachment_table_can_be_migrated_again_without_losing_files(): void
    {
        $citizen = $this->user();
        $service = $this->service($citizen);
        $this->upload($citizen, $service);
        $file = CitizenAttachment::firstOrFail();
        $migration = require database_path('migrations/2026_10_26_000001_create_citizen_attachments.php');
        $migration->down();
        $migration->up();
        $this->assertTrue(\Illuminate\Support\Facades\Schema::hasIndex('citizen_attachments', 'attachments_owner_context'));
        $this->assertSame($citizen->id, $file->fresh()->user_id);
        Storage::disk('attachments')->assertExists($file->storage_path);
    }
}
