<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Support\Providers\RouteServiceProvider as ServiceProvider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

class RouteServiceProvider extends ServiceProvider
{
    public const HOME = '/admin';

    public function boot(): void
    {
        RateLimiter::for('login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email', '')));

            return Limit::perMinute(5)->by($email.'|'.$request->ip());
        });

        // NOTE: the Kernel 'api' middleware group applies throttle:api to
        // EVERY api route, in addition to any route-level throttle — so the
        // internal-IP exemption must live here too.
        RateLimiter::for('api', function (Request $request) {
            if ($this->isInternalFrontendRequest($request)) {
                return Limit::none();
            }

            return Limit::perMinute(120)->by(
                (string) ($request->user()?->id ?: $request->ip())
            );
        });

        RateLimiter::for('frontend', function (Request $request) {
            if ($this->isInternalFrontendRequest($request)) {
                return Limit::none();
            }

            return app()->environment(['local', 'staging'])
                ? Limit::perMinute(1000)->by($request->ip())
                : Limit::perMinute(120)->by($request->ip());
        });

        RateLimiter::for('inquiry', function (Request $request) {
            return Limit::perMinute(max(1, (int) config('inquiries.rate_limit.per_minute', 5)))
                ->by($request->ip());
        });

        // Service Finder quote requests. Limit is admin-tunable, and falls
        // back to the config default when the setting is blank.
        RateLimiter::for('finder-lead', function (Request $request) {
            return Limit::perMinute($this->finderLimit(
                'lead_rate_limit',
                (int) config('service_finder.leads.rate_limit_per_minute', 5)
            ))->by($request->ip());
        });

        // Search logging is fire-and-forget from the UI, so it is allowed a
        // higher ceiling than a form post but still cannot be used to flood
        // the analytics table.
        RateLimiter::for('finder-search', function (Request $request) {
            if ($this->isInternalFrontendRequest($request)) {
                return Limit::none();
            }

            return Limit::perMinute($this->finderLimit(
                'search_rate_limit',
                (int) config('service_finder.searches.rate_limit_per_minute', 30)
            ))->by($request->ip());
        });

        // Same shape as finder-search: a fire-and-forget beacon, generous
        // but not open-ended.
        RateLimiter::for('finder-usage', function (Request $request) {
            if ($this->isInternalFrontendRequest($request)) {
                return Limit::none();
            }

            return Limit::perMinute((int) config('service_finder.usage.rate_limit_per_minute', 60))
                ->by($request->ip());
        });

        $this->routes(function () {
            Route::middleware('api')
                ->prefix('api')
                ->group(base_path('routes/api.php'));

            Route::middleware('web')
                ->group(base_path('routes/web.php'));
        });
    }

    /**
     * Read a Service Finder rate limit from the admin configurator.
     *
     * Limiters are registered during boot, so a database read here must never
     * be allowed to break routing — an unavailable settings table simply
     * yields the configured default.
     */
    private function finderLimit(string $key, int $default): int
    {
        try {
            $value = (int) \App\Support\ServiceFinderSettings::get($key);
        } catch (\Throwable) {
            $value = 0;
        }

        return max(1, $value ?: $default);
    }

    private function isInternalFrontendRequest(Request $request): bool
    {
        $ips = (array) config('frontend.rate_limit.internal_ips', ['127.0.0.1', '::1']);

        return in_array($request->ip(), $ips, true);
    }
}
