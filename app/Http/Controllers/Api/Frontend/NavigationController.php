<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Location;
use App\Models\NavigationMenu;
use App\Models\NavigationMenuItem;
use App\Models\Page;
use App\Models\Service;
use App\Support\HtmlCleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;

class NavigationController extends BaseFrontendController
{
    /**
     * GET /api/frontend/navigation
     *
     * Returns header and footer navigation data with auto-resolved URLs for
     * linked content items (services, categories, locations, blogs, pages).
     * All linked-item lookups are batched — zero N+1 queries regardless of
     * how many items the navigation contains.
     */
    public function index(): JsonResponse
    {
        $data = $this->cached('navigation', function () {
            return [
                'header' => $this->buildHeader(),
                'footer' => $this->buildFooter(),
            ];
        });

        // Sitewide schema is resolved OUTSIDE the navigation cache so an SEO
        // Configurator save (which clears the SiteSetting cache) is reflected
        // immediately — the client renders this once in the root layout so the
        // Organization + WebSite graph appears on every page.
        $data['seo'] = $this->sitewideSeo();

        return $this->success($data);
    }

    /**
     * The admin-managed sitewide JSON-LD (default_schema) — decoded, honouring
     * the sitewide "Schema Output" toggle. Returns ['schema' => array|null].
     */
    private function sitewideSeo(): array
    {
        $settings = \App\Models\SiteSetting::allCached();
        $enabled  = filter_var($settings['default_enable_schema'] ?? '1', FILTER_VALIDATE_BOOLEAN);

        $schema = null;
        $raw    = $settings['default_schema'] ?? null;

        if ($enabled && is_string($raw) && trim($raw) !== '') {
            $decoded = json_decode($raw, true);
            $schema  = json_last_error() === JSON_ERROR_NONE ? $decoded : null;
        }

        return ['schema' => $schema];
    }

    // ── Header ────────────────────────────────────────────────────────────────

    private function buildHeader(): array
    {
        $menu = NavigationMenu::where('location', 'header')->first();
        if (! $menu) {
            return [];
        }

        $meta = $menu->meta ?? [];

        $rootItems = NavigationMenuItem::where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('status', true)
            ->where('item_type', 'nav_link')
            ->with(['children' => fn ($q) => $q->where('status', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Batch-resolve URLs for all items + all children in ONE pass
        $resolvedUrls = $this->batchResolveUrls(
            $rootItems->flatMap(fn ($i) => collect([$i])->merge($i->children))
        );

        $headerLinks = $rootItems->map(function (NavigationMenuItem $item) use ($resolvedUrls) {
            $itemMeta    = $item->meta ?? [];
            $hasMegaMenu = ! empty($itemMeta['mega_title'])
                || ! empty($itemMeta['mega_description'])
                || $item->children->isNotEmpty();

            $mapped = [
                'href' => $resolvedUrls[$item->id] ?? '#',
                'text' => $item->title,
            ];

            if ($hasMegaMenu) {
                $subLinks = $item->children->map(function (NavigationMenuItem $sub) use ($resolvedUrls) {
                    $subMeta = $sub->meta ?? [];
                    $result  = [
                        'href' => $resolvedUrls[$sub->id] ?? '#',
                        'text' => $sub->title,
                    ];
                    if (! empty($subMeta['icon_src'])) {
                        $result['icon'] = [
                            'src' => $subMeta['icon_src'],
                            'alt' => $subMeta['icon_alt'] ?? '',
                        ];
                    }
                    return $result;
                })->values()->all();

                $mapped['megaMenu'] = [
                    'title'       => $itemMeta['mega_title']       ?? null,
                    'description' => $itemMeta['mega_description'] ?? null,
                    'subLinks'    => $subLinks,
                ];

                if (! empty($itemMeta['mega_detail'])) {
                    $mapped['detail'] = $itemMeta['mega_detail'];
                }
                if (! empty($itemMeta['mega_image_src'])) {
                    $mapped['img'] = [
                        'src' => $itemMeta['mega_image_src'],
                        'alt' => $itemMeta['mega_image_alt'] ?? '',
                    ];
                }
            }

            return $mapped;
        })->values()->all();

        $result = [];

        if (! empty($meta['logo_src'])) {
            $result['logo'] = [
                'src' => $meta['logo_src'],
                'alt' => $meta['logo_alt'] ?? 'logo',
            ];
        }

        $result['headerLinks'] = $headerLinks;

        if (! empty($meta['cta_text'])) {
            $result['cta'] = [
                'href' => $meta['cta_href'] ?? '#',
                'text' => $meta['cta_text'],
            ];
        }

        return $result;
    }

    // ── Footer ────────────────────────────────────────────────────────────────

    private function buildFooter(): array
    {
        $menu = NavigationMenu::where('location', 'footer')->first();
        if (! $menu) {
            return [];
        }

        $meta = $menu->meta ?? [];

        $rootItems = NavigationMenuItem::where('menu_id', $menu->id)
            ->whereNull('parent_id')
            ->where('status', true)
            ->with(['children' => fn ($q) => $q->where('status', true)->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        // Batch-resolve URLs for all items + all children
        $resolvedUrls = $this->batchResolveUrls(
            $rootItems->flatMap(fn ($i) => collect([$i])->merge($i->children))
        );

        $locationsGroup = $rootItems->firstWhere('item_type', 'locations_group');
        $groups         = $rootItems->where('item_type', 'group')->values();
        $otherLinks     = $rootItems->where('item_type', 'other_link')->values();

        $result = [];

        if (! empty($meta['logo_src'])) {
            $result['logo'] = [
                'src' => $meta['logo_src'],
                'alt' => $meta['logo_alt'] ?? 'footer-logo',
            ];
        }

        if (! empty($meta['detail'])) {
            $result['detail'] = $meta['detail'];
        }

        if ($locationsGroup) {
            $heading = ['text' => $locationsGroup->title];
            $groupHref = $resolvedUrls[$locationsGroup->id] ?? null;
            if ($groupHref && $groupHref !== '#') {
                $heading['href'] = $groupHref;
            }
            $result['locations'] = [
                'heading' => $heading,
                'links'   => $locationsGroup->children->map(fn ($l) => [
                    'href' => $resolvedUrls[$l->id] ?? '#',
                    'text' => $l->title,
                ])->values()->all(),
            ];
        }

        if ($groups->isNotEmpty()) {
            $result['innerPages'] = $groups->map(function (NavigationMenuItem $group) use ($resolvedUrls) {
                $label = ['text' => $group->title];
                $groupHref = $resolvedUrls[$group->id] ?? null;
                if ($groupHref && $groupHref !== '#') {
                    $label['href'] = $groupHref;
                }
                return [
                    'label' => $label,
                    'links' => $group->children->map(fn ($l) => [
                        'href' => $resolvedUrls[$l->id] ?? '#',
                        'text' => $l->title,
                    ])->values()->all(),
                ];
            })->values()->all();
        }

        if (! empty($meta['rights'])) {
            $result['rights'] = $meta['rights'];
        }

        if ($otherLinks->isNotEmpty()) {
            $result['otherPages'] = $otherLinks->map(fn ($l) => [
                'href' => $resolvedUrls[$l->id] ?? '#',
                'text' => $l->title,
            ])->values()->all();
        }

        if (! empty($meta['info_label']) || ! empty($meta['info_text'])) {
            $result['info'] = [
                'label' => $meta['info_label'] ?? '',
                'text'  => $meta['info_text']  ?? '',
            ];
        }

        // Google review badge (bottom bar). Admin-editable; the whole badge
        // is omitted when no score is set, so it never renders empty.
        if (! empty($meta['google_rating'])) {
            $result['rating'] = [
                'score' => (string) $meta['google_rating'],
                'url'   => $meta['google_review_url'] ?? '',
            ];
        }

        // Social links are env-driven on the client (company.config.js) —
        // no longer part of the navigation payload.

        return $result;
    }

    // ── Batch URL resolution (zero N+1) ───────────────────────────────────────

    /**
     * Resolves URLs for all navigation items in a single pass.
     * Makes at most ONE query per link_type (service/category/location/blog/page).
     * Custom items use their stored href directly — no query needed.
     *
     * Returns: [nav_item_id => resolved_url_string]
     *
     * Fallback chain for linked items:
     *   1. Resolved URL from the linked content item's slug
     *   2. Stored custom href (if the linked item no longer exists)
     *   3. '#' (safe empty fallback)
     */
    private function batchResolveUrls(Collection $items): array
    {
        $resolved = [];

        // ── Seed custom / missing items immediately ──────────────────────────
        foreach ($items as $item) {
            if ($item->isCustomLink()) {
                $resolved[$item->id] = $item->href ?: '#';
            }
        }

        $linked = $items->filter(fn ($i) => ! $i->isCustomLink() && $i->linkable_id);

        if ($linked->isEmpty()) {
            return $resolved;
        }

        $byType = $linked->groupBy('link_type');

        // ── Services ─────────────────────────────────────────────────────────
        if ($byType->has(NavigationMenuItem::LINK_TYPE_SERVICE)) {
            $ids      = $byType[NavigationMenuItem::LINK_TYPE_SERVICE]->pluck('linkable_id')->unique();
            $services = Service::with([
                'slug',
                'categories' => fn ($q) => $q
                    ->where('status', true)
                    ->where('type', 'service')
                    ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES)
                    ->with('slug')
                    ->orderByRaw("CASE WHEN category_type = 'service' THEN 0 ELSE 1 END"),
            ])
                ->whereIn('id', $ids)
                ->where('status', true)
                ->get()
                ->keyBy('id');

            foreach ($byType[NavigationMenuItem::LINK_TYPE_SERVICE] as $navItem) {
                $service = $services->get($navItem->linkable_id);
                $resolved[$navItem->id] = $service
                    ? ($this->resolveServiceUrl($service) ?: $navItem->href ?: '#')
                    : ($navItem->href ?: '#');
            }
        }

        // ── Categories ───────────────────────────────────────────────────────
        if ($byType->has(NavigationMenuItem::LINK_TYPE_CATEGORY)) {
            $ids        = $byType[NavigationMenuItem::LINK_TYPE_CATEGORY]->pluck('linkable_id')->unique();
            $categories = Category::with('slug')
                ->whereIn('id', $ids)
                ->where('status', true)
                ->get()
                ->keyBy('id');

            foreach ($byType[NavigationMenuItem::LINK_TYPE_CATEGORY] as $navItem) {
                $cat = $categories->get($navItem->linkable_id);
                $resolved[$navItem->id] = $cat
                    ? ($this->resolveCategoryUrl($cat) ?: $navItem->href ?: '#')
                    : ($navItem->href ?: '#');
            }
        }

        // ── Locations ─────────────────────────────────────────────────────────
        if ($byType->has(NavigationMenuItem::LINK_TYPE_LOCATION)) {
            $ids       = $byType[NavigationMenuItem::LINK_TYPE_LOCATION]->pluck('linkable_id')->unique();
            $locations = Location::with('slug')
                ->whereIn('id', $ids)
                ->where('status', true)
                ->get()
                ->keyBy('id');

            foreach ($byType[NavigationMenuItem::LINK_TYPE_LOCATION] as $navItem) {
                $loc = $locations->get($navItem->linkable_id);
                $resolved[$navItem->id] = $loc
                    ? ($this->resolveLocationUrl($loc) ?: $navItem->href ?: '#')
                    : ($navItem->href ?: '#');
            }
        }

        // ── Blogs ─────────────────────────────────────────────────────────────
        if ($byType->has(NavigationMenuItem::LINK_TYPE_BLOG)) {
            $ids   = $byType[NavigationMenuItem::LINK_TYPE_BLOG]->pluck('linkable_id')->unique();
            $blogs = Blog::with('slug')
                ->whereIn('id', $ids)
                ->where('status', true)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->get()
                ->keyBy('id');

            foreach ($byType[NavigationMenuItem::LINK_TYPE_BLOG] as $navItem) {
                $blog = $blogs->get($navItem->linkable_id);
                $resolved[$navItem->id] = $blog
                    ? ($this->resolveBlogUrl($blog) ?: $navItem->href ?: '#')
                    : ($navItem->href ?: '#');
            }
        }

        // Pages
        if ($byType->has(NavigationMenuItem::LINK_TYPE_PAGE)) {
            $ids = $byType[NavigationMenuItem::LINK_TYPE_PAGE]->pluck('linkable_id')->unique();
            $pages = Page::with('slug')
                ->whereIn('id', $ids)
                ->where('status', true)
                ->get()
                ->keyBy('id');

            foreach ($byType[NavigationMenuItem::LINK_TYPE_PAGE] as $navItem) {
                $page = $pages->get($navItem->linkable_id);
                $resolved[$navItem->id] = $page
                    ? ($this->resolvePageUrl($page) ?: $navItem->href ?: '#')
                    : ($navItem->href ?: '#');
            }
        }

        // Resolver-built URLs are already rooted, but any value that fell back
        // to a stored custom href is whatever an admin typed. A bare "contact-us"
        // is browser-relative and resolves against the page it is rendered on,
        // so the same menu item would point at /contact-us in the header of the
        // home page and /locations/contact-us on a location page. Every other
        // link in the API passes through safeUrl; the menu was the one path that
        // did not. Running the whole map through it here covers the custom-link
        // seeds and each link_type's "?: $navItem->href" fallback in one place.
        return array_map(
            fn (string $url): string => HtmlCleaner::safeUrl($url) ?? '#',
            $resolved
        );
    }

    // ── Per-type URL resolvers ─────────────────────────────────────────────────

    private function resolveServiceUrl(Service $service): string
    {
        $serviceSlug = $service->slug?->slug;
        if (! $serviceSlug) {
            return '';
        }

        // Use the first active category (service preferred over sector)
        $category = $service->categories
            ->first(fn ($c) => $c->status && $c->type === 'service' && $c->category_type === 'service')
            ?? $service->categories
            ->first(fn ($c) => $c->status && $c->type === 'service');

        if (! $category) {
            return '';
        }

        $catSlug = $category->slug?->slug;

        return $category->category_type === 'sector'
            ? "/sectors/{$serviceSlug}"
            : ($catSlug ? "/{$catSlug}/{$serviceSlug}" : '');
    }

    private function resolveCategoryUrl(Category $category): string
    {
        $slug = $category->slug?->slug;
        return $slug ? "/{$slug}" : '';
    }

    private function resolveLocationUrl(Location $location): string
    {
        $slug = $location->slug?->slug;
        return $slug ? "/locations/{$slug}" : '';
    }

    private function resolveBlogUrl(Blog $blog): string
    {
        $slug = $blog->slug?->slug;
        return $slug ? "/blogs/{$slug}" : '';
    }

    private function resolvePageUrl(Page $page): string
    {
        if ($page->page_type === 'home') {
            return '/';
        }

        $slug = $page->slug?->slug;
        return $slug ? "/{$slug}" : ($page->page_type ? "/{$page->page_type}" : '');
    }
}
