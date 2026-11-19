<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\IdentityCardForm;
use App\Models\ResidenceDistrict;
use App\Models\User;
use App\Services\Identity\IdentityRenewals;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IdentityRenewalTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') throw new \RuntimeException('In-memory database required');
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->withoutMiddleware(ValidateCsrfToken::class);
        Storage::fake('attachments');
        Process::fake(['*photo_quality.py*' => Process::result(output: '{"version":1,"status":"completed","accepted":true,"errors":[]}')]);
    }

    private function fixture(): array
    {
        $owner = User::factory()->create(['username' => 'RENEWAL-TEST']);
        $service = GovernmentService::where('code', 'identity-renewal')->firstOrFail();
        $service->update(['is_active' => true, 'rules_approved' => true, 'source_reference' => 'Synthetic renewal',
            'renewal_statuses' => ['active'], 'required_documents' => [], 'application_fields' => [], 'required_fields' => [],
            'verification_rules' => ['renewal_validity' => 'any']]);
        $service->assessments()->create(['user_id' => $owner->id, 'rules_version' => $service->rules_version,
            'identity_verified' => true, 'conditions_met' => true, 'reviewed_by' => $owner->id, 'reviewed_at' => now(), 'verification_reference' => 'SYNTHETIC']);
        $previous = $owner->documents()->create(['document_type' => 'identity', 'document_type_code' => 'identity', 'document_number' => 'RENEWAL-OLD', 'status' => 'active', 'issued_at' => today()->subYears(5), 'expires_at' => today()->subDay()]);
        $this->actingAs($owner)->get(route('citizen.identity-renewal.start'))->assertRedirect();
        return [$owner, $service, $previous, IdentityCardForm::sole()];
    }

    private function payload($previous, $form): array
    {
        $data = [];
        foreach (config('identity_card.fields.3') as $key => $field) {
            $data[$key] = isset($field['options']) ? array_key_first($field['options']) : 'عنوان اختبار';
        }
        $district = ResidenceDistrict::with('governorate')->firstOrFail();
        $data = array_replace($data, ['residence_country' => 'اليمن', 'residence_governorate' => $district->governorate->name,
            'residence_district' => $district->name, 'phone' => '+967777123456', 'alternate_phone' => '', 'residence_village' => '']);
        return ['version' => $form->version, 'previous_document_id' => $previous->id, 'data' => $data,
            'photo' => UploadedFile::fake()->image('portrait.png', 600, 600), 'previous_card' => UploadedFile::fake()->image('old-card.png', 600, 600)];
    }

    public function test_citizen_can_save_review_and_submit_renewal_without_witnesses_or_attestation(): void
    {
        [$owner, , $previous, $form] = $this->fixture();
        $this->get(route('citizen.identity-renewal.show', $form))->assertOk()->assertSee('تجديد البطاقة الشخصية');
        $this->post(route('citizen.identity-renewal.save', $form), $this->payload($previous, $form))->assertSessionHasNoErrors()->assertRedirect();
        $form->refresh();
        $this->assertSame(7, $form->current_step);
        $this->assertSame(0, $form->witnesses()->count());
        $this->assertNull($form->attestation);
        $this->get(route('citizen.identity-renewal.show', $form))->assertOk();
        $reviews = session('identity_card_reviews');
        $token = array_key_last($reviews);
        $this->post(route('citizen.identity-card.submit', $form), ['version' => $form->version, 'declaration' => 1, 'review_token' => $token])->assertSessionHasNoErrors()->assertRedirect();
        $form->refresh();
        $this->assertNotNull($form->submitted_at);
        $this->assertSame('identity-renewal', $form->application->service_code);
        $this->assertSame($previous->id, $form->application->request_data['previous_document_id']);
        $this->assertNull($form->application->charge);
        $staff = User::factory()->create(['role' => 'employee']);
        (new \App\Models\ServiceReviewPermission)->forceFill(['user_id' => $staff->id, 'government_service_id' => $form->government_service_id, 'can_review' => true, 'granted_by' => $staff->id])->save();
        $application = $form->application->transitionTo(\App\Enums\ServiceRequestStatus::UnderReview, $staff);
        $application->transitionTo(\App\Enums\ServiceRequestStatus::RequiresCorrection, $staff, 'صحح الشارع', ['deficiencies' => [['target_type' => 'field', 'target_key' => 'street', 'description' => 'صحح عنوان الشارع'], ['target_type' => 'attachment', 'target_key' => 'renewal_previous_card', 'description' => 'أرفق صورة أوضح للبطاقة']]]);
        $form->refresh();
        $payload = $this->payload($previous, $form);
        $payload['data']['street'] = 'شارع مصحح';
        $this->post(route('citizen.identity-renewal.save', $form), $payload)->assertSessionHasNoErrors();
        $this->get(route('citizen.identity-renewal.show', $form));
        $token = array_key_last(session('identity_card_reviews'));
        $this->post(route('citizen.identity-card.submit', $form), ['version' => $form->fresh()->version, 'declaration' => 1, 'review_token' => $token])->assertSessionHasNoErrors();
        $this->assertSame('submitted', $application->fresh()->status);
        $this->assertSame('شارع مصحح', $application->fresh()->request_data['street']);
        $deficiency = $application->deficiencies()->where('target_key', 'renewal_previous_card')->firstOrFail();
        $resolution = app(\App\Services\Review\ReviewDeficiencies::class);
        try {
            $resolution->assertResolution($application->fresh(), $deficiency);
            $this->fail('A replacement upload still requires employee review');
        } catch (\Illuminate\Validation\ValidationException) {
            $this->assertSame('awaiting_review', $deficiency->status);
        }
        $reviewer = app(\App\Services\Identity\InitialIdentityReview::class);
        $application = $application->fresh()->transitionTo(\App\Enums\ServiceRequestStatus::UnderReview, $staff);
        $task = $reviewer->tasks($form->fresh())['previous_card'];
        $reviewer->record($staff, $application->fresh(), ['task' => 'previous_card', 'outcome' => 'approved', 'fingerprint' => $task['fingerprint'], 'decision_token' => (string) \Illuminate\Support\Str::uuid(), 'confirmed' => 1]);
        $resolution->assertResolution($application->fresh(), $deficiency);
    }

    public function test_foreign_card_and_foreign_draft_are_rejected(): void
    {
        [, , $previous, $form] = $this->fixture();
        $foreign = User::factory()->create();
        $otherCard = $foreign->documents()->create($previous->only(['document_type', 'document_type_code', 'status', 'issued_at', 'expires_at']) + ['document_number' => 'FOREIGN']);
        $this->post(route('citizen.identity-renewal.save', $form), $this->payload($otherCard, $form))->assertSessionHasErrors('previous_document_id');
        $this->assertNull(data_get($form->fresh()->data, 'previous_document_id'));
        $this->actingAs($foreign)->get(route('citizen.identity-renewal.show', $form))->assertForbidden();
    }

    public function test_missing_card_copy_and_stale_preview_prevent_submission(): void
    {
        [, , $previous, $form] = $this->fixture();
        $payload = $this->payload($previous, $form);
        unset($payload['previous_card']);
        $this->post(route('citizen.identity-renewal.save', $form), $payload)->assertSessionHasNoErrors();
        $this->get(route('citizen.identity-renewal.show', $form));
        $token = array_key_last(session('identity_card_reviews'));
        $this->post(route('citizen.identity-card.submit', $form), ['version' => $form->fresh()->version, 'declaration' => 1, 'review_token' => $token])->assertSessionHasErrors('previous_card');
        $form->refresh();
        $this->post(route('citizen.identity-renewal.save', $form), $this->payload($previous, $form))->assertSessionHasNoErrors();
        $this->post(route('citizen.identity-card.submit', $form), ['version' => $form->fresh()->version, 'declaration' => 1, 'review_token' => $token])->assertSessionHasErrors('review_token');
        $this->assertNull($form->fresh()->submitted_at);
    }

    public function test_generic_wizard_cannot_modify_renewal_identity_fields(): void
    {
        [, , , $form] = $this->fixture();
        $this->post(route('citizen.identity-card.save', $form), ['version' => $form->version, 'step' => 1, 'action' => 'save', 'data' => ['applicant_name' => 'Changed identity']])->assertStatus(409);
        $this->assertNotSame('Changed identity', $form->fresh()->data['applicant_name']);
    }

    public function test_local_setup_is_idempotent_and_preserves_configured_fee(): void
    {
        $service = GovernmentService::where('code', 'identity-renewal')->firstOrFail();
        $this->artisan('identity:prepare-renewal', ['--database' => ':memory:'])->assertSuccessful();
        $this->assertSame('6500.00', $service->fresh()->fee);
        $this->assertSame('YER', $service->fresh()->currency);
        $service->refresh()->update(['fee' => '125.00', 'currency' => 'YER']);
        $this->artisan('identity:prepare-renewal', ['--database' => ':memory:'])->assertSuccessful();
        $this->assertSame('125.00', $service->fresh()->fee);
    }
}
