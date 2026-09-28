<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class FrontendCache
{
    /**
     * Per-type TTL constants (seconds).
     * Override any with FRONTEND_CACHE_TTL_* environment variables.
     */
    const TTL_NAVIGATION   = 86400;  // 24 h — header/footer rarely change and any
                                     // admin edit bumps the version + pings the
                                     // frontend revalidate webhook immediately.
                                     // Override via FRONTEND_CACHE_TTL_NAVIGATION
    const TTL_STATIC_PAGES = 600;    // 10 min — override via FRONTEND_CACHE_TTL_STATIC_PAGES
    const TTL_CONTENT      = 600;    // 10 min — services, categories, sectors (via defaultTtl)
    const TTL_LOCATIONS    = 600;    // 10 min — override via FRONTEND_CACHE_TTL_LOCATIONS
    const TTL_BLOGS        = 300;    // 5 min  — override via FRONTEND_CACHE_TTL_BLOGS
    const TTL_ROUTE_MANIFEST = 600;  // 10 min — override via FRONTEND_CACHE_TTL_ROUTE_MANIFEST
    const TTL_REDIRECT     = 300;    // 5 min  — override via FRONTEND_CACHE_TTL_REDIRECT
    const TTL_SERVICE_FINDER = 3600; // 1 h — the coverage map changes only when
                                     // an admin edits it or an import runs, and
                                     // both bump the version immediately, so a
                                     // long TTL costs nothing in freshness.
                                     // Override via FRONTEND_CACHE_TTL_SERVICE_FINDER

    /** Cache successful reads; application exceptions must propagate exactly once. */
    public static function remember(string $key, Closure $callback, ?int $ttlSeconds = null): mixed
    {
        if (! self::enabled()) return $callback();

        $cacheKey = self::prefix().':'.self::version().':'.$key;
        try {
            $store = self::store();
            $cached = $store->get($cacheKey);
            if ($cached !== null) return $cached;
        } catch (Throwable $exception) {
            report($exception);
            return $callback();
        }

        $value = $callback();
        try {
            $store->put($cacheKey, $value, max(1, $ttlSeconds ?? self::defaultTtl()));
        } catch (Throwable $exception) {
            report($exception);
        }
        return $value;
    }

    /**
     * Resolve the correct TTL for a cache key based on its prefix segment.
     * Allows controllers to call remember($key, $cb) without specifying TTL
     * when the key prefix is descriptive (e.g. "navigation", "blog:…").
     *
     * @param string $key
     */
    public static function ttlForKey(string $key): int
    {
        return match (true) {
            str_starts_with($key, 'navigation')    => (int) config('frontend.cache.ttls.navigation', self::TTL_NAVIGATION),
            str_starts_with($key, 'static_page')   => (int) config('frontend.cache.ttls.static_pages', self::TTL_STATIC_PAGES),
            (str_starts_with($key, 'blog') || str_starts_with($key, 'website_blog') || str_starts_with($key, 'recent_blog'))           => (int) config('frontend.cache.ttls.blogs', self::TTL_BLOGS),
            str_starts_with($key, 'redirect')       => (int) config('frontend.cache.ttls.redirect', self::TTL_REDIRECT),
            str_starts_with($key, 'location')       => (int) config('frontend.cache.ttls.locations', self::TTL_LOCATIONS),
            str_starts_with($key, 'route_manifest') => (int) config('frontend.cache.ttls.route_manifest', self::TTL_ROUTE_MANIFEST),
            str_starts_with($key, 'service_finder') => (int) config('frontend.cache.ttls.service_finder', self::TTL_SERVICE_FINDER),
            default                                 => self::defaultTtl(),
        };
    }

    /**
     * Invalidate all cached data by bumping the version key.
     * Deferred to after DB commit when inside a transaction.
     */
    public static function bump(): void
    {
        try {
            $connection = DB::connection();

            if ($connection->transactionLevel() > 0) {
                $connection->afterCommit(fn () => self::bumpNow());

                return;
            }
        } catch (Throwable $exception) {
            report($exception);
        }

        self::bumpNow();
    }

    protected static function bumpNow(): void
    {
        try {
            self::store()->forever(self::prefix().':version', self::newVersion());
        } catch (Throwable $exception) {
            report($exception);
        }

        self::notifyFrontend();
    }

    public static function version(): string
    {
        try {
            return (string) self::store()->rememberForever(
                self::prefix().':version',
                fn () => self::newVersion()
            );
        } catch (Throwable $exception) {
            report($exception);

            return 'unavailable';
        }
    }

    protected static function enabled(): bool
    {
        return (bool) config('frontend.cache.enabled', true);
    }

    protected static function store(): mixed
    {
        $store = config('frontend.cache.store');

        return $store ? Cache::store((string) $store) : Cache::store();
    }

    protected static function prefix(): string
    {
        return trim((string) config('frontend.cache.prefix', 'frontend_api'), ':') ?: 'frontend_api';
    }

    protected static function defaultTtl(): int
    {
        return max(1, (int) config('frontend.cache.ttl', 900));
    }

    protected static function newVersion(): string
    {
        return now()->format('YmdHisv').':'.Str::random(8);
    }

    protected static function notifyFrontend(): void
    {
        $url    = trim((string) config('frontend.revalidation.url'));
        $secret = trim((string) config('frontend.revalidation.secret'));

        if ($url === '' || $secret === '') {
            return;
        }

        try {
            $response = Http::acceptJson()
                ->withToken($secret)
                ->timeout(max(1, (int) config('frontend.revalidation.timeout', 3)))
                ->post($url, [
                    'tags' => [
                        'blogs',
                        'navigation',
                        'pages',
                        'service-pages',
                        'services',
                        'site',
                    ],
                ]);

            if (! $response->successful()) {
                report(new \RuntimeException(
                    'Frontend cache revalidation failed with HTTP '.$response->status().'.'
                ));
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
