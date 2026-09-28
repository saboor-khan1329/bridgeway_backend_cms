<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Location;
use App\Models\Page;
use App\Support\SeoDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationsController extends BaseFrontendController
{
    /**
     * GET /api/frontend/locations
     * GET /api/frontend/locations?page=2[&per_page=24]
     *
     * Returns root (parent) locations for the locations listing page.
     *
     * Pagination is opt-in and backwards compatible: with no `page` query
     * parameter the full list is returned exactly as before (no `meta` key),
     * so existing consumers are unaffected. Passing `page` switches to a
     * paginated response with the same `meta` shape the blogs endpoint uses.
     *
     * Note the cache key includes page/per_page — without that, page 2 would
     * be served the cached payload of page 1.
     */
    public function index(Request $request): JsonResponse
    {
        $paginate = $request->query('page') !== null || $request->query('per_page') !== null;

        if (! $paginate) {
            return $this->success(
                $this->cached('locations_index', fn () => $this->buildListing())
            );
        }

        $page    = max(1, (int) $request->query('page', 1));
        $perPage = $this->resolvePerPage($request);

        return $this->success($this->cached(
            "locations_index:page:{$page}:per:{$perPage}",
            fn () => $this->buildListing($page, $perPage)
        ));
    }

    /**
     * Clamp per_page to the configured bounds so a caller cannot request an
     * unbounded page size.
     */
    private function resolvePerPage(Request $request): int
    {
        $default = (int) config('frontend.pagination.default_per_page', 12);
        $max     = (int) config('frontend.pagination.max_per_page', 100);
        $perPage = (int) $request->query('per_page', $default);

        return max(1, min($perPage, $max));
    }

    /**
     * Builds the listing payload. When $page is null the full unpaginated
     * list is returned and no `meta` key is present (legacy shape).
     */
    private function buildListing(?int $page = null, ?int $perPage = null): array
    {
        $query = Location::query()
            ->where('status', true)
            ->whereNull('parent_id')
            ->with(['slug', 'images'])
            ->orderBy('title');

        $paginator = $page === null
            ? null
            : $query->paginate($perPage, ['*'], 'page', $page);

        $locations = $paginator === null
            ? $query->get()
            : collect($paginator->items());

        $payload = [
            'hero' => [
                'title' => 'Security Service Locations Across the UK',
                'description' => 'Our vision and mission is to become the best provider of facilities services for colleagues, customers and communities by making people and places the best they can be.',
            ],
            'locations' => $locations->map(fn ($l) => $this->formatLocationCard($l))->values()->all(),
            'testimonials' => $this->locationsListingTestimonials(),
            'seo' => SeoDefaults::for('locations', $this->locationsListingSeo(), [
                'title' => 'Security Service Locations Across the UK',
                'description' => 'Bridgeway Digital service coverage and office locations.',
                'url' => SeoDefaults::urlFor('/locations'),
                'image' => '',
            ]),
        ];

        if ($paginator !== null) {
            $payload['meta'] = [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ];
        }

        return $payload;
    }

    private function locationsListingSeo(): ?array
    {
        return $this->locationsListingPage()?->seoApi();
    }

    /**
     * Testimonials attached to the locations-index Page record — the same
     * "attach to a Page" mechanism used for the Home page section, just for
     * whichever Page row represents /locations (page_type location|locations,
     * or slug 'locations').
     */
    private function locationsListingTestimonials(): array
    {
        $page = $this->locationsListingPage();

        return $page
            ? $this->formatTestimonialSection($page, $page->testimonials()->where('status', true)->get())
            : ['heading' => null, 'sub_heading' => null, 'items' => []];
    }

    private function locationsListingPage(): ?Page
    {
        return Page::query()
            ->where('status', true)
            ->where(function ($query) {
                $query->whereIn('page_type', ['location', 'locations'])
                    ->orWhereHas('slug', fn ($slug) => $slug->where('slug', 'locations'));
            })
            ->with('seo')
            ->first();
    }
}
