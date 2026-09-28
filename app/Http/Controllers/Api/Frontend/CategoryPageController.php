<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Category;
use App\Support\HtmlCleaner;
use App\Support\SeoDefaults;
use Illuminate\Http\JsonResponse;

class CategoryPageController extends BaseFrontendController
{
    /**
     * GET /api/frontend/categories/{slug}
     *
     * Returns all data needed to render a service or sector category page.
     */
    public function show(string $slug): JsonResponse
    {
        $data = $this->cached("category_page:{$slug}", function () use ($slug) {
            $category = $this->findBySlug(Category::class, $slug);

            if (! $category) {
                return ['_missing' => true];
            }

            if (
                ! $category->status
                || $category->type !== 'service'
                || ! in_array($category->category_type, Category::SERVICE_CATEGORY_TYPES, true)
                || $this->isStructuralGroupingCategory($category)
            ) {
                return ['_gone' => true]; // 410 — existed but unavailable
            }

            $category->load([
                'slug',
                'seo',
                'images',
                'services' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug']),
                'testimonials' => fn ($q) => $q->where('status', true),
                'parent.slug',
            ]);

            return $this->formatCategory($category);
        });

        if (! $data || (is_array($data) && ! empty($data['_missing']))) {
            return $this->notFound('Category not found.');
        }

        if (is_array($data) && ! empty($data['_gone'])) {
            return $this->gone('This category is no longer available.');
        }

        return $this->success($data);
    }

    private function formatCategory(Category $category): array
    {
        return [
            'page_type' => $category->category_type === 'sector' ? 'sector_category' : 'service_category',
            'id' => $category->id,
            'type' => $category->type,
            'category_type' => $category->category_type,
            'name' => $category->name,
            'slug' => $category->slug?->slug,
            'short_description' => HtmlCleaner::plainText($category->short_description),
            'sub_heading1' => $category->sub_heading1,
            'sub_heading_description' => HtmlCleaner::plainText($category->sub_heading_description),
            'banner_title' => $category->banner_title,
            'banner_description' => HtmlCleaner::plainText($category->banner_description),
            'section_cta' => [
                'heading' => $category->section_cta_heading,
                'description' => HtmlCleaner::plainText($category->section_cta_description),
                'button_name' => $category->section_cta_button_name,
                'button_url' => HtmlCleaner::safeUrl($category->section_cta_button_url),
                'image' => $this->formatImage($category, 'section_cta_image'),
            ],
            'is_featured' => (bool) $category->is_featured,
            'seo' => SeoDefaults::for('category', $category->seoApi(), [
                'title' => $category->name,
                'description' => HtmlCleaner::plainText($category->banner_description ?: $category->short_description),
                'url' => SeoDefaults::urlFor('/'.$category->slug?->slug),
                'image' => (string) data_get($this->formatThumbnailImage($category), 'url', ''),
            ]),
            'image' => $this->formatThumbnailImage($category),
            'banner_image' => $this->formatBannerImage($category),
            'parent' => $category->parent ? [
                'name' => $category->parent->name,
                'slug' => $category->parent->slug?->slug,
            ] : null,
            'services' => $category->services
                ->map(fn ($s) => $this->formatServiceCard($s, $category))
                ->values()
                ->all(),
            'testimonials' => $this->formatTestimonialSection($category, $category->testimonials),
        ];
    }

    private function isStructuralGroupingCategory(Category $category): bool
    {
        if ($category->category_type !== 'service' || $category->parent_id !== null) {
            return false;
        }

        return ! $category->services()
            ->where('status', true)
            ->exists()
            && $category->children()
                ->where('status', true)
                ->exists();
    }
}
