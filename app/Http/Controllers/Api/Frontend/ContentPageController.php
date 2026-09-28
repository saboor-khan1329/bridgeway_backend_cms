<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\ContentBlock;
use App\Models\ContentPage;
use App\Support\FrontendSectionRegistry;
use App\Support\FrontendSectionPresenter;
use App\Support\FrontendSchemaBuilder;
use App\Support\FrontendSeoPresenter;
use App\Support\SeoDefaults;
use App\Support\StaticPageOwnership;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;

/**
 * The frontend's section-driven content pages.
 *
 * These are the Amazon, development, SEO and company pages the Next.js site
 * previously read from JSON files on disk. They are built the same way service
 * pages are — an ordered list of ContentBlock rows, each carrying one
 * component's payload — so this controller is ServicePageContentController's
 * twin, differing only in which model owns the sections and in where the
 * metadata comes from.
 *
 * Structured SEO fields are presented in the frontend's existing metadata
 * vocabulary. Authored titles, canonicals and OG values are preserved; schema
 * markup is generated on the backend from the same CMS records and sections.
 */
class ContentPageController extends BaseFrontendController
{
    /** Matches a frontend route segment. */
    private const SLUG_PATTERN = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    /**
     * GET /api/frontend/pages
     *
     * Every published page, in full — one request for a whole site's worth of
     * content rather than one per page during a build.
     */
    public function index(): JsonResponse
    {
        $data = $this->cached('content_pages:index', function () {
            return $this->publishedQuery()
                ->with($this->pageRelations())
                ->get()
                ->map(fn (ContentPage $page) => $this->formatPage($page))
                ->filter()
                ->values()
                ->all();
        });

        return $this->success(['items' => $data]);
    }

    /**
     * GET /api/frontend/pages/slugs
     *
     * Just the slugs, for generateStaticParams().
     */
    public function slugs(): JsonResponse
    {
        $slugs = $this->cached('content_pages:slugs', function () {
            return $this->publishedQuery()
                ->pluck('slug')
                ->filter()
                ->values()
                ->all();
        });

        return $this->success(['items' => $slugs]);
    }

    /**
     * GET /api/frontend/pages/{slug}
     *
     * One page with its ordered sections. A slug that never existed is a 404;
     * one that existed and is no longer published is a 410 — the same
     * distinction the rest of this API draws.
     */
    public function show(string $slug): JsonResponse
    {
        if (! preg_match(self::SLUG_PATTERN, $slug) || in_array($slug, StaticPageOwnership::SLUGS, true)) {
            return $this->notFound('Page not found.');
        }

        $data = $this->cached("content_page:{$slug}", function () use ($slug) {
            $page = ContentPage::query()
                ->where('is_cms_managed', true)
                ->where('template', '!=', 'global')
                ->where('slug', $slug)
                ->first();

            if (! $page) {
                return ['_missing' => true];
            }

            if (! $page->status) {
                return ['_gone' => true];
            }

            $page->load($this->pageRelations());

            return $this->formatPage($page) ?? ['_gone' => true];
        });

        if (is_array($data) && ! empty($data['_missing'])) {
            return $this->notFound('Page not found.');
        }

        if (! $data || (is_array($data) && ! empty($data['_gone']))) {
            return $this->gone('This page is no longer available.');
        }

        return $this->success($data);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function publishedQuery()
    {
        return ContentPage::query()
            ->whereNotIn('slug', StaticPageOwnership::SLUGS)
            ->where('is_cms_managed', true)
            ->where('template', '!=', 'global')
            ->where('status', true)
            ->whereHas('contentBlocks', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('type', FrontendSectionRegistry::TYPES))
            ->orderBy('sort_order')
            ->orderBy('title');
    }

    /** Reusable sections have no route or page SEO of their own. */
    public function globalSections(string $slug): JsonResponse
    {
        if (! preg_match(self::SLUG_PATTERN, $slug)) return $this->notFound();
        $data = $this->cached("global_sections:{$slug}", function () use ($slug) {
            $page = ContentPage::where('slug', $slug)->where('template', 'global')
                ->where('is_cms_managed', true)->where('status', true)
                ->with($this->pageRelations())->first();
            return $page ? ['sections' => $this->formatSections($page->contentBlocks)] : ['_missing' => true];
        });
        return isset($data['_missing']) ? $this->notFound() : $this->success($data);
    }

    private function pageRelations(): array
    {
        return [
            'images',
            'seo',
            'contentBlocks' => fn ($query) => $query
                ->where('is_active', true)
                ->with('fields')
                ->orderBy('sort_order')
                ->orderBy('id'),
        ];
    }

    /**
     * The page payload, or null when there is nothing to render.
     *
     * A page with no sections is not a page: returning an empty shell would
     * give the frontend a blank route to render and a slug to advertise in
     * generateStaticParams(), so it counts as unavailable instead.
     */
    private function formatPage(ContentPage $page): ?array
    {
        $sections = $this->formatSections($page->contentBlocks);

        if ($sections === []) {
            return null;
        }

        $path = $page->slug === 'home' ? '/' : '/'.$page->slug;
        $canonical = SeoDefaults::urlFor($path);
        $firstSection = $sections[0]['data'] ?? [];
        $description = (string) (data_get($firstSection, 'description') ?: data_get($firstSection, 'detail') ?: '');
        $image = $this->formatFirstImage($page, ['banner_desktop', 'banner', 'thumbnail', 'default']);
        $template = match ($page->template) {
            'amazon-service' => 'amazon_service',
            'service', 'service-two' => 'service',
            default => 'static',
        };

        return [
            'id' => $page->id,
            'slug' => $page->slug,
            'status' => 'published',
            'template' => $page->template,
            'title' => $page->title,
            'sections' => $sections,
            // Always an object. An empty PHP array would serialise as `[]`,
            // and the frontend's mapper tests this for plain-object-ness
            // before reading a title out of it.
            'seo' => FrontendSeoPresenter::present(
                $template,
                $page->seoApi(),
                [
                    'title' => $page->title,
                    'description' => $description,
                    'url' => $canonical,
                    'canonical' => $path,
                    'image' => (string) ($image['url'] ?? ''),
                ],
                [
                    'schemaGraph' => FrontendSchemaBuilder::page(
                        $page->template,
                        $page->meta_title ?: $page->title,
                        $page->meta_description ?: $description,
                        $canonical,
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
            // console warning and a gap in the page, so it never leaves here.
            //
            // Emptiness is deliberately not a filter. Several components carry
            // no editable payload at all — logo strips, standing CTAs — so an
            // empty `data` is their normal state, and dropping them would
            // silently remove a band from the middle of a page.
            ->filter(fn (ContentBlock $block) => FrontendSectionRegistry::supports($block->type))
            ->map(fn (ContentBlock $block) => [
                'id' => $block->section_key ?: "{$block->type}-{$block->id}",
                'type' => $block->type,
                'enabled' => true,
                'data' => (object) FrontendSectionPresenter::data($block),
            ])
            ->values()
            ->all();
    }
}
