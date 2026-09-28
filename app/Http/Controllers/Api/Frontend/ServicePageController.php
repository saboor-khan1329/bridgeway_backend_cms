<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Category;
use App\Models\Service;
use Illuminate\Http\JsonResponse;

class ServicePageController extends BaseFrontendController
{
    /**
     * GET /api/frontend/sectors/{sectorSlug}
     *
     * Resolves the single sector category assigned to a sector page so the
     * frontend URL remains /sectors/{sectorSlug} without a category assumption.
     */
    public function showSector(string $sectorSlug): JsonResponse
    {
        $result = $this->cached("sector_category_slug:{$sectorSlug}", function () use ($sectorSlug) {
            $service = $this->findBySlug(Service::class, $sectorSlug);
            if (! $service) {
                return ['_missing' => true];
            }
            if (! $service->status) {
                return ['_gone' => true]; // 410
            }
            $category = $service->categories()
                ->where('status', true)
                ->where('type', 'service')
                ->where('category_type', 'sector')
                ->with('slug')
                ->first();

            return $category?->slug?->slug ?? ['_missing' => true];
        });

        if (! $result || (is_array($result) && ! empty($result['_missing']))) {
            return $this->notFound('Sector not found.');
        }

        if (is_array($result) && ! empty($result['_gone'])) {
            return $this->gone('This sector is no longer available.');
        }

        $categorySlug = $result;

        return $this->show($categorySlug, $sectorSlug);
    }

    /**
     * GET /api/frontend/services/{categorySlug}/{serviceSlug}
     *
     * Returns all data needed to render a service or sector inner page
     * (e.g. /services/{category}/{service} or /sectors/{sector}).
     * categorySlug is used to build breadcrumbs and verify context.
     */
    public function show(string $categorySlug, string $serviceSlug): JsonResponse
    {
        $data = $this->cached("service_page:{$categorySlug}:{$serviceSlug}", function () use ($categorySlug, $serviceSlug) {
            $service = $this->findBySlug(Service::class, $serviceSlug);

            if (! $service) {
                return ['_missing' => true];
            }

            if (! $service->status) {
                return ['_gone' => true]; // 410
            }

            $category = $this->findBySlug(Category::class, $categorySlug);

            if (! $category) {
                return ['_missing' => true];
            }

            if (
                ! $category->status
                || $category->type !== 'service'
                || ! in_array($category->category_type, Category::SERVICE_CATEGORY_TYPES, true)
            ) {
                return ['_gone' => true]; // 410
            }

            $service->load([
                'slug',
                'seo',
                'images',
                'faqs' => fn ($q) => $q->where('status', true),
                'testimonials' => fn ($q) => $q->where('status', true),
                'categories' => fn ($q) => $q->where('status', true)->with('slug'),
                'linkedServicesV1' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedServicesV2' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedServicesV3' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'relatedLocations' => fn ($q) => $q->where('status', true)->with(['slug', 'images']),
                'relatedBlogs' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories']),
                'section3SectorFaqs' => fn ($q) => $q->where('status', true),
                'section4SectorFaqs' => fn ($q) => $q->where('status', true),
            ]);

            if (! $service->categories->contains(fn ($serviceCategory) => (int) $serviceCategory->id === (int) $category->id)) {
                return ['_missing' => true];
            }

            $extra = [];

            if ($category->category_type === 'sector') {
                $extra = [
                    'page_type' => 'sector_service',
                    'sector_context' => [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug?->slug,
                        'type' => $category->type,
                        'category_type' => $category->category_type,
                    ],
                ];
            }

            return $this->formatFullService($service, $category, $extra);
        });

        if (! $data || (is_array($data) && ! empty($data['_missing']))) {
            return $this->notFound('Service not found.');
        }

        if (is_array($data) && ! empty($data['_gone'])) {
            return $this->gone('This service is no longer available.');
        }

        return $this->success($data);
    }
}
