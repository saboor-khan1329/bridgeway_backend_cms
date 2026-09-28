<?php

namespace Tests\Feature;

use App\Models\ContentBlock;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/frontend/service-pages — the section-driven service pages the
 * Next.js frontend renders at /service/{slug}.
 *
 * Run:
 *   php artisan test --filter ServicePageContentApiTest
 */
class ServicePageContentApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Answer from the database every time, so a test never asserts against
        // a value another test cached.
        config(['frontend.cache.enabled' => false]);
    }

    /**
     * A published service page with one hero section.
     */
    private function createServicePage(string $slug, array $attributes = []): Service
    {
        $service = Service::factory()->create(array_merge([
            'title' => 'Laravel Web Development',
            'short_description' => 'Build fast Laravel applications.',
            'status' => true,
        ], $attributes));

        $service->slug()->delete();
        $service->slug()->create(['slug' => $slug]);

        $this->addSection($service, 'hero', 'serviceHero', [
            'heading' => 'Laravel Development',
            'description' => 'Build fast.',
        ]);

        return $service->fresh();
    }

    private function addSection(
        Service $service,
        string $key,
        string $type,
        array $data,
        int $order = 0,
        bool $active = true
    ): ContentBlock {
        return ContentBlock::create([
            'blockable_type' => Service::class,
            'blockable_id' => $service->id,
            'type' => $type,
            'section_key' => $key,
            'data' => $data,
            'sort_order' => $order,
            'is_active' => $active,
        ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/service-pages/{slug}
    // ─────────────────────────────────────────────────────────────

    public function test_service_page_returns_200_with_expected_structure(): void
    {
        $this->createServicePage('laravel-development-company');

        $this->getJson('/api/frontend/service-pages/laravel-development-company')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'slug',
                    'status',
                    'template',
                    'title',
                    'sections' => [['id', 'type', 'enabled', 'data']],
                    'seo' => ['title', 'description', 'canonical', 'robots' => ['index', 'follow']],
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'laravel-development-company',
                    'status' => 'published',
                    'template' => 'inner-service',
                    'title' => 'Laravel Web Development',
                ],
            ]);
    }

    public function test_sections_are_returned_in_sort_order(): void
    {
        $service = $this->createServicePage('ordered-page');

        $this->addSection($service, 'faq', 'serFaq', ['MainTitle' => 'FAQs'], 20);
        $this->addSection($service, 'pricing', 'serPrice', ['heading' => 'Pricing'], 10);

        $response = $this->getJson('/api/frontend/service-pages/ordered-page');

        $this->assertSame(
            ['hero', 'pricing', 'faq'],
            array_column($response->json('data.sections'), 'id')
        );
    }

    public function test_section_data_is_returned_unchanged(): void
    {
        $service = $this->createServicePage('nested-data-page');

        $nested = [
            'heading' => 'Compare',
            'comparisonTableData' => [
                'headings' => ['left' => 'Us', 'right' => 'Them'],
                'rows' => [
                    ['label' => 'Speed', 'us' => true, 'them' => false],
                ],
            ],
        ];

        $this->addSection($service, 'comparison', 'featureComparison', $nested, 5);

        $response = $this->getJson('/api/frontend/service-pages/nested-data-page');
        $section = collect($response->json('data.sections'))
            ->firstWhere('id', 'comparison');

        $this->assertSame($nested, $section['data']);
    }

    public function test_section_with_no_data_is_still_returned(): void
    {
        $service = $this->createServicePage('empty-section-page');

        // serLogo and serCta render fixed markup and take no configuration;
        // dropping them would remove a band from the middle of the page.
        $this->addSection($service, 'logos', 'serLogo', [], 1);

        $response = $this->getJson('/api/frontend/service-pages/empty-section-page');

        $this->assertContains(
            'logos',
            array_column($response->json('data.sections'), 'id')
        );
    }

    public function test_empty_section_data_is_an_object_not_an_array(): void
    {
        $service = $this->createServicePage('object-shape-page');
        $this->addSection($service, 'logos', 'serLogo', [], 1);

        $section = collect($this->getJson('/api/frontend/service-pages/object-shape-page')->json('data.sections'))
            ->firstWhere('id', 'logos');

        // json_encode would write an empty PHP array as [], so `data` would
        // arrive as an array on these sections and an object on every other.
        $this->assertSame([], $section['data']);
        $raw = $this->getJson('/api/frontend/service-pages/object-shape-page')->getContent();
        $this->assertStringContainsString('"id":"logos","type":"serLogo","enabled":true,"data":{}', $raw);
    }

    public function test_inactive_sections_are_excluded(): void
    {
        $service = $this->createServicePage('hidden-section-page');
        $this->addSection($service, 'hidden', 'serFaq', ['MainTitle' => 'Hidden'], 1, false);

        $ids = array_column(
            $this->getJson('/api/frontend/service-pages/hidden-section-page')->json('data.sections'),
            'id'
        );

        $this->assertNotContains('hidden', $ids);
        $this->assertContains('hero', $ids);
    }

    public function test_section_types_the_frontend_cannot_render_are_excluded(): void
    {
        $service = $this->createServicePage('unknown-section-page');
        $this->addSection($service, 'mystery', 'someRemovedComponent', ['a' => 'b'], 1);

        $ids = array_column(
            $this->getJson('/api/frontend/service-pages/unknown-section-page')->json('data.sections'),
            'id'
        );

        $this->assertNotContains('mystery', $ids);
    }

    public function test_the_same_component_can_appear_twice_on_a_page(): void
    {
        $service = $this->createServicePage('repeated-component-page');

        $this->addSection($service, 'sub-service', 'subService', ['MainTitle' => 'First'], 1);
        $this->addSection($service, 'sub-service-2', 'subService', ['MainTitle' => 'Second'], 2);

        $sections = collect($this->getJson('/api/frontend/service-pages/repeated-component-page')->json('data.sections'))
            ->where('type', 'subService')
            ->values();

        $this->assertCount(2, $sections);
        $this->assertSame('First', $sections[0]['data']['MainTitle']);
        $this->assertSame('Second', $sections[1]['data']['MainTitle']);
    }

    public function test_faq_section_is_advertised_for_structured_data(): void
    {
        $service = $this->createServicePage('faq-page');
        $this->addSection($service, 'ser-faq', 'serFaq', [
            'MainTitle' => 'FAQs',
            'faqData' => [['question' => 'How long?', 'answer' => 'Six weeks.']],
        ], 1);

        $response = $this->getJson('/api/frontend/service-pages/faq-page')->assertOk();
        $types = collect($response->json('data.seo.schemaGraph'))->pluck('@type');
        $this->assertTrue($types->contains('FAQPage'));
        $this->assertTrue($types->contains('BreadcrumbList'));
    }

    public function test_seo_description_falls_back_to_the_service_summary(): void
    {
        $this->createServicePage('described-page');

        $this->getJson('/api/frontend/service-pages/described-page')
            ->assertJsonPath('data.seo.description', 'Build fast Laravel applications.')
            ->assertJsonPath('data.seo.canonical', '/service/described-page');
    }

    public function test_robots_are_returned_as_booleans_for_nextjs(): void
    {
        $this->createServicePage('robots-page');

        $this->getJson('/api/frontend/service-pages/robots-page')
            ->assertJsonPath('data.seo.robots.index', true)
            ->assertJsonPath('data.seo.robots.follow', true);
    }

    // ─── Missing, invalid and unpublished ────────────────────────────────────

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/frontend/service-pages/no-such-page')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_malformed_slug_returns_404(): void
    {
        foreach (['Bad_Slug', 'UPPERCASE', 'trailing-', 'double--dash', 'sp ace'] as $slug) {
            $this->getJson('/api/frontend/service-pages/'.urlencode($slug))
                ->assertStatus(404);
        }
    }

    public function test_unpublished_page_returns_410(): void
    {
        $this->createServicePage('unpublished-page', ['status' => false]);

        // 410 rather than 404: the page existed, and that is a different
        // instruction to a search engine.
        $this->getJson('/api/frontend/service-pages/unpublished-page')
            ->assertStatus(410)
            ->assertJson(['success' => false]);
    }

    public function test_page_with_no_sections_returns_410(): void
    {
        $service = Service::factory()->create(['status' => true]);
        $service->slug()->delete();
        $service->slug()->create(['slug' => 'sectionless-page']);

        // A service with no sections is not a page; an empty shell would give
        // the frontend a blank route to render.
        $this->getJson('/api/frontend/service-pages/sectionless-page')
            ->assertStatus(410);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/service-pages/slugs
    // ─────────────────────────────────────────────────────────────

    public function test_slugs_endpoint_lists_published_pages(): void
    {
        $this->createServicePage('first-page');
        $this->createServicePage('second-page', ['title' => 'Second']);

        $slugs = $this->getJson('/api/frontend/service-pages/slugs')
            ->assertStatus(200)
            ->json('data.items');

        $this->assertContains('first-page', $slugs);
        $this->assertContains('second-page', $slugs);
    }

    public function test_slugs_endpoint_excludes_unpublished_pages(): void
    {
        $this->createServicePage('live-page');
        $this->createServicePage('draft-page', ['status' => false]);

        $slugs = $this->getJson('/api/frontend/service-pages/slugs')->json('data.items');

        $this->assertContains('live-page', $slugs);
        $this->assertNotContains('draft-page', $slugs);
    }

    public function test_slugs_endpoint_returns_empty_list_when_there_are_no_pages(): void
    {
        $this->getJson('/api/frontend/service-pages/slugs')
            ->assertStatus(200)
            ->assertJson(['success' => true, 'data' => ['items' => []]]);
    }

    public function test_slugs_route_is_not_captured_by_the_detail_route(): void
    {
        // Declared before /{slug}, so "slugs" must not be read as a page slug.
        $this->getJson('/api/frontend/service-pages/slugs')
            ->assertStatus(200)
            ->assertJsonStructure(['data' => ['items']]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/service-pages
    // ─────────────────────────────────────────────────────────────

    public function test_index_returns_published_pages_in_full(): void
    {
        $this->createServicePage('indexed-page');
        $this->createServicePage('hidden-page', ['status' => false]);

        $items = $this->getJson('/api/frontend/service-pages')
            ->assertStatus(200)
            ->json('data.items');

        $this->assertCount(1, $items);
        $this->assertSame('indexed-page', $items[0]['slug']);
        $this->assertNotEmpty($items[0]['sections']);
    }

    public function test_index_omits_pages_with_no_sections(): void
    {
        $service = Service::factory()->create(['status' => true]);
        $service->slug()->delete();
        $service->slug()->create(['slug' => 'sectionless']);

        $items = $this->getJson('/api/frontend/service-pages')->json('data.items');

        $this->assertSame([], array_column($items, 'slug'));
    }
}
