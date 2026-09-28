<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\ContentPage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContentPageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['frontend.cache.enabled' => false]);
    }

    public function test_content_page_returns_existing_frontend_document_shape(): void
    {
        $page = ContentPage::create([
            'slug' => 'amazon-ppc-management-services',
            'title' => 'Amazon PPC Management Services',
            'template' => 'amazon-service',
            'is_cms_managed' => true,
            'status' => true,
        ]);
        $page->seo()->create([
            'meta_title' => 'Amazon PPC',
            'canonical_url' => '/amazon-ppc-management-services',
            'enable_schema' => true,
        ]);
        ContentBlock::create([
            'blockable_type' => ContentPage::class,
            'blockable_id' => $page->id,
            'section_key' => 'hero',
            'type' => 'serviceHero',
            'data' => ['heading' => 'Amazon PPC'],
            'sort_order' => 0,
            'is_active' => true,
        ]);

        $this->getJson('/api/frontend/pages/amazon-ppc-management-services')
            ->assertOk()
            ->assertJsonPath('data.template', 'amazon-service')
            ->assertJsonPath('data.seo.title', 'Amazon PPC')
            ->assertJsonPath('data.sections.0.id', 'hero')
            ->assertJsonPath('data.sections.0.data.heading', 'Amazon PPC');
    }

    public function test_slug_list_excludes_drafts_and_sectionless_records(): void
    {
        ContentPage::create(['slug' => 'empty-page', 'title' => 'Empty', 'template' => 'service', 'status' => true]);
        ContentPage::create(['slug' => 'draft-page', 'title' => 'Draft', 'template' => 'service', 'status' => false]);

        $this->getJson('/api/frontend/pages/slugs')
            ->assertOk()
            ->assertExactJson(['success' => true, 'data' => ['items' => []]]);
    }
}
