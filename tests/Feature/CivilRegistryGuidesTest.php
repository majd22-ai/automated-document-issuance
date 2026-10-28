<?php

namespace Tests\Feature;

use Tests\TestCase;

class CivilRegistryGuidesTest extends TestCase
{
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

    public function test_home_exposes_each_guide_without_removing_other_services(): void
    {
        $page = $this->get('/')->assertOk();
        foreach (array_keys(config('civil_registry_guides')) as $key) {
            $page->assertSee(route('services.civil-registry.guide', $key));
        }
        $page->assertSee(route('services.passports'))->assertSee(route('services.driving-licenses'));
    }
}
