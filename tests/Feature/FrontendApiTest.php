<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for all GET /api/frontend/* endpoints.
 *
 * Each test follows the pattern:
 *   1. Create the model + slug via factory
 *   2. Hit the API
 *   3. Assert structure and correct data
 *
 * Run:
 *   php artisan test --filter FrontendApiTest
 */
class FrontendApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable frontend caching so tests always hit the DB
        config(['frontend.cache.enabled' => false]);
    }

    private function createServiceCategory(array $attributes = []): Category
    {
        $root = Category::factory()->service()->create(['status' => true]);

        return Category::factory()->service()->create(array_merge([
            'parent_id' => $root->id,
            'status' => true,
        ], $attributes));
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/categories/{slug}
    // ─────────────────────────────────────────────────────────────

    public function test_category_page_returns_200_with_correct_structure(): void
    {
        $category = $this->createServiceCategory([
            'name' => 'Security Services',
            'status' => true,
        ]);

        $slug = $category->slug->slug;

        $response = $this->getJson("/api/frontend/categories/{$slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'name',
                    'slug',
                    'type',
                    'category_type',
                    'seo',
                    'image',
                    'banner_image',
                    'services',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'name' => 'Security Services',
                    'slug' => $slug,
                ],
            ]);
    }

    public function test_category_page_returns_services_with_slugs(): void
    {
        $category = $this->createServiceCategory(['status' => true]);
        $service = Service::factory()->create(['status' => true]);
        $category->services()->attach($service);

        $slug = $category->slug->slug;

        $response = $this->getJson("/api/frontend/categories/{$slug}");

        $response->assertStatus(200);

        $services = $response->json('data.services');
        $this->assertCount(1, $services);
        $this->assertArrayHasKey('slug', $services[0]);
        $this->assertNotNull($services[0]['slug']);
    }

    public function test_category_page_does_not_expose_legacy_faq_associations(): void
    {
        $category = $this->createServiceCategory(['status' => true]);
        $faq = Faq::factory()->create(['question' => 'Test Q?', 'answer' => 'Test A.', 'status' => true]);
        $category->faqs()->attach($faq);

        $slug = $category->slug->slug;

        $response = $this->getJson("/api/frontend/categories/{$slug}");

        $response->assertStatus(200)
            ->assertJsonMissingPath('data.faqs');

        $this->assertDatabaseHas('faqables', [
            'faq_id' => $faq->id,
            'faqable_type' => $category->getMorphClass(),
            'faqable_id' => $category->id,
        ]);
    }

    public function test_category_page_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/frontend/categories/does-not-exist');

        $response->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_inactive_category_returns_410(): void
    {
        $category = $this->createServiceCategory(['status' => false]);
        $slug = $category->slug->slug;

        $response = $this->getJson("/api/frontend/categories/{$slug}");

        $response->assertStatus(410);
    }

    public function test_category_page_returns_sector_category_with_sector_page_type(): void
    {
        $sector = Category::factory()->sector()->create([
            'name' => 'Sectors',
            'status' => true,
        ]);

        $response = $this->getJson("/api/frontend/categories/{$sector->slug->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'page_type' => 'sector_category',
                    'name' => 'Sectors',
                    'category_type' => 'sector',
                ],
            ]);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/services/{categorySlug}/{serviceSlug}
    // ─────────────────────────────────────────────────────────────

    public function test_service_page_returns_200_with_correct_structure(): void
    {
        $category = $this->createServiceCategory(['status' => true]);
        $service = Service::factory()->create([
            'title' => 'Alarm Response Monitoring',
            'status' => true,
        ]);
        $category->services()->attach($service);

        $catSlug = $category->slug->slug;
        $serSlug = $service->slug->slug;

        $response = $this->getJson("/api/frontend/services/{$catSlug}/{$serSlug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'seo',
                    'image',
                    'banner_image',
                    'faqs',
                    'section_2',
                    'section_3',
                    'linked_services_v1',
                    'linked_services_v2',
                    'linked_services_v3',
                    'related_locations',
                    'related_blogs',
                    'categories',
                    'category_context',
                ],
            ]);

        $this->assertSame('Alarm Response Monitoring', $response->json('data.title'));
        $this->assertSame($serSlug, $response->json('data.slug'));
    }

    public function test_service_page_attaches_category_context(): void
    {
        $category = $this->createServiceCategory(['name' => 'Security Services', 'status' => true]);
        $service = Service::factory()->create(['status' => true]);
        $category->services()->attach($service);

        $catSlug = $category->slug->slug;
        $serSlug = $service->slug->slug;

        $response = $this->getJson("/api/frontend/services/{$catSlug}/{$serSlug}");

        $this->assertSame('Security Services', $response->json('data.category_context.name'));
    }

    public function test_service_page_returns_404_when_category_does_not_own_service(): void
    {
        $requestedCategory = $this->createServiceCategory(['status' => true]);
        $actualCategory = $this->createServiceCategory(['status' => true]);
        $service = Service::factory()->create(['status' => true]);
        $actualCategory->services()->attach($service);

        $response = $this->getJson("/api/frontend/services/{$requestedCategory->slug->slug}/{$service->slug->slug}");

        $response->assertStatus(404);
    }

    public function test_service_page_returns_404_for_unknown_slug(): void
    {
        $category = $this->createServiceCategory(['status' => true]);

        $response = $this->getJson("/api/frontend/services/{$category->slug->slug}/no-such-service");

        $response->assertStatus(404);
    }

    public function test_inactive_service_returns_410(): void
    {
        $category = $this->createServiceCategory(['status' => true]);
        $service = Service::factory()->create(['status' => false]);

        $response = $this->getJson("/api/frontend/services/{$category->slug->slug}/{$service->slug->slug}");

        $response->assertStatus(410);
    }

    public function test_service_page_returns_sector_associated_inner_page(): void
    {
        $sector = Category::factory()->sector()->create([
            'name' => 'Sectors',
            'status' => true,
        ]);
        $service = Service::factory()->create([
            'title' => 'Industrial Security Services',
            'status' => true,
        ]);
        $sector->services()->attach($service);

        $response = $this->getJson("/api/frontend/services/{$sector->slug->slug}/{$service->slug->slug}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'page_type' => 'sector_service',
                    'title' => 'Industrial Security Services',
                    'sector_context' => [
                        'slug' => $sector->slug->slug,
                    ],
                ],
            ]);
    }

    public function test_service_page_rejects_sector_inner_page_under_wrong_category(): void
    {
        $category = Category::factory()->service()->create(['status' => true]);
        $sector = Category::factory()->sector()->create(['status' => true]);
        $service = Service::factory()->create(['status' => true]);
        $sector->services()->attach($service);

        $response = $this->getJson("/api/frontend/services/{$category->slug->slug}/{$service->slug->slug}");

        $response->assertStatus(404);
    }

    public function test_sector_page_is_available_at_its_public_frontend_path(): void
    {
        $sector = Category::factory()->sector()->create(['status' => true]);
        $service = Service::factory()->create(['status' => true]);
        $sector->services()->attach($service);

        $response = $this->getJson("/api/frontend/sectors/{$service->slug->slug}");

        $response->assertOk()
            ->assertJsonPath('data.page_type', 'sector_service')
            ->assertJsonPath('data.sector_context.id', $sector->id);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/locations
    // ─────────────────────────────────────────────────────────────

    public function test_locations_index_returns_200_with_locations_array(): void
    {
        Location::factory()->count(3)->create(['status' => true, 'parent_id' => null]);
        Location::factory()->create(['status' => false, 'parent_id' => null]); // inactive

        $response = $this->getJson('/api/frontend/locations');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'locations' => [
                        '*' => ['id', 'title', 'slug', 'image', 'is_featured'],
                    ],
                ],
            ]);

        // Only active, root-level locations
        $this->assertCount(3, $response->json('data.locations'));
    }

    public function test_locations_index_excludes_child_locations(): void
    {
        $parent = Location::factory()->create(['status' => true, 'parent_id' => null]);
        Location::factory()->create(['status' => true, 'parent_id' => $parent->id]);

        $response = $this->getJson('/api/frontend/locations');

        // Only the parent (parent_id = null) should be in the list
        $this->assertCount(1, $response->json('data.locations'));
    }

    public function test_locations_index_returns_empty_array_when_no_locations(): void
    {
        $response = $this->getJson('/api/frontend/locations');

        $response->assertStatus(200);
        $this->assertEmpty($response->json('data.locations'));
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/locations/{slug}
    // ─────────────────────────────────────────────────────────────

    public function test_location_page_returns_200_with_correct_structure(): void
    {
        $location = Location::factory()->create([
            'title' => 'Security Services in London',
            'status' => true,
        ]);

        $slug = $location->slug->slug;

        $response = $this->getJson("/api/frontend/locations/{$slug}");

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'seo',
                    'image',
                    'banner_image',
                    'faqs',
                    'linked_services_v1',
                    'linked_services_v2',
                    'linked_services_v3',
                    'linked_services_v4',
                    'linked_child_locations',
                    'related_services',
                    'related_blogs',
                    'children',
                    'parent',
                ],
            ])
            ->assertJson([
                'data' => ['title' => 'Security Services in London'],
            ]);
    }

    public function test_location_page_includes_child_locations(): void
    {
        $parent = Location::factory()->create(['status' => true]);
        Location::factory()->count(2)->create(['status' => true, 'parent_id' => $parent->id]);

        $response = $this->getJson("/api/frontend/locations/{$parent->slug->slug}");

        $this->assertCount(2, $response->json('data.children'));
    }

    public function test_location_page_includes_parent_context(): void
    {
        $parent = Location::factory()->create(['title' => 'London', 'status' => true]);
        $child = Location::factory()->create(['status' => true, 'parent_id' => $parent->id]);

        $response = $this->getJson("/api/frontend/locations/{$child->slug->slug}");

        $response->assertStatus(200);
        $this->assertSame('London', $response->json('data.parent.title'));
    }

    public function test_location_page_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/frontend/locations/no-such-location');

        $response->assertStatus(404)->assertJson(['success' => false]);
    }

    public function test_inactive_location_returns_410(): void
    {
        $location = Location::factory()->create(['status' => false]);

        $response = $this->getJson("/api/frontend/locations/{$location->slug->slug}");

        $response->assertStatus(410);
    }
}
