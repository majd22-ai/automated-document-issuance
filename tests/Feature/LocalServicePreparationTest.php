<?php

namespace Tests\Feature;

use App\Models\GovernmentService;
use Illuminate\Support\Facades\DB;
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
