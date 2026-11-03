<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ResponsiveImagesTest extends TestCase
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

    public function test_home_keeps_the_layout_and_has_responsive_images_and_targeted_lcp_preloads(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        $xpath = new \DOMXPath($dom);
        $photos = $xpath->query('//div[contains(@class,"gov-header-landscape")]//img');
        $this->assertCount(2, $photos);
        foreach ($photos as $photo) {
            $this->assertStringContainsString(' 320w', $photo->getAttribute('srcset'));
            $this->assertNotEmpty($photo->getAttribute('sizes'));
        }
        $this->assertCount(1, $xpath->query('//div[contains(@class,"gov-welcome-picture")]//img[@class="gov-welcome-photo"]'));
        $this->assertCount(2, $xpath->query('//link[@rel="preload"][@as="image"][@imagesrcset][@media]'));
        $this->assertCount(0, $xpath->query('//link[contains(@href,"fonts.googleapis")]'));
        $this->assertCount(0, $xpath->query('//iframe[starts-with(@src,"https://maps.google")]'));
    }

    public function test_service_banners_have_responsive_documents_and_keep_the_original_fallback(): void
    {
        foreach (['identity' => 'identity-document', 'passports' => 'yemeni-passport', 'driving-licenses' => 'driving-license'] as $path => $file) {
            $html = $this->get('/services/'.$path)->assertOk()->getContent();
            $this->assertStringContainsString('assets/images/services/'.$file.'.webp', $html);
            $this->assertStringContainsString('type="image/avif"', $html);
            $this->assertStringContainsString('fetchpriority="high"', $html);
            $this->assertStringContainsString('sizes=', $html);
        }
    }

    public function test_every_generated_candidate_exists_and_matches_its_width_and_format(): void
    {
        foreach (config('responsive_images') as $original => $image) {
            $this->assertFileExists(public_path($original));
            foreach (['webp' => 'image/webp', 'avif' => 'image/avif'] as $format => $mime) {
                foreach ($image[$format] as $width => $file) {
                    $this->assertFileExists(public_path($file));
                    $metadata = getimagesize(public_path($file));
                    $this->assertSame($width, $metadata[0]);
                    $this->assertSame($mime, $metadata['mime']);
                    $this->assertStringContainsString('/responsive/', $file);
                }
            }
        }
    }
}
