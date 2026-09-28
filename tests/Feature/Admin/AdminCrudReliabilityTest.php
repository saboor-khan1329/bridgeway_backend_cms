<?php

namespace Tests\Feature\Admin;

use App\Models\Redirect;
use App\Models\Category;
use App\Models\Service;
use App\Support\FrontendPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminCrudReliabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_missing_file_manager_asset_errors_map_back_to_the_image_field(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->service()->create();

        $response = $this->from(route('admin.services.create'))
            ->post(route('admin.services.store'), [
                'slug' => 'missing-banner-service',
                'status' => 1,
                'title' => 'Missing Banner Service',
                'category_id' => $category->id,
                'banner_desktop' => [
                    'path' => 'managed/services/banner_desktop/missing-banner.png',
                    'disk' => 'public',
                ],
            ]);

        $response->assertRedirect(route('admin.services.create'));
        $response->assertSessionHasErrors('banner_desktop.path');
    }

    public function test_destroy_actions_hard_delete_records_instead_of_leaving_stale_rows(): void
    {
        $this->actingAsAdmin();

        $service = Service::factory()->create([
            'title' => 'Temporary Service',
        ]);
        $category = Category::factory()->service()->create();
        $category->services()->attach($service);

        $response = $this->delete(route('admin.services.destroy', $service));

        $response->assertRedirect();
        $this->assertDatabaseMissing('services', ['id' => $service->id]);
        $this->assertDatabaseMissing('slugs', [
            'sluggable_type' => $service->getMorphClass(),
            'sluggable_id' => $service->id,
        ]);
    }

    public function test_create_generates_slug_from_title_when_slug_is_blank(): void
    {
        $this->actingAsAdmin();
        $category = Category::factory()->service()->create();

        $response = $this->post(route('admin.services.store'), [
            'title' => 'Auto Generated Slug Service',
            'status' => 1,
            'category_id' => $category->id,
        ]);

        $response->assertRedirect(route('admin.services.index'));

        $service = Service::query()->where('title', 'Auto Generated Slug Service')->firstOrFail();

        $this->assertSame('auto-generated-slug-service', $service->slug->slug);
    }

    public function test_title_update_does_not_change_existing_slug_unless_slug_is_submitted(): void
    {
        $this->actingAsAdmin();

        $service = Service::factory()->create([
            'title' => 'Original Title',
        ]);
        $category = Category::factory()->service()->create();
        $category->services()->attach($service);
        $oldSlug = $service->slug->slug;

        $this->put(route('admin.services.update', $service), [
            'title' => 'Changed Title',
            'status' => 1,
            'category_id' => $category->id,
        ])->assertRedirect(route('admin.services.index'));

        $service->refresh();

        $this->assertSame($oldSlug, $service->slug->slug);
        $this->assertSame(0, Redirect::query()->where('sourceable_id', $service->id)->count());
    }

    public function test_manual_slug_change_creates_301_redirect_and_duplicate_slug_is_rejected(): void
    {
        $this->actingAsAdmin();

        $first = Service::factory()->create(['title' => 'First Service']);
        $second = Service::factory()->create(['title' => 'Second Service']);
        $category = Category::factory()->service()->create();
        $category->services()->attach([$first->id, $second->id]);
        $oldSlug = $first->slug->slug;

        $this->put(route('admin.services.update', $first), [
            'title' => 'First Service',
            'slug' => 'first-service-updated',
            'status' => 1,
            'category_id' => $category->id,
        ])->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('redirects', [
            'from_url' => FrontendPath::forModelSlug($first, $oldSlug),
            'to_url' => FrontendPath::forModelSlug($first, 'first-service-updated'),
            'status_code' => 301,
            'status' => 1,
            'is_auto_generated' => 1,
        ]);

        $this->from(route('admin.services.edit', $second))
            ->put(route('admin.services.update', $second), [
                'title' => 'Second Service',
                'slug' => 'first-service-updated',
                'status' => 1,
                'category_id' => $category->id,
            ])
            ->assertRedirect(route('admin.services.edit', $second))
            ->assertSessionHasErrors('slug');
    }

    public function test_category_change_creates_redirect_for_the_previous_frontend_path(): void
    {
        $this->actingAsAdmin();

        $service = Service::factory()->create(['title' => 'Retail Security']);
        $serviceCategory = Category::factory()->service()->create();
        $newServiceCategory = Category::factory()->service()->create();
        $serviceCategory->services()->attach($service);
        $oldPath = FrontendPath::forModel($service);

        $this->put(route('admin.services.update', $service), [
            'title' => $service->title,
            'status' => 1,
            'category_id' => $newServiceCategory->id,
        ])->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('redirects', [
            'from_url' => $oldPath,
            'to_url' => '/'.$newServiceCategory->slug->slug.'/'.$service->slug->slug,
            'status_code' => 301,
            'status' => 1,
            'is_auto_generated' => 1,
        ]);
    }
}
