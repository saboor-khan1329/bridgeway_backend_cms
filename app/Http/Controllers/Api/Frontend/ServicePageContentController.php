<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\ContentBlock;
use App\Models\Service;
use App\Support\FrontendSectionRegistry;
use App\Support\FrontendSectionPresenter;
use App\Support\FrontendSeoPresenter;
use App\Support\FrontendSchemaBuilder;
use App\Support\HtmlCleaner;
use App\Support\SeoDefaults;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * The frontend's section-driven service pages — /service/{slug}.
 *
 * Distinct from ServicePageController, which serves the older
 * /services/{category}/{service} contract built from a Service's fixed
 * section_* columns. These pages are assembled from ordered ContentBlock rows
 * instead, so an editor composes a page from components rather than filling in
 * a fixed template. Both read the same Service record, its slug and its SEO;
 * only the body differs, which is why this is a second controller rather than
 * a second model.
 */
class ServicePageContentController extends BaseFrontendController
{
    /** The page template the frontend renders these with. */
    private const TEMPLATE = 'inner-service';

    /** Matches the frontend's /service/{slug} route segment. */
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * GET /api/frontend/service-pages
     *
     * Every published service page, in full. Used by the frontend's build step
     * so a whole site's worth of pages costs one request rather than one per
     * page.
     */
    public function index(): JsonResponse
    {
        $data = $this->cached('service_pages:index', function () {
            return $this->publishedQuery()
                ->with($this->pageRelations())
                ->get()
                ->map(fn (Service $service) => $this->formatPage($service))
                ->filter()
                ->values()
                ->all();
        });

        return $this->success(['items' => $data]);
    }

    /**
     * GET /api/frontend/service-pages/slugs
     *
     * Just the slugs, for generateStaticParams(). Kept separate from index()
     * so the frontend can enumerate routes without pulling every section body.
     */
    public function slugs(): JsonResponse
    {
        $slugs = $this->cached('service_pages:slugs', function () {
            return $this->publishedQuery()
                ->with('slug')
                ->get()
                ->map(fn (Service $service) => $service->slug?->slug)
                ->filter()
                ->values()
                ->all();
        });

        return $this->success(['items' => $slugs]);
    }

    /**
     * GET /api/frontend/service-pages/{slug}
     *
     * One page, with its ordered sections.
     *
     * A slug that never existed is a 404; a page that exists but is no longer
     * published is a 410, which is the distinction the rest of this API makes
     * and the one search engines act on differently.
     */
    public function show(string $slug): JsonResponse
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) {
            return $this->notFound('Service page not found.');
        }

        $data = $this->cached("service_page_content:{$slug}", function () use ($slug) {
            $service = $this->findBySlug(Service::class, $slug);

            if (! $service) {
                return ['_missing' => true];
            }

            if (! $service->status) {
                return ['_gone' => true];
            }

            $service->load($this->pageRelations());

            return $this->formatPage($service) ?? ['_gone' => true];
        });

        if (is_array($data) && ! empty($data['_missing'])) {
            return $this->notFound('Service page not found.');
        }

        if (! $data || (is_array($data) && ! empty($data['_gone']))) {
            return $this->gone('This service page is no longer available.');
        }

        return $this->success($data);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function publishedQuery()
    {
        return Service::query()
            ->where('status', true)
            ->whereHas('slug')
            ->whereHas('contentBlocks', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('type', FrontendSectionRegistry::TYPES))
            ->orderBy('title');
    }

    private function pageRelations(): array
    {
        return [
            'slug',
            'categories',
            'seo',
            'images',
            'contentBlocks' => fn ($query) => $query
                ->where('is_active', true)
                ->with('fields')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ];
    }

    /**
     * Build the page payload, or null when there is nothing to render.
     *
     * A service with no sections is not a page — returning an empty shell would
     * give the frontend a blank route to render and a slug to advertise in
     * generateStaticParams(), so it is treated as unavailable instead.
     */
    private function formatPage(Service $service): ?array
    {
        $slug = $service->slug?->slug;

        if (! $slug) {
            return null;
        }

        $sections = $this->formatSections($service->contentBlocks);

        if ($sections === []) {
            return null;
        }

        $path = "/service/{$slug}";
        $image = $this->formatFirstImage($service, ['banner_desktop', 'banner', 'thumbnail', 'default']);

        $isAmazon = $service->relationLoaded('categories')
            ? $service->categories->contains(fn ($cat) => ($cat->category_type ?? '') === 'amazon')
            : $service->categories()->where('category_type', 'amazon')->exists();
        $seoTemplate = $isAmazon ? 'amazon_service' : 'service';

        return [
            'id' => $service->id,
            'slug' => $slug,
            'status' => 'published',
            'template' => self::TEMPLATE,
            'title' => $service->title,
            'sections' => $sections,
            'seo' => FrontendSeoPresenter::present(
                $seoTemplate,
                $service->seoApi(),
                [
                    'title' => $service->title,
                    'description' => (string) HtmlCleaner::plainText(
                        $service->short_description ?: $service->card_description
                    ),
                    'url' => SeoDefaults::urlFor($path),
                    'canonical' => $path,
                    'image' => (string) ($image['url'] ?? ''),
                ],
                [
                    'schemaGraph' => FrontendSchemaBuilder::page(
                        self::TEMPLATE,
                        $service->meta_title ?: $service->title,
                        (string) ($service->meta_description ?: HtmlCleaner::plainText($service->short_description ?: $service->card_description)),
                        SeoDefaults::urlFor($path),
                        $sections,
                        $image['url'] ?? null,
                    ),
                ]
            ),
        ];
    }

    /**
     * @param  Collection<int, ContentBlock>  $blocks
     */
    private function formatSections(Collection $blocks): array
    {
        return $blocks
            // A section the frontend has no component for would render as a
            // console warning and a gap, so it never leaves the API.
            //
            // Emptiness is deliberately not a filter here. Several of these
            // components carry no editable data at all — the logo strip and the
            // standing CTA render fixed markup — so an empty `data` is their
            // normal state, and dropping them would silently remove a band from
            // the middle of the page. What makes a section appear is an editor
            // placing it and leaving it active; that is the only test applied.
            ->filter(fn (ContentBlock $block) => FrontendSectionRegistry::supports($block->type))
            ->map(fn (ContentBlock $block) => [
                'id' => $block->section_key ?: "{$block->type}-{$block->id}",
                'type' => $block->type,
                'enabled' => true,
                // Always an object, never an array — the same rule the block
                // `options` payload follows. A section with no data is an empty
                // PHP array, which json_encode would otherwise write as `[]`,
                // so `data` would arrive as an object on most sections and an
                // array on the handful that take no configuration.
                'data' => (object) $this->sectionData($block),
            ])
            ->values()
            ->all();
    }

    /**
     * A section's payload.
     *
     * `data` is the page-section shape and carries the whole payload. The four
     * original ContentBlock components predate it and describe themselves with
     * heading/intro/items/options, so those are folded into the same object
     * rather than being exposed as a second, parallel section shape.
     */
    private function sectionData(ContentBlock $block): array
    {
        if ($block->hasData()) {
            return FrontendSectionPresenter::data($block);
        }

        return array_filter([
            'heading' => $block->heading,
            'intro' => $block->intro,
            'items' => $block->usableItems(),
            'options' => $block->options ?? [],
        ], fn ($value) => $value !== null && $value !== '' && $value !== []);
    }

}
