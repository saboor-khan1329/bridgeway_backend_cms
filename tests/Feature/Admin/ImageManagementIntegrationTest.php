<?php

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ImageManagementIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_service_creation_can_attach_a_file_manager_image_with_metadata(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = Category::factory()->service()->create();

        $asset = UploadedFile::fake()->image('library-banner.png', 1600, 900);
        Storage::disk('public')->putFileAs('managed/services/banner_desktop', $asset, 'library-banner.png');

        $response = $this->post(route('admin.services.store'), [
            'slug' => 'security-service',
            'status' => 1,
            'title' => 'Security Service',
            'category_id' => $category->id,
            'banner_desktop' => [
                'path' => 'managed/services/banner_desktop/library-banner.png',
                'disk' => 'public',
                'alt' => 'Main banner alt',
                'title' => 'Main banner title',
                'caption' => 'Main banner caption',
            ],
        ]);

        $response->assertRedirect(route('admin.services.index'));

        $service = Service::where('title', 'Security Service')->firstOrFail();
        $image = $service->getImage('banner_desktop');

        $this->assertNotNull($image);
        $this->assertSame('managed/services/banner_desktop/library-banner.png', $image->path);
        $this->assertSame('Main banner alt', $image->alt);
        $this->assertSame('Main banner title', $image->title);
        $this->assertSame('Main banner caption', $image->caption);
        $this->assertSame('selection', $image->source);
    }

    public function test_service_uploads_land_in_the_managed_service_folder(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $category = Category::factory()->service()->create();

        $response = $this->post(route('admin.services.store'), [
            'title' => 'Managed Service',
            'slug' => 'managed-service',
            'status' => 1,
            'category_id' => $category->id,
            'banner_desktop' => [
                'file' => UploadedFile::fake()->image('banner.png', 1400, 800),
                'alt' => 'Banner alt',
                'title' => 'Banner title',
                'caption' => 'Banner caption',
            ],
            'thumbnail' => [
                'file' => UploadedFile::fake()->image('thumb.png', 600, 400),
            ],
        ]);

        $response->assertRedirect(route('admin.services.index'));

        $service = Service::with('images', 'slug')
            ->where('title', 'Managed Service')
            ->whereHas('slug', fn ($query) => $query->where('slug', 'managed-service'))
            ->firstOrFail();

        $banner = $service->getImage('banner_desktop');
        $thumbnail = $service->getImage('thumbnail');

        $this->assertNotNull($banner);
        $this->assertNotNull($thumbnail);
        $this->assertStringStartsWith('managed/services/banner_desktop/', $banner->path);
        $this->assertStringStartsWith('managed/services/thumbnail/', $thumbnail->path);
        Storage::disk('public')->assertExists($banner->path);
        Storage::disk('public')->assertExists($thumbnail->path);
    }

    public function test_replacing_one_of_two_shared_selected_assets_keeps_the_original_file(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();

        $asset = UploadedFile::fake()->image('shared-banner.png', 1200, 800);
        Storage::disk('public')->putFileAs('managed/services/banner_desktop', $asset, 'shared-banner.png');

        $first = Service::factory()->create(['title' => 'First Service']);
        $second = Service::factory()->create(['title' => 'Second Service']);
        $category = Category::factory()->service()->create();
        $category->services()->attach([$first->id, $second->id]);

        foreach ([$first, $second] as $service) {
            $service->images()->create([
                'image_type' => 'banner_desktop',
                'path' => 'managed/services/banner_desktop/shared-banner.png',
                'disk' => 'public',
                'alt' => 'Shared banner',
                'source' => 'selection',
            ]);
        }

        $response = $this->put(route('admin.services.update', $first), [
            'slug' => $first->slug->slug,
            'status' => 1,
            'title' => 'First Service',
            'category_id' => $category->id,
            'banner_desktop' => [
                'file' => UploadedFile::fake()->image('replacement.png', 1600, 900),
            ],
        ]);

        $response->assertRedirect(route('admin.services.index'));
        Storage::disk('public')->assertExists('managed/services/banner_desktop/shared-banner.png');
        $this->assertSame('managed/services/banner_desktop/shared-banner.png', $second->fresh()->getImage('banner_desktop')->path);
    }
}
