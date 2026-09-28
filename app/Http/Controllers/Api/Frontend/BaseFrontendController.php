<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Slug;
use App\Models\Service;
use App\Support\FrontendCache;
use App\Support\HtmlCleaner;
use App\Support\ImageFormatter;
use App\Support\LegacySectorContent;
use App\Support\SeoDefaults;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class BaseFrontendController extends Controller
{
    protected function findBySlug(string $modelClass, string $slug): ?Model
    {
        $record = Slug::query()
            ->where('sluggable_type', $modelClass)
            ->where('slug', $slug)
            ->with('sluggable')
            ->first();

        return $record?->sluggable;
    }

    protected function cached(string $key, callable $callback): mixed
    {
        return FrontendCache::remember($key, $callback, FrontendCache::ttlForKey($key));
    }

    protected function formatImage(Model $model, string $type = 'default'): ?array
    {
        return ImageFormatter::single($model, $type);
    }

    protected function formatFirstImage(Model $model, array $types): ?array
    {
        foreach ($types as $type) {
            $image = $this->formatImage($model, $type);

            if ($image) {
                return $image;
            }
        }

        return null;
    }

    protected function formatBannerImage(Model $model): ?array
    {
        return $this->formatFirstImage($model, ['banner_desktop', 'banner', 'default']);
    }

    protected function formatThumbnailImage(Model $model): ?array
    {
        return $this->formatFirstImage($model, [
            'thumbnail',
            'default',
            'banner_desktop',
            'banner',
            'section_2_side_image',
        ]);
    }

    protected function formatImages(Model $model, string $type = 'default'): array
    {
        return ImageFormatter::many($model, $type);
    }

    protected function formatFaqs($faqs): array
    {
        return $faqs->where('status', true)->map(fn ($f) => [
            'id' => $f->id,
            'question' => $f->question,
            'answer' => HtmlCleaner::clean($f->answer),
        ])->values()->all();
    }

    /**
     * Testimonials attached to a Page/Service/Location/Category via the
     * reviewable morph. Returns the raw card list only — callers wrap this
     * with their own (optional) heading/sub_heading override, since those
     * live on the content record, not the testimonial itself.
     */
    protected function formatTestimonials($testimonials): array
    {
        return $testimonials->where('status', true)->map(fn ($t) => [
            'id' => $t->id,
            'title' => $t->title,
            'content' => HtmlCleaner::plainText($t->content),
            'author_name' => $t->author_name,
            'author_role' => $t->author_role,
            'company_name' => $t->company_name,
            'rating' => $t->rating,
            'photo' => $this->formatImage($t, 'photo'),
        ])->values()->all();
    }

    /**
     * Wraps formatTestimonials() with the attaching record's own heading/
     * sub-heading override (both nullable — the frontend supplies its own
     * default copy when they're blank, so no admin action is required for
     * the section to render correctly).
     */
    protected function formatTestimonialSection(Model $model, $testimonials): array
    {
        return [
            'heading' => $model->testimonials_heading,
            'sub_heading' => $model->testimonials_sub_heading,
            'items' => $this->formatTestimonials($testimonials),
        ];
    }

    /**
     * Admin-authored sections (feature cards, packages, cost factors, process
     * steps) for a service, sector or location page.
     *
     * Blocks that are switched off, or that an editor has started but not
     * filled in, are dropped here rather than on the frontend — the page then
     * simply has one fewer section, exactly as it did before any of this
     * existed. An empty array is the normal state for most pages.
     */
    protected function formatContentBlocks(Model $model): array
    {
        $blocks = $model->relationLoaded('contentBlocks')
            ? $model->contentBlocks
            : $model->contentBlocks()->get();

        return $blocks
            ->where('is_active', true)
            // Only the four components this payload describes. The same table
            // also stores the frontend's page sections, which carry their own
            // nested `data` instead of heading/intro/items and are served by
            // ServicePageContentController — reading one as the other would
            // emit a section with an empty body.
            ->filter(fn ($block) => isset(\App\Models\ContentBlock::TYPES[$block->type]))
            ->filter(fn ($block) => $block->hasContent())
            ->map(fn ($block) => [
                'type' => $block->type,
                'heading' => HtmlCleaner::plainText($block->heading),
                'intro' => HtmlCleaner::plainText($block->intro),
                // Always an object, never an array. A component type with no
                // options produces an empty PHP array, which json_encode writes
                // as [] — so the same key arrived as an object on some blocks
                // and an array on others, and a client reading options.cta_label
                // had to guard for both. (object) pins the shape.
                'options' => (object) $this->formatContentBlockOptions($block),
                'items' => $this->formatContentBlockItems($block),
            ])
            ->values()
            ->all();
    }

    /**
     * Options are merged over the type's declared defaults so the frontend
     * always receives a usable button label even for a block saved before a
     * given option existed.
     */
    protected function formatContentBlockOptions(Model $block): array
    {
        $declared = \App\Models\ContentBlock::TYPES[$block->type]['options'] ?? [];
        $saved = $block->options ?? [];
        $out = [];

        foreach ($declared as $key => $spec) {
            $value = HtmlCleaner::plainText($saved[$key] ?? null);
            $out[$key] = $value !== null && $value !== '' ? $value : ($spec['default'] ?? null);
        }

        return $out;
    }

    /**
     * Item fields are cleaned per the type's field spec: `image` fields become
     * a {src, alt} pair built from the stored file-manager path, `list` fields
     * become a plain array of non-empty strings, everything else is plain text.
     */
    protected function formatContentBlockItems(Model $block): array
    {
        $fields = \App\Models\ContentBlock::TYPES[$block->type]['fields'] ?? [];

        return collect($block->usableItems())
            ->map(function (array $item) use ($fields) {
                $out = [];

                foreach ($fields as $key => $spec) {
                    $value = $item[$key] ?? null;

                    $out[$key] = match ($spec['type']) {
                        'image' => $this->formatContentBlockImage($value, $item[$key.'_alt'] ?? null),
                        'list' => collect(is_array($value) ? $value : preg_split('/\r\n|\r|\n/', (string) $value))
                            ->map(fn ($line) => HtmlCleaner::plainText($line))
                            ->filter(fn ($line) => $line !== null && $line !== '')
                            ->values()
                            ->all(),
                        default => HtmlCleaner::plainText($value),
                    };
                }

                return $out;
            })
            ->values()
            ->all();
    }

    protected function formatContentBlockImage(?string $path, ?string $alt): ?array
    {
        if (! $path) {
            return null;
        }

        return [
            'path' => $path,
            'url' => ImageFormatter::url($path),
            'alt' => HtmlCleaner::plainText($alt),
        ];
    }

    protected function formatTextBlock(?array $block): ?array
    {
        if (! $block) {
            return null;
        }

        return [
            'heading' => HtmlCleaner::plainText(data_get($block, 'heading')),
            'sub_description' => HtmlCleaner::plainText(data_get($block, 'sub_description')),
            'button_name' => HtmlCleaner::plainText(data_get($block, 'button_name')),
            'button_url' => HtmlCleaner::safeUrl(data_get($block, 'button_url')),
        ];
    }

    protected function formatServiceCard(Model $service, ?Category $categoryContext = null): array
    {
        $serviceSlug = $service->slug?->slug;
        $categorySlug = $categoryContext?->slug?->slug;

        if (! $categorySlug && $service->relationLoaded('categories')) {
            $category = $service->categories
                ->first(fn ($category) => $category->status && $category->type === 'service' && $category->category_type === 'service')
                ?? $service->categories
                    ->first(fn ($category) => $category->status && $category->type === 'service' && in_array($category->category_type, Category::SERVICE_CATEGORY_TYPES, true));
            $categorySlug = $category?->slug?->slug;
        } else {
            $category = $categoryContext;
        }

        $url = $category?->category_type === 'sector'
            ? ($serviceSlug ? "/sectors/{$serviceSlug}" : null)
            : ($categorySlug && $serviceSlug ? "/{$categorySlug}/{$serviceSlug}" : null);

        return [
            'id' => $service->id,
            'title' => $service->title,
            'slug' => $serviceSlug,
            'short_description' => HtmlCleaner::plainText($service->short_description),
            'card_description' => HtmlCleaner::plainText($service->card_description),
            'image' => $this->formatThumbnailImage($service),
            'is_featured' => (bool) $service->is_featured,
            'category_slug' => $categorySlug,
            'category_type' => $category?->category_type,
            'url' => $url,
        ];
    }

    protected function formatFullService(Service $service, ?Category $category = null, array $extra = []): array
    {
        $cleanRichText = fn (?string $v) => HtmlCleaner::clean($v);
        $plainText = fn (?string $v) => HtmlCleaner::plainText($v);
        $safeUrl = fn (?string $v) => HtmlCleaner::safeUrl($v);
        $isSectorService = $category?->category_type === 'sector';
        $legacySectorContent = $isSectorService
            ? LegacySectorContent::extract($service->section_9_description)
            : ['description' => $service->section_9_description, 'faq_blocks' => []];
        $section3SectorFaqs = $this->formatFaqs($service->section3SectorFaqs);
        $section4SectorFaqs = $this->formatFaqs($service->section4SectorFaqs);
        $section3SectorsFaqs = $this->formatTextBlock($service->section_3_sectors_faqs);
        $section4SectorsFaqs = $this->formatTextBlock($service->section_4_sectors_faqs);
        $legacySectorFaqFallbacks = [];

        if ($section3SectorFaqs === [] && ($legacyBlock = data_get($legacySectorContent, 'faq_blocks.section_2'))) {
            $section3SectorFaqs = $legacyBlock['faqs'];
            $section3SectorsFaqs ??= [
                'heading' => $plainText($service->section_3_heading),
                'sub_description' => $cleanRichText($service->section_3_description),
                'button_name' => $plainText(data_get($legacyBlock, 'button_name')),
                'button_url' => $safeUrl(data_get($legacyBlock, 'button_url')),
            ];
            $legacySectorFaqFallbacks[] = 'section_3';
        }

        if ($section4SectorFaqs === [] && ($legacyBlock = data_get($legacySectorContent, 'faq_blocks.section_3'))) {
            $section4SectorFaqs = $legacyBlock['faqs'];
            $section4SectorsFaqs ??= [
                'heading' => $plainText($service->section_4_heading),
                'sub_description' => $cleanRichText($service->section_4_description),
                'button_name' => $plainText(data_get($legacyBlock, 'button_name')),
                'button_url' => $safeUrl(data_get($legacyBlock, 'button_url')),
            ];
            $legacySectorFaqFallbacks[] = 'section_4';
        }

        return array_merge([
            'page_type' => 'service',
            'id' => $service->id,
            'title' => $service->title,
            'slug' => $service->slug?->slug,
            'short_description' => $plainText($service->short_description),
            'banner_title' => $service->banner_title,
            'banner_description' => $plainText($service->banner_description),
            'section_2' => [
                'heading' => $service->section_2_heading,
                'description' => $cleanRichText($service->section_2_description),
                'button_name' => $service->section_2_button_name,
                'button_url' => $safeUrl($service->section_2_button_url),
                'image' => $this->formatImage($service, 'section_2_side_image'),
            ],
            'section_3' => $isSectorService ? null : [
                'heading' => $service->section_3_heading,
                'description' => $cleanRichText($service->section_3_description),
                'button_name' => $service->section_3_button_name,
                'button_url' => $safeUrl($service->section_3_button_url),
                'image' => $this->formatImage($service, 'section_3_side_image'),
            ],
            'section_4' => $isSectorService ? null : [
                'heading' => $service->section_4_heading,
                'description' => $cleanRichText($service->section_4_description),
                'image' => $this->formatImage($service, 'section_4_side_image'),
            ],
            'section_5' => [
                'heading' => $service->section_5_heading,
                'description' => $cleanRichText($service->section_5_description),
                'button_name' => $isSectorService ? $service->section_5_button_name : null,
                'button_url' => $isSectorService ? $safeUrl($service->section_5_button_url) : null,
                'image' => $this->formatImage($service, 'section_5_side_image'),
            ],
            'section_6' => [
                'heading' => $service->section_6_heading,
                'description' => $cleanRichText($service->section_6_description),
                'button_name' => $isSectorService ? $service->section_6_button_name : null,
                'button_url' => $isSectorService ? $safeUrl($service->section_6_button_url) : null,
                'image' => $this->formatImage($service, 'section_6_side_image'),
            ],
            'section_7' => [
                'heading' => $service->section_7_heading,
                'description' => $cleanRichText($service->section_7_description),
                'button_name' => $service->section_7_button_name,
                'button_url' => $safeUrl($service->section_7_button_url),
                'image' => $isSectorService ? null : $this->formatImage($service, 'section_7_side_image'),
            ],
            'section_8' => [
                'heading' => $service->section_8_heading,
                'description' => $cleanRichText($service->section_8_description),
                'button_name' => $service->section_8_button_name,
                'button_url' => $safeUrl($service->section_8_button_url),
                'image' => $this->formatImage($service, 'section_8_side_image'),
            ],
            'section_9' => [
                'heading' => $service->section_9_heading,
                'description' => $cleanRichText($legacySectorContent['description']),
                'button_name' => $service->section_9_button_name,
                'button_url' => $safeUrl($service->section_9_button_url),
                'image' => $this->formatImage($service, 'section_9_side_image'),
            ],
            'linked_services_v1' => [
                'heading' => $service->linked_services_v1_heading,
                'sub_description' => $plainText($service->linked_services_v1_sub_description),
                'services' => $service->linkedServicesV1->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($service->linkedServicesV1),
            ],
            'linked_services_v2' => [
                'heading' => $service->linked_services_v2_heading,
                'sub_description' => $plainText($service->linked_services_v2_sub_description),
                'services' => $service->linkedServicesV2->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($service->linkedServicesV2),
            ],
            'linked_services_v3' => [
                'heading' => $service->linked_services_v3_heading,
                'sub_description' => $plainText($service->linked_services_v3_sub_description),
                'services' => $service->linkedServicesV3->map(fn ($s) => $this->formatServiceCard($s))->values()->all(),
                'view_all' => $this->linkedServicesViewAll($service->linkedServicesV3),
            ],
            'related_locations' => [
                'heading' => $service->related_locations_heading,
                'sub_heading' => $service->related_locations_sub_heading,
                'locations' => $service->relatedLocations->map(fn ($l) => $this->formatLocationCard($l))->values()->all(),
            ],
            'related_blogs' => $isSectorService ? [
                'heading' => $service->related_blogs_heading,
                'sub_heading' => $service->related_blogs_sub_heading,
                'blogs' => $service->relatedBlogs->map(fn ($b) => $this->formatLinkedBlogCard($b))->values()->all(),
            ] : null,
            'section_3_sectors_faqs' => $isSectorService ? $section3SectorsFaqs : null,
            'section_4_sectors_faqs' => $isSectorService ? $section4SectorsFaqs : null,
            'section_3_sector_faqs' => $isSectorService ? $section3SectorFaqs : [],
            'section_4_sector_faqs' => $isSectorService ? $section4SectorFaqs : [],
            'legacy_sector_faq_fallbacks' => $isSectorService ? $legacySectorFaqFallbacks : [],
            'is_featured' => (bool) $service->is_featured,
            'seo' => SeoDefaults::for($isSectorService ? 'sector' : 'service', $service->seoApi(), [
                'title' => $service->title,
                'description' => $plainText($service->banner_description ?: $service->short_description),
                'url' => SeoDefaults::urlFor(\App\Support\FrontendPath::forModel($service) ?? '/'),
                'image' => (string) data_get($this->formatThumbnailImage($service), 'url', ''),
                // Parent category name (e.g. "Security Services") for the {category}
                // placeholder — resolved per page so a shared Service/Sector schema
                // template stays correct across every category. Uses the already
                // eager-loaded categories collection (no extra query).
                'category' => (string) (
                    ($service->categories->first(fn ($c) => $c->category_type === ($isSectorService ? 'sector' : 'service'))
                        ?? $service->categories->first())?->name ?? ''
                ),
            ]),
            'image' => $this->formatThumbnailImage($service),
            'banner_image' => $this->formatBannerImage($service),
            'images' => $this->formatImages($service),
            'faqs' => $this->formatFaqs($service->faqs),
            'testimonials' => $this->formatTestimonialSection($service, $service->testimonials),
            'content_blocks' => $this->formatContentBlocks($service),
            'categories' => $service->categories->map(fn ($c) => $this->formatCategorySummary($c))->values()->all(),
            'category_context' => $category ? $this->formatCategorySummary($category) : null,
        ], $extra);
    }

    protected function formatLocationCard(Model $location): array
    {
        $slug = $location->slug?->slug;

        return [
            'id' => $location->id,
            'title' => $location->title,
            'slug' => $slug,
            'url' => $slug ? "/locations/{$slug}" : null,
            'sub_heading' => $location->sub_heading,
            'short_description' => HtmlCleaner::plainText($location->short_description),
            'card_description' => HtmlCleaner::plainText($location->card_description),
            'image' => $this->formatThumbnailImage($location),
            'is_featured' => (bool) $location->is_featured,
        ];
    }

    /**
     * Shared "category summary" shape — used both for a service's list of
     * parent categories and its single category_context, so both stay in
     * sync from one place.
     */
    protected function formatCategorySummary(Model $category): array
    {
        return [
            'id' => $category->id,
            'name' => $category->name,
            'slug' => $category->slug?->slug,
            'type' => $category->type,
            'category_type' => $category->category_type,
        ];
    }

    /**
     * Build the trailing "View All {Category}" card for a linked-services block.
     *
     * The linked services in a block share a category (the one selected in the
     * CMS). We resolve that category from the already-eager-loaded `categories`
     * relation and point the card at its listing page. Returns null when the
     * block is empty or no service category can be resolved, so the card is only
     * added when it makes sense. Computed inside the cached payload — no extra
     * per-request query cost.
     */
    protected function linkedServicesViewAll($services): ?array
    {
        if (! $services || $services->isEmpty()) {
            return null;
        }

        $category = null;

        foreach ($services as $service) {
            if (! $service->relationLoaded('categories')) {
                continue;
            }

            $category = $service->categories
                ->first(fn ($c) => $c->status && $c->type === 'service' && $c->category_type === 'service')
                ?? $service->categories
                    ->first(fn ($c) => $c->status && $c->type === 'service' && in_array($c->category_type, Category::SERVICE_CATEGORY_TYPES, true));

            if ($category && $category->slug?->slug) {
                break;
            }

            $category = null;
        }

        if (! $category || ! $category->slug?->slug) {
            return null;
        }

        $url = $category->category_type === 'sector'
            ? '/sectors'
            : '/'.$category->slug->slug;

        return [
            'label' => 'View All '.$category->name,
            'url' => $url,
            // Use the category page's own BANNER image (its representative hero
            // image — same one shown on /{category}). Fall back to any other
            // category image, and only as a last resort the last linked
            // service's thumbnail — so the card never duplicates a card above.
            'image' => $this->formatBannerImage($category)
                ?? $this->formatThumbnailImage($category)
                ?? $this->formatThumbnailImage($services->last()),
        ];
    }

    protected function formatLinkedBlogCard(Model $blog): array
    {
        $category = $blog->relationLoaded('categories') ? $blog->categories->first() : null;

        return [
            'id'           => $blog->id,
            'title'        => $blog->title,
            'slug'         => $blog->slug?->slug,
            'excerpt'      => HtmlCleaner::plainText($blog->excerpt ?: $blog->short_description),
            'author_name'  => method_exists($blog, 'authorName') ? $blog->authorName() : ($blog->author ?? null),
            'published_at' => $blog->published_at?->format('d M, Y'),
            'created_at'   => $blog->created_at?->format('d M, Y'),
            'updated_at'   => $blog->updated_at?->format('d M, Y'),
            'category'     => $category?->name,
            'image'        => $this->formatThumbnailImage($blog),
        ];
    }

    protected function notFound(string $message = 'Not found.'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 404);
    }

    protected function gone(string $message = 'Gone.'): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message], 410);
    }

    protected function success(array $data): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $data]);
    }
}
