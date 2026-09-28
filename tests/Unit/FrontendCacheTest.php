<?php

namespace Tests\Unit;

use App\Support\FrontendCache;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

class FrontendCacheTest extends TestCase
{
    public function test_remember_uses_the_configured_cache_store(): void
    {
        $this->configureCache();
        $calls = 0;

        $first = FrontendCache::remember('content', function () use (&$calls): string {
            $calls++;

            return 'cached-value';
        });
        $second = FrontendCache::remember('content', function () use (&$calls): string {
            $calls++;

            return 'new-value';
        });

        $this->assertSame('cached-value', $first);
        $this->assertSame('cached-value', $second);
        $this->assertSame(1, $calls);
    }

    public function test_bump_rotates_the_version_and_notifies_the_frontend(): void
    {
        $this->configureCache();
        config([
            'frontend.revalidation.url' => 'https://frontend.test/api/revalidate',
            'frontend.revalidation.secret' => 'test-secret',
        ]);
        Http::fake([
            'https://frontend.test/api/revalidate' => Http::response(['revalidated' => true]),
        ]);
        $before = FrontendCache::version();

        FrontendCache::bump();

        $this->assertNotSame($before, FrontendCache::version());
        Http::assertSent(fn (Request $request) => $request->url() === 'https://frontend.test/api/revalidate'
            && $request->hasHeader('Authorization', 'Bearer test-secret')
            && $request['tags'] === ['blogs', 'navigation', 'pages', 'service-pages', 'services', 'site']);
    }

    protected function configureCache(): void
    {
        config([
            'frontend.cache.enabled' => true,
            'frontend.cache.store' => 'array',
            'frontend.cache.prefix' => 'frontend_api_test_'.Str::random(8),
        ]);
    }

    public function test_failed_query_is_not_retried_or_cached(): void
    {
        $this->configureCache();
        $calls = 0;
        try {
            FrontendCache::remember('failure', function () use (&$calls) {
                $calls++;
                throw new \RuntimeException('Query failed');
            });
            $this->fail('Expected the query failure.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Query failed', $exception->getMessage());
        }
        $this->assertSame(1, $calls);
        $this->assertSame('recovered', FrontendCache::remember('failure', fn () => 'recovered'));
    }

    public function test_ttl_and_version_changes_expire_cached_values(): void
    {
        $this->configureCache();
        $this->assertSame('first', FrontendCache::remember('ttl', fn () => 'first', 2));
        $this->travel(3)->seconds();
        $this->assertSame('second', FrontendCache::remember('ttl', fn () => 'second', 2));
        FrontendCache::bump();
        $this->assertSame('third', FrontendCache::remember('ttl', fn () => 'third', 2));
        $this->travelBack();
    }

    public function test_blog_ttls_use_cached_configuration(): void
    {
        config(['frontend.cache.ttls.blogs' => 42]);
        foreach (['blog_page:example', 'website_blog_index:1', 'recent_blog_cards:3:'] as $key) {
            $this->assertSame(42, FrontendCache::ttlForKey($key));
        }
    }
}
