<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPage;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SectionPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private function sections(): array
    {
        return [[
            'id' => 'hero',
            'type' => 'serviceHero',
            'enabled' => true,
            'data' => ['heading' => 'A database heading'],
        ]];
    }

    public function test_admin_can_create_a_root_content_page_with_exact_seo_and_sections(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/content-pages', [
            'title' => 'Amazon Test',
            'slug' => 'amazon-test',
            'template' => 'amazon-service',
            'sort_order' => 3,
            'status' => '1',
            'meta_title' => 'Exact SEO title',
            'canonical_url' => '/amazon-test',
            'sections' => $this->sections(),
        ])->assertRedirect();

        $page = ContentPage::query()->where('slug', 'amazon-test')->with('contentBlocks')->firstOrFail();
        $this->assertSame('Exact SEO title', $page->seo->meta_title);
        $this->assertSame('/amazon-test', $page->seo->canonical_url);
        $this->assertSame('A database heading', $page->contentBlocks->first()->cmsData()['heading']);
    }

    public function test_admin_can_create_a_slug_routed_service_page(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/service-pages', [
            'title' => 'New Development Service',
            'slug' => 'new-development-service',
            'short_description' => 'A summary.',
            'status' => '1',
            'sections' => $this->sections(),
        ])->assertRedirect();

        $service = Service::query()->whereHas('slug', fn ($query) => $query->where('slug', 'new-development-service'))->with('contentBlocks')->firstOrFail();
        $this->assertSame('A database heading', $service->contentBlocks->first()->cmsData()['heading']);
    }

    public function test_unsupported_section_type_is_rejected(): void
    {
        $this->actingAsAdmin();

        $this->from('/admin/content-pages/create')->post('/admin/content-pages', [
            'title' => 'Bad Page',
            'slug' => 'bad-page',
            'template' => 'service',
            'status' => '1',
            'sections' => [['id' => 'bad', 'type' => 'removedComponent', 'enabled' => true, 'data' => []]],
        ])->assertRedirect('/admin/content-pages/create')->assertSessionHasErrors('sections.0.type');

        $this->assertDatabaseMissing('content_pages', ['slug' => 'bad-page']);
    }

    public function test_dashboard_exposes_only_the_active_bridgeway_content_areas(): void
    {
        $this->actingAsAdmin();

        $this->get('/admin')
            ->assertOk()
            ->assertSee('Content Pages')
            ->assertSee('Service Pages')
            ->assertSee('Blogs')
            ->assertDontSee('Locations')
            ->assertDontSee('Onboarding Portal');
    }

    public function test_admin_can_save_page_level_custom_schema_and_image_metadata(): void
    {
        $this->actingAsAdmin();

        $customSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'ProfessionalService',
            'name' => 'Custom Amazon Page Schema',
        ];

        $sectionsWithImage = [[
            'id' => 'hero',
            'type' => 'serviceHero',
            'enabled' => true,
            'data' => [
                'heading' => 'Amazon Store Hero',
                'image' => [
                    'src' => '/images/amazon-hero.webp',
                    'alt' => 'Amazon Store Growth',
                    'title' => 'Growth Chart',
                    'caption' => 'Year-over-year revenue',
                    'width' => 1200,
                    'height' => 800,
                ],
            ],
        ]];

        $response = $this->post('/admin/content-pages', [
            'title' => 'Amazon Scaling Services',
            'slug' => 'amazon-scaling-services',
            'template' => 'amazon-service',
            'sort_order' => 1,
            'status' => '1',
            'enable_schema' => '1',
            'schema' => json_encode($customSchema),
            'meta_title' => 'Scale on Amazon Fast',
            'canonical_url' => '/amazon-scaling-services',
            'sections' => $sectionsWithImage,
        ]);

        $response->assertRedirect();

        $page = ContentPage::query()->where('slug', 'amazon-scaling-services')->with(['seo', 'contentBlocks'])->firstOrFail();
        $this->assertTrue((bool) $page->seo->enable_schema);
        $this->assertSame($customSchema, $page->seoApi()['schema']);

        $blockData = $page->contentBlocks->first()->cmsData();
        $this->assertSame('/images/amazon-hero.webp', $blockData['image']['src']);
        $this->assertSame('Amazon Store Growth', $blockData['image']['alt']);
        $this->assertSame('Growth Chart', $blockData['image']['title']);
        $this->assertSame('Year-over-year revenue', $blockData['image']['caption']);
        $this->assertSame(1200, (int) $blockData['image']['width']);
        $this->assertSame(800, (int) $blockData['image']['height']);
    }

    public function test_edit_view_contains_page_level_schema_override_and_file_manager_controls(): void
    {
        $this->actingAsAdmin();

        $page = ContentPage::create([
            'slug' => 'amazon-fba-setup',
            'title' => 'Amazon FBA Setup',
            'template' => 'amazon-service',
            'status' => true,
            'is_cms_managed' => true,
            'sort_order' => 2,
        ]);

        $response = $this->get("/admin/content-pages/{$page->id}/edit");

        $response->assertOk();
        $response->assertSee('Page-Level Schema Configuration');
        $response->assertSee('Format / Validate JSON');
        $response->assertSee('Insert Starter Schema');
        $response->assertSee('Browse File Manager');
    }
}
