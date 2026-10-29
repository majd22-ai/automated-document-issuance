<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicFooterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (DB::connection()->getDatabaseName() !== ':memory:' && ! str_ends_with(DB::connection()->getDatabaseName(), '_testing')) {
            throw new \RuntimeException('Isolated test database required');
        }
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
    }

    private function document(string $html): \DOMXPath
    {
        $document = new \DOMDocument;
        @$document->loadHTML('<?xml encoding="UTF-8">'.$html);

        return new \DOMXPath($document);
    }

    public function test_footer_has_three_groups_and_nine_working_links_and_fragment_targets(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $xpath = $this->document($html);
        $this->assertSame(3, $xpath->query('//footer//section')->length);
        $links = $xpath->query('//footer//a');
        $this->assertSame(9, $links->length);
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            $this->assertNotSame('#', $href);
            $this->assertNotSame('', $href);
            $parts = parse_url($href);
            $response = $this->get($parts['path'] ?? '/')->assertOk();
            if (isset($parts['fragment'])) {
                $target = $this->document($response->getContent());
                $this->assertSame(1, $target->query('//*[@id="'.$parts['fragment'].'"]')->length, $href);
            }
            $this->assertNotContains(trim($link->textContent), ['الرئيسية', 'بوابة الخدمات', 'متطلبات الخدمات', 'الاستعلام', 'تواصل معنا']);
        }
        $this->assertStringContainsString('© '.date('Y'), $html);
        $this->assertStringContainsString('مشروع تخرج أكاديمي — نسخة تجريبية', $html);
        $this->assertStringNotContainsString('footer-mobile.js', $html);
    }

    public function test_information_pages_are_public_and_unknown_page_is_rejected(): void
    {
        foreach (config('platform_pages') as $key => $page) {
            $this->get(route('platform.page', $key))->assertOk()->assertSee($page['title']);
        }
        $this->get('/platform/missing')->assertNotFound();
        $this->get(route('help.technical-issue'))->assertOk()->assertSee('الإبلاغ عن مشكلة تقنية')->assertSee('لا يتم إرسال الرسائل');
        $this->get(route('contact.index'))->assertOk()->assertSee('تواصل معنا');
    }
}
