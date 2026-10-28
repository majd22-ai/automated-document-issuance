<?php

namespace Tests\Feature;

use App\Models\IdentityCardForm;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Attachments\ManageAttachments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentityCardWizardTest extends TestCase
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
        Storage::fake('attachments');
    }

    private function draft(): IdentityCardForm
    {
        $citizen = User::factory()->create();
        $citizen->forceFill(['username' => 'WIZARD-'.$citizen->id])->save();
        $this->actingAs($citizen)->get('/citizen/identity-card')->assertOk();
        $this->post('/citizen/identity-card')->assertRedirect();

        return IdentityCardForm::sole();
    }

    private function data(int $step): array
    {
        $data = [];
        foreach (config('identity_card.fields.'.$step) as $key => $field) {
            $data[$key] = isset($field['options']) ? array_key_first($field['options']) : (($field['type'] ?? '') === 'date' ? '2000-01-01' : 'بيان اختبار');
            if (in_array($key, ['phone', 'alternate_phone'])) {
                $data[$key] = '777123456';
            }
        }

        return $data;
    }

    private function save(IdentityCardForm $form, int $step, array $payload, string $action = 'next')
    {
        return $this->post('/citizen/identity-card/'.$form->id, $payload + ['step' => $step, 'action' => $action, 'version' => $form->fresh()->version]);
    }

    private function ready(IdentityCardForm $form): void
    {
        for ($step = 1; $step <= 3; $step++) {
            $this->save($form, $step, ['data' => $this->data($step)])->assertSessionHasNoErrors()->assertRedirect();
            $this->get('/citizen/identity-card/'.$form->id.'?step='.$step)->assertOk();
        }
        $this->save($form, 4, ['photo' => UploadedFile::fake()->image('portrait.jpg', 600, 600)])->assertSessionHasNoErrors()->assertRedirect();
        $witnesses = [];
        for ($i = 0; $i < 2; $i++) {
            $witnesses[] = ['name' => 'شاهد اختبار '.$i, 'document_type' => 'بطاقة شخصية', 'document_number' => '100000'.$i,
                'issued_place' => 'صنعاء', 'issued_at' => '2020-01-01', 'employer' => '', 'work_address' => '', 'phone' => '777123456', 'address' => 'عنوان اختبار'];
        }
        $this->save($form, 5, ['witnesses' => $witnesses])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/citizen/identity-card/'.$form->id.'?step=6')->assertOk()->assertSee('شاهد اختبار 0');
    }

    public function test_draft_can_be_saved_incomplete_resumed_and_rejects_stale_or_foreign_changes(): void
    {
        $form = $this->draft();
        $this->save($form, 1, ['data' => ['first_name' => 'حسام']], 'save')->assertSessionHasNoErrors();
        $this->assertSame('حسام', $form->fresh()->data['first_name']);
        $this->assertSame(1, $form->fresh()->current_step);
        $this->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee('حسام');
        $this->post('/citizen/identity-card/'.$form->id, ['step' => 1, 'action' => 'save', 'version' => 1, 'data' => []])->assertStatus(409);
        $this->save($form, 1, ['data' => []])->assertSessionHasErrors('data.first_name');
        $this->post('/citizen/identity-card')->assertRedirect();
        $this->assertSame(1, IdentityCardForm::count());
        $this->actingAs(User::factory()->create())->get('/citizen/identity-card/'.$form->id)->assertForbidden();
        $this->save($form, 1, ['data' => ['first_name' => 'غير مخول']], 'save')->assertForbidden();
        $this->assertSame('حسام', $form->fresh()->data['first_name']);
    }

    public function test_photo_validation_private_storage_replacement_and_witnesses(): void
    {
        $form = $this->draft();
        for ($step = 1; $step <= 3; $step++) {
            $this->save($form, $step, ['data' => $this->data($step)])->assertSessionHasNoErrors();
        }
        foreach ([UploadedFile::fake()->image('small.png', 599, 600), UploadedFile::fake()->image('large.jpg', 600, 600)->size(2049), UploadedFile::fake()->create('script.svg', 10, 'image/svg+xml')] as $file) {
            $this->save($form, 4, ['photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->assertNull($form->fresh()->citizen_attachment_id);
        $this->save($form, 4, [])->assertSessionHasErrors('photo');
        $this->save($form, 4, ['photo' => UploadedFile::fake()->image('unsafe-client.jpg', 600, 600)])->assertSessionHasNoErrors();
        $photo = $form->fresh()->photo;
        $this->assertMatchesRegularExpression('~^citizen-files/[a-f0-9-]{36}\.jpg$~', $photo->storage_path);
        Storage::disk('attachments')->assertExists($photo->storage_path);
        $this->assertSame('pending', $photo->review_status);
        $this->get('/identity-card/forms/'.$form->id.'/photo')->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->save($form, 4, ['photo' => UploadedFile::fake()->image('replacement.png', 600, 600)], 'save')->assertSessionHasNoErrors();
        $this->assertNotSame($photo->storage_path, $form->fresh()->photo->storage_path);
        $this->actingAs(User::factory()->create())->get('/identity-card/forms/'.$form->id.'/photo')->assertForbidden();
    }

    public function test_complete_submission_reuses_existing_request_and_is_private_idempotent_and_immutable(): void
    {
        $form = $this->draft();
        $this->ready($form);
        $service = $form->service;
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST configured service']);
        $admin = User::factory()->create(['role' => 'admin']);
        $service->assessments()->create(['user_id' => $form->user_id, 'rules_version' => $service->rules_version, 'identity_verified' => true, 'conditions_met' => true, 'reviewed_by' => $admin->id, 'reviewed_at' => now(), 'verification_reference' => 'TEST only']);
        $this->get('/citizen/identity-card/'.$form->id.'?step=6')->assertOk();
        $reviews = session('identity_card_reviews');
        $url = '/citizen/identity-card/'.$form->id.'/submit';
        $payload = ['version' => $form->fresh()->version, 'declaration' => '1', 'review_token' => array_key_last($reviews)];
        $this->post($url, ['version' => $payload['version']])->assertSessionHasErrors('declaration');
        $this->post($url, $payload)->assertSessionHasNoErrors()->assertRedirect();
        $form = $form->fresh();
        $application = $form->application;
        $this->assertNotNull($application);
        $this->assertMatchesRegularExpression('/^REQ-[0-9A-Z]{26}$/', $application->reference);
        $this->assertSame('submitted', $application->status);
        $this->assertSame('pending_photo_review', $form->status);
        $this->assertSame('بيان اختبار', $application->request_data['first_name']);
        $this->assertSame($application->id, $form->photo->citizen_service_request_id);
        $this->assertNotNull($form->declared_at);
        $this->assertSame(['not_verified'], $form->witnesses->pluck('verification_status')->unique()->values()->all());
        $this->assertSame(['not_recorded'], $form->witnesses->pluck('attendance_status')->unique()->values()->all());
        $this->post($url, $payload)->assertRedirect();
        $this->assertSame(1, $form->citizen->serviceRequests()->count());
        $this->save($form, 1, ['data' => ['first_name' => 'تغيير']], 'save')->assertStatus(409);
        $this->get('/citizen/identity-card/'.$form->id)->assertOk()->assertSee($application->reference);
        $employee = User::factory()->create(['role' => 'employee']);
        $this->actingAs($employee)->get('/employee/identity-card/'.$form->id)->assertForbidden();
        (new ServiceReviewPermission)->forceFill(['user_id' => $employee->id, 'government_service_id' => $service->id, 'can_review' => true, 'granted_by' => $admin->id])->save();
        $this->get('/employee/identity-card/'.$form->id)->assertOk()->assertSee('شاهد اختبار 0');
        $this->get('/identity-card/forms/'.$form->id.'/photo')->assertOk();
        $this->actingAs(User::factory()->create())->get('/citizen/identity-card/'.$form->id)->assertForbidden();
    }

    public function test_guest_and_employee_cannot_create_citizen_forms(): void
    {
        $this->get('/citizen/identity-card')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'employee']))->post('/citizen/identity-card')->assertForbidden();
        $this->assertSame(0, IdentityCardForm::count());
    }

    public function test_confirmation_detects_photo_changes_outside_the_wizard(): void
    {
        $form = $this->draft();
        $this->ready($form);
        $reviews = session('identity_card_reviews');
        $version = $form->fresh()->version;
        $form->fresh()->photo->forceFill(['version_token' => (string) Str::uuid()])->save();
        $this->post('/citizen/identity-card/'.$form->id.'/submit', ['version' => $version, 'declaration' => '1', 'review_token' => array_key_last($reviews)])->assertSessionHasErrors('review_token');
        $this->assertNull($form->fresh()->submitted_at);
        $this->assertSame(0, $form->citizen->serviceRequests()->count());
    }

    public function test_generic_attachment_replacement_cannot_bypass_final_photo_dimensions(): void
    {
        $form = $this->draft();
        $this->ready($form);
        $service = $form->service;
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'TEST photo rule', 'verification_rules' => ['documents' => [['code' => 'identity_photo', 'name' => 'صورة اختبار', 'kind' => 'photo', 'requires_review' => true, 'allowed_mime_types' => ['image/jpeg', 'image/png']]]]]);
        $photo = $form->fresh()->photo;
        try {
            app(ManageAttachments::class)->store($form->citizen, $service, null, 'identity_photo', UploadedFile::fake()->image('small.jpg', 100, 100));
            $this->fail('A replacement below the minimum dimensions must be rejected.');
        } catch (ValidationException $error) {
            $this->assertArrayHasKey('file', $error->errors());
        }
        $this->assertSame($photo->storage_path, $form->fresh()->photo->storage_path);
        $this->assertNull($form->fresh()->submitted_at);
    }
}
