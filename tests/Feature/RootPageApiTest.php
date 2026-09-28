<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\ContentPage;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RootPageApiTest extends TestCase
{
    use RefreshDatabase;

    private function service(): Service
    {
        $service = Service::create(['title' => 'Magento Development', 'status' => true]);
        $service->slug()->create(['slug' => 'magento-development-company']);
        $service->contentBlocks()->create(['section_key' => 'hero', 'type' => 'serviceHero',
            'data' => ['heading' => 'CMS heading'], 'is_active' => true, 'sort_order' => 0]);
        return $service;
    }

    public function test_existing_root_menu_url_reuses_the_service_record(): void
    {
        $this->service();
        $this->getJson('/api/frontend/root-pages/magento-development-company')->assertOk()
            ->assertJsonPath('data.sections.0.data.heading', 'CMS heading');
        $this->getJson('/api/frontend/root-pages/slugs')->assertOk()
            ->assertJsonPath('data.items.0', 'magento-development-company');
        $this->assertSame(0, ContentPage::count());
    }

    public function test_draft_root_page_is_not_resurrected_by_a_matching_service(): void
    {
        $this->service();
        ContentPage::create(['slug' => 'magento-development-company', 'title' => 'Draft root',
            'template' => 'service', 'is_cms_managed' => true, 'status' => false]);
        $this->getJson('/api/frontend/root-pages/magento-development-company')->assertGone();
    }

    public function test_unknown_root_url_is_missing(): void
    {
        $this->getJson('/api/frontend/root-pages/no-such-page')->assertNotFound();
    }

    public function test_static_urls_cannot_be_claimed_by_cms_records(): void
    {
        $page = ContentPage::create(['slug' => 'about-us', 'title' => 'CMS collision',
            'template' => 'service', 'is_cms_managed' => true, 'status' => true]);
        $page->contentBlocks()->create(['section_key' => 'hero', 'type' => 'serviceHero',
            'data' => ['heading' => 'Wrong source'], 'is_active' => true]);
        $this->getJson('/api/frontend/pages/about-us')->assertNotFound();
        $this->getJson('/api/frontend/root-pages/about-us')->assertNotFound();
        $this->getJson('/api/frontend/root-pages/slugs')->assertJsonMissing(['about-us']);
    }

    public function test_global_sections_are_not_routable_pages(): void
    {
        $page = ContentPage::create(['slug' => 'home-sections', 'title' => 'Home form',
            'template' => 'global', 'is_cms_managed' => true, 'status' => true]);
        $page->contentBlocks()->create(['section_key' => 'hero', 'type' => 'heroSection',
            'data' => ['submitText' => 'Contact us'], 'is_active' => true]);
        $this->getJson('/api/frontend/globals/home-sections')->assertOk()
            ->assertJsonPath('data.sections.0.data.submitText', 'Contact us')
            ->assertJsonMissingPath('data.seo');
        $this->getJson('/api/frontend/pages/home-sections')->assertNotFound();
        $this->getJson('/api/frontend/globals/about-us')->assertNotFound();
    }

    public function test_backend_calculates_revenue_but_preserves_explicit_cms_values(): void
    {
        $page = ContentPage::create(['slug' => 'calculator', 'title' => 'Calculator', 'template' => 'service',
            'is_cms_managed' => true, 'status' => true]);
        $page->contentBlocks()->create(['section_key' => 'calculator', 'type' => 'revenueCalculate',
            'is_active' => true, 'data' => ['revenueMultiplier' => 3,
                'sliderOptions' => [['investment' => 100], ['investment' => 200, 'revenue' => 900]]]]);
        $this->getJson('/api/frontend/pages/calculator')->assertOk()
            ->assertJsonPath('data.sections.0.data.sliderOptions.0.revenue', 300)
            ->assertJsonPath('data.sections.0.data.sliderOptions.1.revenue', 900);
    }
}
