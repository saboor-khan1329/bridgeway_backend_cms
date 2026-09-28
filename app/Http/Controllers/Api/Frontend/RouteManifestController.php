<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Location;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class RouteManifestController extends BaseFrontendController
{
    /**
     * GET /api/frontend/route-manifest
     *
     * Returns active frontend dynamic route params. Next.js uses this for
     * generateStaticParams(), while Laravel remains the owner of relationship
     * and visibility rules.
     */
    public function index(): JsonResponse
    {
        $data = $this->cached('route_manifest', function () {
            $serviceCategories = Category::query()
                ->where('status', true)
                ->where('type', 'service')
                ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES)
                ->withCount([
                    'services as active_services_count' => fn ($q) => $q->where('status', true),
                    'children as active_children_count' => fn ($q) => $q->where('status', true),
                ])
                ->with('slug')
                ->orderBy('id')
                ->get()
                ->reject(fn ($category) => $category->category_type === 'service'
                    && $category->parent_id === null
                    && (int) $category->active_services_count === 0
                    && (int) $category->active_children_count > 0)
                ->map(fn ($category) => $category->slug?->slug)
                ->filter()
                ->values();

            $contentPages = Service::query()
                ->where('status', true)
                ->whereHas('categories', fn ($q) => $q
                    ->where('status', true)
                    ->where('type', 'service')
                    ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES))
                ->with([
                    'slug',
                    'categories' => fn ($q) => $q
                        ->where('status', true)
                        ->where('type', 'service')
                        ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES)
                        ->with('slug'),
                ])
                ->orderBy('id')
                ->get()
                ->flatMap(fn ($service) => $service->categories->map(fn ($category) => [
                    'category_slug' => $category->slug?->slug,
                    'service_slug' => $service->slug?->slug,
                    'category_type' => $category->category_type,
                ]))
                ->filter(fn ($route) => filled($route['category_slug']) && filled($route['service_slug']))
                ->unique(fn ($route) => $route['category_slug'].'/'.$route['service_slug'])
                ->values();

            $servicePages = $contentPages
                ->where('category_type', 'service')
                ->map(fn ($route) => [
                    'category_slug' => $route['category_slug'],
                    'service_slug' => $route['service_slug'],
                ])
                ->values();

            $sectorPages = $contentPages
                ->where('category_type', 'sector')
                ->map(fn ($route) => [
                    'sector_slug' => $route['service_slug'],
                ])
                ->unique('sector_slug')
                ->values();

            $locations = Location::query()
                ->where('status', true)
                ->with('slug')
                ->orderBy('id')
                ->get()
                ->map(fn ($location) => $location->slug?->slug)
                ->filter()
                ->values();

            $blogs = Blog::query()
                ->where('status', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->with('slug')
                ->orderBy('published_at', 'desc')
                ->get()
                ->map(fn ($blog) => $blog->slug?->slug)
                ->filter()
                ->values();

            return [
                'service_categories' => $serviceCategories->map(fn ($slug) => ['slug' => $slug])->all(),
                'service_pages' => $servicePages->all(),
                'sector_pages' => $sectorPages->all(),
                'locations' => $locations->map(fn ($slug) => ['slug' => $slug])->all(),
                'blogs' => $blogs->map(fn ($slug) => ['slug' => $slug])->all(),
            ];
        });

        return $this->success($data);
    }
}
