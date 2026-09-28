<?php

$bool = static fn (string $key, bool $default): bool => filter_var(
    env($key, $default),
    FILTER_VALIDATE_BOOL,
    FILTER_NULL_ON_FAILURE
) ?? $default;

return [
    'urls' => [
        'site' => rtrim((string) env('WEBSITE_URL', env('FRONTEND_URL', 'https://bridgewaydigital.com')), '/'),
        'admin' => rtrim((string) env('ADMIN_APP_URL', env('APP_URL', 'https://bridgewaydigital.com')), '/'),
    ],

    'cache' => [
        'enabled' => $bool('FRONTEND_API_CACHE_ENABLED', env('APP_ENV') !== 'local'),
        'ttl' => (int) env('FRONTEND_API_CACHE_TTL', 10800),
        'store' => env('FRONTEND_API_CACHE_STORE', 'redis'),
        'prefix' => env('FRONTEND_API_CACHE_PREFIX', 'frontend_api'),
        'ttls' => [
            'navigation' => (int) env('FRONTEND_CACHE_TTL_NAVIGATION', 86400),
            'static_pages' => (int) env('FRONTEND_CACHE_TTL_STATIC_PAGES', 600),
            'blogs' => (int) env('FRONTEND_CACHE_TTL_BLOGS', 300),
            'redirect' => (int) env('FRONTEND_CACHE_TTL_REDIRECT', 300),
            'locations' => (int) env('FRONTEND_CACHE_TTL_LOCATIONS', 600),
            'route_manifest' => (int) env('FRONTEND_CACHE_TTL_ROUTE_MANIFEST', 600),
            'service_finder' => (int) env('FRONTEND_CACHE_TTL_SERVICE_FINDER', 3600),
        ],
    ],

    'revalidation' => [
        'url' => env('FRONTEND_REVALIDATE_URL'),
        'secret' => env('FRONTEND_REVALIDATE_SECRET'),
        'timeout' => (int) env('FRONTEND_REVALIDATE_TIMEOUT', 3),
    ],

    'pagination' => [
        'default_per_page' => (int) env('FRONTEND_API_DEFAULT_PER_PAGE', 12),
        'max_per_page' => (int) env('FRONTEND_API_MAX_PER_PAGE', 100),
    ],

    'sitemap' => [
        'output_dir' => env('SITEMAP_OUTPUT_DIR', base_path('../frontend/public')),
    ],

    'rate_limit' => [
        'internal_ips' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('FRONTEND_INTERNAL_IPS', '127.0.0.1,::1'))
        ))),
    ],
];
