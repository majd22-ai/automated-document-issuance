<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CivilRegistryGuidesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $database = DB::connection()->getDatabaseName();
        if ($database !== ':memory:' && ! str_ends_with($database, '_testing')) {
            throw new \RuntimeException('Isolated test database required.');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    public function test_each_image_has_its_own_page_with_all_transcribed_sections(): void
    {
        foreach (config('civil_registry_guides') as $key => $guide) {
            $page = $this->get('/services/civil-registry/'.$key)->assertOk()->assertSee($guide['title']);
            foreach ($guide['sections'] as $heading => $items) {
                $page->assertSee($heading);
                foreach ($items as $item) {
                    $page->assertSee($item);
                }
            }
            foreach (array_merge($guide['duration'], $guide['fees']) as $item) {
                $page->assertSee($item);
            }
        }
        $this->get('/services/civil-registry/unknown')->assertNotFound();
    }

    public function test_guides_live_in_identity_services_without_duplicating_them_on_home(): void
    {
        $home = $this->get('/')->assertOk();
        $page = $this->get('/services/identity')->assertOk();
        foreach (array_keys(config('civil_registry_guides')) as $key) {
            $page->assertSee(route('services.civil-registry.guide', $key));
            $home->assertDontSee(route('services.civil-registry.guide', $key));
        }
        $home->assertSee(route('services.passports'))->assertSee(route('services.driving-licenses'));
    }
}
