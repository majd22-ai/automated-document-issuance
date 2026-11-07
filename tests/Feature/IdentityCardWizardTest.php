<?php

namespace Tests\Feature;

use App\Models\IdentityCardForm;
use App\Models\ServiceReviewPermission;
use App\Models\User;
use App\Services\Attachments\ManageAttachments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class IdentityCardWizardTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Workflow tests isolate the external image engine; protocol failures are tested below.
        Process::fake(['*photo_quality.py*' => Process::result(output: '{"version":1,"status":"completed","accepted":true,"errors":[]}')]);
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
                'issued_place' => 'صنعاء', 'issued_at' => '2020-01-01', 'employer' => '', 'work_address' => '', 'phone' => '777123456', 'address' => 'عنوان اختبار', 'selfie' => UploadedFile::fake()->image('witness-'.$i.'.jpg', 600, 600)];
        }
        $this->save($form, 5, ['witnesses' => $witnesses])->assertSessionHasNoErrors()->assertRedirect();
        $this->get('/citizen/identity-card/'.$form->id.'?step=6')->assertOk()->assertSee('شاهد اختبار 0');
    }

    public function test_residence_village_and_house_are_optional_and_required_errors_are_arabic(): void
    {
        $form = $this->draft();
        for ($step = 1; $step <= 2; $step++) {
            $this->save($form, $step, ['data' => $this->data($step)])->assertSessionHasNoErrors();
        }
        $data = $this->data(3);
        unset($data['residence_village'], $data['house'], $data['residence_governorate']);
        $this->save($form, 3, ['data' => $data])->assertSessionHasErrors('data.residence_governorate');
        $this->assertSame('يرجى إدخال محافظة الإقامة للانتقال إلى الخطوة التالية.', session('errors')->first('data.residence_governorate'));
        $data['residence_governorate'] = 'صنعاء';
        $this->save($form, 3, ['data' => $data])->assertSessionHasNoErrors()->assertRedirect();
    }

    public function test_photo_engine_rejection_is_shown_without_saving_or_advancing(): void
    {
        $form = $this->draft();
        for ($step = 1; $step <= 3; $step++) {
            $this->save($form, $step, ['data' => $this->data($step)])->assertSessionHasNoErrors();
        }
        Process::fake(['*photo_quality.py*' => Process::result(output: '{"version":1,"status":"completed","accepted":false,"errors":["blur","background","multiple_faces"]}')]);
        $this->save($form, 4, ['photo' => UploadedFile::fake()->image('bad.jpg', 600, 600)])
            ->assertSessionHasErrors('photo');
        $this->assertStringContainsString('ضبابية', implode(' ', session('errors')->get('photo')));
        $this->assertSame(4, $form->fresh()->current_step);
        $this->assertNull($form->fresh()->citizen_attachment_id);
        $this->assertSame([], Storage::disk('attachments')->allFiles());
    }

    public function test_photo_engine_failure_cannot_replace_an_existing_private_photo(): void
    {
        $form = $this->draft();
        $this->ready($form);
        $previous = $form->fresh()->photo;
        Process::fake(['*photo_quality.py*' => Process::result(output: 'unavailable', exitCode: 2)]);
        $this->save($form, 4, ['photo' => UploadedFile::fake()->image('replacement.jpg', 600, 600)], 'save')->assertSessionHasErrors('photo');
        $this->assertSame($previous->storage_path, $form->fresh()->photo->storage_path);
        Storage::disk('attachments')->assertExists($previous->storage_path);
        $this->get('/citizen/identity-card/'.$form->id.'?step=6')->assertOk();
        $reviews = session('identity_card_reviews');
        $this->post('/citizen/identity-card/'.$form->id.'/submit', ['version' => $form->fresh()->version, 'declaration' => '1', 'review_token' => array_key_last($reviews)])
            ->assertSessionHasErrors('photo');
        $this->assertNull($form->fresh()->submitted_at);
    }

    public function test_birth_date_selectors_save_real_dates_and_keep_location_names(): void
    {
        $form = $this->draft();
        $this->save($form, 1, ['data' => $this->data(1)])->assertSessionHasNoErrors();
        $this->get('/citizen/identity-card/'.$form->id.'?step=2')->assertOk()
            ->assertSee('birth_dates[birth_gregorian][day]', false)
            ->assertSee('birth_dates[birth_hijri][year]', false)
            ->assertSee('data-birth-locations', false);
        $data = $this->data(2);
        $data['birth_country'] = 'اليمن';
        $data['birth_village'] = 'اسم قرية من الوثيقة';
        $this->save($form, 2, ['data' => $data, 'birth_dates' => [
            'birth_gregorian' => ['year' => '2000', 'month' => '2', 'day' => '29'],
            'birth_hijri' => ['year' => '1420', 'month' => '11', 'day' => '24'],
        ]])->assertSessionHasNoErrors();
        $this->assertSame('2000-02-29', $form->fresh()->data['birth_gregorian']);
        $this->assertSame('1420-11-24', $form->fresh()->data['birth_hijri']);
        $this->assertSame('اسم قرية من الوثيقة', $form->fresh()->data['birth_village']);
    }

    public function test_birth_selector_rejects_invalid_or_partial_dates_even_when_saving_draft(): void
    {
        $form = $this->draft();
        $this->save($form, 1, ['data' => $this->data(1)])->assertSessionHasNoErrors();
        foreach ([['year' => '2001', 'month' => '2', 'day' => '29'], ['year' => '2000', 'month' => '', 'day' => '1']] as $parts) {
            $this->save($form, 2, ['data' => $this->data(2), 'birth_dates' => ['birth_gregorian' => $parts]], 'save')
                ->assertSessionHasErrors('data.birth_gregorian');
        }
        $this->save($form, 2, ['data' => $this->data(2), 'birth_dates' => ['birth_hijri' => ['year' => '1420', 'month' => '13', 'day' => '1']]])
            ->assertSessionHasErrors('data.birth_hijri');
        $this->assertArrayNotHasKey('birth_gregorian', $form->fresh()->data);
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
        $this->get(route('citizen.identity-card.witness-selfie', ['form' => $form, 'position' => 1]))->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/identity-card/forms/'.$form->id.'/photo')->assertOk();
        $this->actingAs(User::factory()->create())->get('/citizen/identity-card/'.$form->id)->assertForbidden();
    }

    public function test_guest_and_employee_cannot_create_citizen_forms(): void
    {
        $this->get('/citizen/identity-card')->assertRedirect('/login');
        $this->actingAs(User::factory()->create(['role' => 'employee']))->post('/citizen/identity-card')->assertForbidden();
        $this->assertSame(0, IdentityCardForm::count());
    }

    private function witnessPayload(): array
    {
        return array_map(fn ($position) => ['name' => 'الشاهد '.$position, 'document_type' => 'بطاقة شخصية',
            'document_number' => '200000'.$position, 'issued_place' => 'صنعاء', 'issued_at' => '2020-01-01',
            'phone' => '777123456', 'address' => 'عنوان الشاهد', 'nearby_landmark' => 'قرب المسجد',
            'selfie' => UploadedFile::fake()->image('selfie-'.$position.'.jpg', 600, 600)], [1, 2]);
    }

    public function test_witness_selfies_are_required_for_next_and_partial_upload_rolls_back_without_orphans(): void
    {
        $form = $this->draft();
        $form->forceFill(['current_step' => 5])->save();
        $witnesses = $this->witnessPayload();
        unset($witnesses[1]['selfie']);
        $this->save($form, 5, ['witnesses' => $witnesses])->assertSessionHasErrors('witnesses.1.selfie');
        $this->assertSame(0, $form->witnesses()->count());
        $this->assertSame([], Storage::disk('attachments')->allFiles());
        $this->assertSame(5, $form->fresh()->current_step);
        $this->save($form, 5, ['witnesses' => $this->witnessPayload()])->assertSessionHasNoErrors();
        $this->assertSame(6, $form->fresh()->current_step);
        $this->assertTrue($form->witnesses()->first()->selfieIntact());
        $this->assertSame('قرب المسجد', $form->witnesses()->first()->nearby_landmark);
        $this->assertSame('not_verified', $form->witnesses()->first()->verification_status);
    }

    public function test_witness_selfie_is_private_and_replacement_validation_preserves_previous_file(): void
    {
        $form = $this->draft();
        $this->ready($form);
        $witness = $form->witnesses()->firstWhere('position', 1);
        $url = route('citizen.identity-card.witness-selfie', ['form' => $form, 'position' => 1]);
        $this->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertStringStartsWith('witness-selfies/', $witness->selfie_path);
        $this->assertArrayNotHasKey('selfie_path', $witness->toArray());
        $replacement = $this->witnessPayload();
        $replacement[0]['selfie'] = UploadedFile::fake()->image('too-small.jpg', 200, 200);
        $this->save($form, 5, ['witnesses' => $replacement], 'save')->assertSessionHasErrors('witnesses.0.selfie');
        $this->assertSame($witness->selfie_path, $witness->fresh()->selfie_path);
        Storage::disk('attachments')->assertExists($witness->selfie_path);
        $owner = $form->citizen;
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create(['role' => 'employee']))->get($url)->assertForbidden();
        $this->actingAs($owner);
        $this->app['auth']->guard()->logout();
        $this->get($url)->assertRedirect('/login');
    }

    public function test_witness_draft_can_be_saved_without_selfies_but_missing_saved_selfie_blocks_submission(): void
    {
        $form = $this->draft();
        $form->forceFill(['current_step' => 5])->save();
        $this->save($form, 5, ['witnesses' => [['name' => 'اسم شاهد'], []]], 'save')->assertSessionHasNoErrors();
        $this->assertNull($form->witnesses()->first()->selfie_path);
        $form->forceFill(['current_step' => 1])->save();
        $this->ready($form);
        $reviews = session('identity_card_reviews');
        Storage::disk('attachments')->delete($form->witnesses()->first()->selfie_path);
        $this->post('/citizen/identity-card/'.$form->id.'/submit', ['version' => $form->fresh()->version, 'declaration' => '1', 'review_token' => array_key_last($reviews)])
            ->assertSessionHasErrors('witnesses.0.selfie');
        $this->assertNull($form->fresh()->submitted_at);
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
