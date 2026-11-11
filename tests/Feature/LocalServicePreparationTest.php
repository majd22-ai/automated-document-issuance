<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use App\Models\IdentityCardAttestation;
use App\Models\IdentityCardForm;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class LocalServicePreparationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:') {
            throw new \RuntimeException('In-memory test database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_preparation_preserves_existing_values_and_is_idempotent(): void
    {
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $service->update(['fee' => '3500.00', 'currency' => 'ريال يمني', 'source_reference' => 'Existing documented reference', 'estimated_duration' => 'Existing duration']);
        $this->artisan('services:prepare-local', ['--database' => ':memory:'])->assertSuccessful();
        $this->assertSame(0, GovernmentService::where('is_active', true)->count());
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();
        $this->assertSame(8, GovernmentService::where('is_active', true)->count());
        $this->assertSame('3500.00', $service->fresh()->fee);
        $this->assertSame('Existing documented reference', $service->fresh()->source_reference);
        $this->assertSame('Existing duration', $service->fresh()->estimated_duration);
        $identity = $service->fresh();
        $this->assertTrue($identity->rules_approved);
        $this->assertSame($identity->required_documents, array_column($identity->verification_rules['documents'], 'name'));
        $this->assertSame('manual', $identity->verification_rules['restriction_source']);
        $photoRule = collect($identity->verification_rules['documents'])->firstWhere('kind', 'photo');
        $this->assertNotNull($photoRule);
        $this->assertNotContains('application/pdf', $photoRule['allowed_mime_types']);
        $this->assertSame(7, GovernmentService::whereNull('fee')->count());
        $this->assertSame(0, DB::table('service_review_permissions')->count());
        $before = GovernmentService::all()->toArray();
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();
        $this->assertSame($before, GovernmentService::all()->toArray());
        $lost = GovernmentService::where('code', 'identity-lost')->firstOrFail();
        $this->assertNull($lost->issuance_rules); // The engine does not implement replacement issuance.
        $this->assertNotEmpty($service->fresh()->issuance_rules);
    }

    public function test_wrong_database_and_production_are_refused_without_changes(): void
    {
        $this->artisan('services:prepare-local', ['--database' => 'another_database', '--apply' => true])->assertFailed();
        app()->detectEnvironment(fn () => 'production');
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertFailed();
        $this->assertSame(0, GovernmentService::where('is_active', true)->count());
    }

    public function test_two_photograph_document_is_separate_from_the_wizard_portrait(): void
    {
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $twoPhotoName = 'صورتان شخصيتان حديثتان مقاس 4×6 بخلفية بيضاء.';
        $identityProofName = 'وثيقة رسمية تتضمن الاسم والصورة وتاريخ ومكان الميلاد.';
        $service->update([
            'required_documents' => [$twoPhotoName, $identityProofName],
            'verification_rules' => ['documents' => [
                ['code' => 'demo_document_1', 'name' => $twoPhotoName, 'kind' => 'photo', 'allowed_mime_types' => ['image/jpeg', 'image/png'], 'requires_review' => true],
                ['code' => 'demo_document_2', 'name' => $identityProofName, 'kind' => 'photo', 'allowed_mime_types' => ['image/jpeg', 'image/png'], 'requires_review' => true],
            ], 'restriction_source' => 'manual'],
        ]);
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();

        $rules = $service->fresh()->verification_rules['documents'];
        $twoPhotos = collect($rules)->firstWhere('name', $twoPhotoName);
        $identityProof = collect($rules)->firstWhere('name', $identityProofName);
        $formPhoto = collect($rules)->firstWhere('code', 'identity_photo');
        $this->assertSame('document', $twoPhotos['kind']);
        $this->assertContains('application/pdf', $twoPhotos['allowed_mime_types']);
        $this->assertSame('document', $identityProof['kind']);
        $this->assertSame('photo', $formPhoto['kind']);
        $this->assertTrue($formPhoto['optional']);
    }

    public function test_identity_demo_fixture_requires_explicit_mock_draft_and_is_idempotent(): void
    {
        config(['citizen_mock.enabled' => true]);
        Storage::fake('attachments');
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();

        $admin = User::factory()->create();
        $admin->forceFill(['role' => 'admin', 'is_active' => true])->save();
        $personId = DB::table('mock_civil_persons')->insertGetId([
            'fixture_key' => 'demo-fixture-citizen', 'name' => 'مواطن محاكاة', 'national_id' => '99012345678',
            'birth_date' => '2000-01-01', 'birth_place' => 'منطقة اختبار', 'family_role' => 'child',
            'complete' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $citizen = User::factory()->create();
        $citizen->forceFill([
            'role' => 'citizen', 'username' => '99012345678', 'national_id' => '99012345678',
            'phone' => '777123456', 'registration_status' => 'active', 'identity_path' => 'has_identity',
            'activation_source' => 'automated_local_mock', 'mock_person_id' => $personId,
            'account_activated_at' => now(), 'identity_verified_at' => now(),
        ])->save();
        $service = GovernmentService::where('code', 'identity-first-issue')->firstOrFail();
        $form = (new IdentityCardForm)->forceFill([
            'user_id' => $citizen->id, 'government_service_id' => $service->id, 'reference' => 'DEMO-FORM-'.$citizen->id,
            'status' => 'awaiting_data', 'current_step' => 7, 'version' => 1, 'data' => ['applicant_name' => 'مواطن محاكاة'],
        ]);
        $form->save();
        (new IdentityCardAttestation)->forceFill([
            'identity_card_form_id' => $form->id, 'reference' => 'DEMO-ATTEST-'.$citizen->id, 'authority_type' => 'neighborhood',
            'recipient_name' => 'مواطن محاكاة', 'citizen_name' => 'مواطن محاكاة', 'status' => 'approved',
            'created_by' => $admin->id, 'decided_by' => $admin->id, 'decided_at' => now(), 'form_revision' => 1,
        ])->save();
        Storage::disk('attachments')->put('mock-registry/reference-illustration.png', UploadedFile::fake()->image('fixture.png', 20, 20)->getContent());

        $this->artisan('identity-card:prepare-demo-submission', ['--form-id' => (string) $form->id])
            ->assertSuccessful()->expectsOutputToContain('Preview only');
        $this->assertDatabaseMissing('citizen_service_assessments', ['user_id' => $citizen->id, 'government_service_id' => $service->id]);
        $this->assertSame(0, $citizen->attachments()->count());
        $this->assertTrue(collect($service->fresh()->verification_rules['documents'])->firstWhere('name', $service->required_documents[0])['requires_review']);

        $this->artisan('identity-card:prepare-demo-submission', ['--form-id' => (string) $form->id, '--apply' => true])->assertSuccessful();
        $this->assertSame(count($service->required_documents), $citizen->attachments()->where('government_service_id', $service->id)->count());
        $this->assertDatabaseHas('citizen_service_assessments', [
            'user_id' => $citizen->id, 'government_service_id' => $service->id, 'identity_verified' => true,
            'conditions_met' => true, 'is_blocked' => false, 'verification_reference' => 'DEMO-IDENTITY-ASSESSMENT-FORM-'.$form->id,
        ]);
        $this->assertTrue(app(\App\Services\ServiceEligibility::class)->evaluate($citizen->fresh(), $service->fresh())->eligible());
        $this->artisan('identity-card:prepare-demo-submission', ['--form-id' => (string) $form->id, '--apply' => true])->assertSuccessful();
        $this->assertSame(count($service->required_documents), $citizen->attachments()->where('government_service_id', $service->id)->count());
    }

    public function test_existing_issuance_rules_are_preserved_and_required_payment_without_fee_aborts_all_changes(): void
    {
        $service = GovernmentService::where('code', 'birth-certificate-first-issue')->firstOrFail();
        $rules = [
            'enabled' => true, 'source_reference' => 'TEST EXISTING', 'expiry' => ['required' => false],
            'number_prefix' => 'TEST-BC', 'include_year' => true, 'electronic_copy' => 'internal_html',
            'citizen_download' => true, 'delivery' => ['methods' => ['pickup'], 'centers' => ['EXISTING TEST CENTER']],
            'completion_stage' => 'issuance',
        ];
        $service->update(['issuance_rules' => $rules]);
        GovernmentService::where('code', 'passport-renewal')->firstOrFail()->update(['workflow_rules' => ['payment' => ['required' => true, 'stage' => 'processing']]]);
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertFailed();
        $this->assertSame(0, GovernmentService::where('is_active', true)->count());
        GovernmentService::where('code', 'passport-renewal')->firstOrFail()->update(['workflow_rules' => null]);
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();
        $this->assertSame($rules, $service->fresh()->issuance_rules);
    }

    public function test_each_agency_lists_only_its_active_services_and_links_work(): void
    {
        $this->artisan('services:prepare-local', ['--database' => ':memory:', '--apply' => true])->assertSuccessful();
        $pages = [
            '/services' => ['identity', 'birth_certificate', 'passport', 'driving_license'],
            '/services/identity' => ['identity', 'birth_certificate'],
            '/services/passports' => ['passport'],
            '/services/driving-licenses' => ['driving_license'],
        ];
        foreach ($pages as $path => $types) {
            $response = $this->get($path)->assertOk();
            $expected = GovernmentService::where('is_active', true)->whereIn('document_type', $types)->pluck('id')->all();
            $response->assertViewHas('services', fn ($services) => $services->pluck('id')->all() === $expected);
            foreach (GovernmentService::whereIn('id', $expected)->get() as $service) {
                $response->assertSee($service->publicUrl());
                $this->get($service->publicUrl())->assertOk()->assertViewHas('service', fn ($found) => $found->is($service));
            }
        }
        GovernmentService::where('code', 'birth-certificate-first-issue')->firstOrFail()->update(['is_active' => false]);
        $this->get('/services/identity')->assertViewHas('services', fn ($services) => ! $services->contains('code', 'birth-certificate-first-issue'));
        $this->get('/services')->assertViewHas('services', fn ($services) => ! $services->contains('code', 'birth-certificate-first-issue'));
    }
}
