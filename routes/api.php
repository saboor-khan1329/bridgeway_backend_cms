<?php

use App\Http\Controllers\Api\Frontend\AppFlagsController;
use App\Http\Controllers\Api\Frontend\BlogPageController;
use App\Http\Controllers\Api\Frontend\CategoryPageController;
use App\Http\Controllers\Api\Frontend\ContentPageController;
use App\Http\Controllers\Api\Frontend\RootPageController;
use App\Http\Controllers\Api\Frontend\LocationPageController;
use App\Http\Controllers\Api\Frontend\LocationsController;
use App\Http\Controllers\Api\Frontend\NavigationController;
use App\Http\Controllers\Api\Frontend\RedirectLookupController;
use App\Http\Controllers\Api\Frontend\RouteManifestController;
use App\Http\Controllers\Api\Frontend\ServiceFinderController;
use App\Http\Controllers\Api\Frontend\ServicePageContentController;
use App\Http\Controllers\Api\Frontend\ServicePageController;
use App\Http\Controllers\Api\Frontend\SiteDataController;
use App\Http\Controllers\Api\FinderLeadApiController;
use App\Http\Controllers\Api\FormSubmissionController;
use App\Http\Controllers\Api\InquiryApiController;
use App\Http\Controllers\Api\OnboardingApiController;
use App\Http\Controllers\Api\SitemapApiController;
use Illuminate\Support\Facades\Route;

Route::get('/frontend/globals/{slug}', [ContentPageController::class, 'globalSections'])->middleware('throttle:frontend');

Route::post('/inquiries', [InquiryApiController::class, 'store'])
    ->middleware('throttle:inquiry');

// The Next.js frontend's forms — one endpoint for all six, dispatched on
// `formName`. Shares the inquiry limiter because it creates the same rows.
Route::post('/forms', [FormSubmissionController::class, 'store'])
    ->middleware('throttle:inquiry');

// Service Finder quote requests. Kept beside /inquiries rather than inside
// the cached frontend group: it is a write, and it carries its own limiter.
Route::post('/service-finder/leads', [FinderLeadApiController::class, 'store'])
    ->middleware('throttle:finder-lead');

// Named limiter (RouteServiceProvider): unlimited for this VPS's own IPs
// (Next.js build prerender), 1000/min on local+staging, 120/min in
// production. Avoids env() here, which breaks under config caching.
$frontendThrottle = 'throttle:frontend';

// Onboarding (public, strict rate limit on submit)
Route::get('/onboarding/config', [OnboardingApiController::class, 'config']);
Route::post('/onboarding/submit', [OnboardingApiController::class, 'submit'])
    ->middleware('throttle:5,1');

// Sitemap
Route::get('/sitemap', [SitemapApiController::class, 'index']);

Route::prefix('frontend')->middleware($frontendThrottle)->group(function () {
    Route::get('/site', [SiteDataController::class, 'index']);
    Route::get('/navigation', [NavigationController::class, 'index']);
    Route::get('/app-flags', [AppFlagsController::class, 'index']);
    Route::get('/redirect', [RedirectLookupController::class, 'show']);
    Route::get('/route-manifest', [RouteManifestController::class, 'index']);
    Route::get('/categories/{slug}', [CategoryPageController::class, 'show']);
    Route::get('/sectors/{sectorSlug}', [ServicePageController::class, 'showSector']);
    Route::get('/services/{categorySlug}/{serviceSlug}', [ServicePageController::class, 'show']);

    // Section-driven content pages (/{slug} on the frontend) — the Amazon,
    // development, SEO and company pages. Same `/slugs` ordering rule.
    Route::get('/pages', [ContentPageController::class, 'index']);
    Route::get('/pages/slugs', [ContentPageController::class, 'slugs']);
    Route::get('/pages/{slug}', [ContentPageController::class, 'show']);
    Route::get('/root-pages/slugs', [RootPageController::class, 'slugs']);
    Route::get('/root-pages/{slug}', [RootPageController::class, 'show']);

    // Section-driven service pages (/service/{slug} on the frontend).
    // `/slugs` is declared before `/{slug}` so it is not swallowed by it.
    Route::get('/service-pages', [ServicePageContentController::class, 'index']);
    Route::get('/service-pages/slugs', [ServicePageContentController::class, 'slugs']);
    Route::get('/service-pages/{slug}', [ServicePageContentController::class, 'show']);
    Route::get('/blogs', [BlogPageController::class, 'index']);
    Route::get('/blog-index', [BlogPageController::class, 'websiteIndex']);
    Route::get('/blogs/recent', [BlogPageController::class, 'recent']);
    Route::get('/blogs/{slug}', [BlogPageController::class, 'show']);
    Route::get('/locations', [LocationsController::class, 'index']);
    Route::get('/locations/{slug}', [LocationPageController::class, 'show']);
    Route::get('/service-finder', [ServiceFinderController::class, 'index']);
    Route::post('/service-finder/searches', [ServiceFinderController::class, 'logSearch'])
        ->middleware('throttle:finder-search');
    Route::post('/service-finder/usage', [ServiceFinderController::class, 'logUsage'])
        ->middleware('throttle:finder-usage');
});
