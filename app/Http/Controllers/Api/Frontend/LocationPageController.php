<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Location;
use App\Support\HtmlCleaner;
use App\Support\SeoDefaults;
use Illuminate\Http\JsonResponse;

class LocationPageController extends BaseFrontendController
{
    /**
     * GET /api/frontend/locations/{slug}
     *
     * Returns all data needed to render a location detail page
     * (e.g. /locations/{slug}).
     */
    public function show(string $slug): JsonResponse
    {
        $data = $this->cached("location_page:{$slug}", function () use ($slug) {
            $location = $this->findBySlug(Location::class, $slug);

            if (! $location) {
                return ['_missing' => true];
            }

            if (! $location->status) {
                return ['_gone' => true]; // 410
            }

            $location->load([
                'slug',
                'seo',
                'images',
                'faqs' => fn ($q) => $q->where('status', true),
                'testimonials' => fn ($q) => $q->where('status', true),
                'parent' => fn ($q) => $q->with('slug'),
                'children' => fn ($q) => $q->where('status', true)->with(['slug', 'images']),
                'relatedServices' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug']),
                'relatedBlogs' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories']),
                'linkedServicesV1' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedServicesV2' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedServicesV3' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedServicesV4' => fn ($q) => $q->where('status', true)->with(['slug', 'images', 'categories.slug', 'categories.images']),
                'linkedChildren' => fn ($q) => $q->where('status', true)->with(['slug', 'images']),
                'section3LocationFaqs' => fn ($q) => $q->where('status', true),
                'section4LocationFaqs' => fn ($q) => $q->where('status', true),
            ]);

            return $this->formatLocation($location);
        });

        if (! $data || (is_array($data) && ! empty($data['_missing']))) {
            return $this->notFound('Location not found.');
        }

        if (is_array($data) && ! empty($data['_gone'])) {
            return $this->gone('This location page is no longer available.');
        }

        return $this->success($data);
    }

    private function formatLocation(Location $location): array
    {
        $cleanRichText = fn (?string $v) => HtmlCleaner::clean($v);
        $plainText = fn (?string $v) => HtmlCleaner::plainText($v);
        $safeUrl = fn (?string $v) => HtmlCleaner::safeUrl($v);

        return [
            'id' => $location->id,
            'title' => $location->title,
            'slug' => $location->slug?->slug,
            'sub_heading' => $location->sub_heading,
            'short_description' => $plainText($location->short_description),
            'description' => $cleanRichText($location->description),
            'banner_title' => $location->banner_title,
            'banner_description' => $plainText($location->banner_description),
            'section_2' => [
                'heading' => $location->section_2_heading,
                'description' => $cleanRichText($location->section_2_description),
                'button_name' => $location->section_2_button_name,
                'button_url' => $safeUrl($location->section_2_button_url),
                'image' => $this->formatImage($location, 'section_2_side_image'),
            ],
            'section_5' => [
                'heading' => $location->section_5_heading,
                'description' => $cleanRichText($location->section_5_description),
                'image' => $this->formatImage($location, 'section_5_side_image'),
            ],
            'section_6' => [
                'heading' => $location->section_6_heading,
                'description' => $cleanRichText($location->section_6_description),
                'image' => $this->formatImage($location, 'section_6_side_image'),
            ],
            'section_7' => [
                'heading' => $location->section_7_heading,
                'description' => $cleanRichText($location->section_7_description),
                'image' => $this->formatImage($location, 'section_7_side_image'),
            ],
            'section_8' => [
                'heading' => $location->section_8_heading,
                'description' => $cleanRichText($location->section_8_description),
                'image' => $this->formatImage($location, 'section_8_side_image'),
            ],
            'section_9_map_src' => $safeUrl($location->section_9_map_src),
            'linked_services_v1' => [
                'heading' => $location->linked_services_v1_heading,
                'sub_description' => $plainText($location->linked_services_v1_sub_description),
                'services' => $location->linkedServicesV1->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($location->linkedServicesV1),
            ],
            'section_3_locations_faqs' => $this->formatTextBlock($location->section_3_locations_faqs),
            'section_4_locations_faqs' => $this->formatTextBlock($location->section_4_locations_faqs),
            'linked_services_v2' => [
                'heading' => $location->linked_services_v2_heading,
                'sub_description' => $plainText($location->linked_services_v2_sub_description),
                'services' => $location->linkedServicesV2->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($location->linkedServicesV2),
            ],
            'linked_services_v3' => [
                'heading' => $location->linked_services_v3_heading,
                'sub_description' => $plainText($location->linked_services_v3_sub_description),
                'services' => $location->linkedServicesV3->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($location->linkedServicesV3),
            ],
            'linked_services_v4' => [
                'heading' => $location->linked_services_v4_heading,
                'sub_description' => $plainText($location->linked_services_v4_sub_description),
                'services' => $location->linkedServicesV4->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($location->linkedServicesV4),
            ],
            'linked_child_locations' => [
                'heading' => $location->linked_child_locations_heading,
                'sub_description' => $plainText($location->linked_child_locations_sub_description),
                'locations' => $location->linkedChildren->map(fn ($l) => $this->formatLocationCard($l))->values()->all(),
            ],
            'related_services' => $location->relatedServices->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
            'related_blogs' => [
                'heading' => $location->related_blogs_heading,
                'sub_heading' => $location->related_blogs_sub_heading,
                'blogs' => $location->relatedBlogs->map(fn ($b) => $this->formatLinkedBlogCard($b))->values()->all(),
            ],
            'section_3_location_faqs' => $this->formatFaqs($location->section3LocationFaqs),
            'section_4_location_faqs' => $this->formatFaqs($location->section4LocationFaqs),
            'is_featured' => (bool) $location->is_featured,
            'seo' => SeoDefaults::for('location', $location->seoApi(), [
                'title' => $location->title,
                'description' => $plainText($location->banner_description ?: $location->short_description),
                'url' => SeoDefaults::urlFor('/locations/'.$location->slug?->slug),
                'image' => (string) data_get($this->formatThumbnailImage($location), 'url', ''),
            ]),
            'image' => $this->formatThumbnailImage($location),
            'banner_image' => $this->formatBannerImage($location),
            'images' => $this->formatImages($location),
            'faqs' => $this->formatFaqs($location->faqs),
            'testimonials' => $this->formatTestimonialSection($location, $location->testimonials),
            'content_blocks' => $this->formatContentBlocks($location),
            'parent' => $location->parent ? [
                'id' => $location->parent->id,
                'title' => $location->parent->title,
                'slug' => $location->parent->slug?->slug,
            ] : null,
            'children' => $location->children->map(fn ($c) => $this->formatLocationCard($c))->values()->all(),
        ];
    }
}
