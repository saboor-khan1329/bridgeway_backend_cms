<?php

namespace Tests\Feature;

use App\Models\Service;
use App\Support\FrontendCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FrontendCacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_cache_invalidation_waits_for_commit_and_ignores_rolled_back_edits(): void
    {
        config(['frontend.cache.enabled' => true, 'frontend.cache.store' => 'array']);
        $service = Service::create(['title' => 'Development', 'status' => true]);
        $before = FrontendCache::version();
        DB::beginTransaction();
        $service->update(['title' => 'Rolled back']);
        $this->assertSame($before, FrontendCache::version());
        DB::rollBack();
        $this->assertSame($before, FrontendCache::version());
        DB::transaction(function () use ($service, $before): void {
            $service->update(['title' => 'Committed']);
            $this->assertSame($before, FrontendCache::version());
        });
        $this->assertNotSame($before, FrontendCache::version());
    }

    public function test_committed_publication_slug_seo_and_deletion_changes_invalidate_cached_services(): void
    {
        Storage::fake('public');
        config(['frontend.cache.enabled' => true, 'frontend.cache.store' => 'array']);
        $service = Service::create(['title' => 'Development', 'status' => true]);
        $service->slug()->create(['slug' => 'cache-test-service']);
        $service->contentBlocks()->create(['section_key' => 'hero', 'type' => 'serviceHero',
            'data' => ['heading' => 'Development'], 'is_active' => true]);
        $seo = $service->seo()->create(['meta_title' => 'First title']);
        $url = '/api/frontend/service-pages/cache-test-service';
        $this->getJson($url)->assertOk()->assertJsonPath('data.seo.title', 'First title');

        $seo->update(['meta_title' => 'Edited title']);
        $this->getJson($url)->assertOk()->assertJsonPath('data.seo.title', 'Edited title');
        $service->update(['status' => false]);
        $this->getJson($url)->assertGone();
        $service->update(['status' => true]);
        $this->getJson($url)->assertOk();

        $service->slug->update(['slug' => 'renamed-service']);
        $this->getJson($url)->assertNotFound();
        $renamed = '/api/frontend/service-pages/renamed-service';
        $this->getJson($renamed)->assertOk();
        $version = FrontendCache::version();
        $service->images()->create(['path' => 'test.webp', 'disk' => 'public', 'image_type' => 'thumbnail', 'alt' => 'CMS image']);
        $this->assertNotSame($version, FrontendCache::version());
        $service->delete();
        $this->getJson($renamed)->assertNotFound();
    }
}
