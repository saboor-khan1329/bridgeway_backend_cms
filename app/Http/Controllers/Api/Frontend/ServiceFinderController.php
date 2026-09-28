<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\FinderArea;
use App\Models\FinderSearch;
use App\Models\FinderSite;
use App\Models\FinderUsageDaily;
use App\Models\Service;
use App\Services\FinderCoverageService;
use App\Services\FinderUsageService;
use App\Support\HtmlCleaner;
use App\Support\ServiceFinderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public coverage data for the home-page Service Finder.
 *
 * The whole coverage map ships in one cached payload — roughly 90 areas and
 * (as of the site-level drill-down) ~900 sites — so the browser can match a
 * postcode, filter services and move the map without another round trip, and
 * without an address-API call. That is both the fastest experience and the
 * cheapest one.
 *
 * Client site names are never published — nothing in this payload can
 * identify a specific client's premises. Individual site *coordinates* are
 * published (rounded to ~11m, postcode-precision, the same precision the
 * area centroids already use) so the map can cluster down from an area count
 * to individual pins as a visitor zooms in; a bare position with no name and
 * no address attached reveals no more than the postcode search box already
 * would if a visitor typed that postcode in.
 */
class ServiceFinderController extends BaseFrontendController
{
    public function __construct(
        protected FinderCoverageService $coverage,
        protected FinderUsageService $usage,
    ) {
    }

    /** GET /api/frontend/service-finder */
    public function index(): JsonResponse
    {
        // Environment kill switch. Answers successfully with the feature
        // marked off rather than erroring, so the website simply omits the
        // section instead of logging a failed fetch on every render.
        if (! config('service_finder.enabled', true)) {
            return $this->success([
                'settings' => ['enabled' => false],
                'services' => [],
                'areas' => [],
                'totals' => ['areas' => 0, 'sites' => 0],
            ]);
        }

        $payload = $this->cached('service_finder:coverage', fn () => $this->buildPayload());

        return $this->success($payload);
    }

    /** POST /api/frontend/service-finder/searches */
    public function logSearch(Request $request): JsonResponse
    {
        if (! config('service_finder.enabled', true) || ! ServiceFinderSettings::get('search_logging_enabled')) {
            return response()->json(['success' => true, 'data' => null], 202);
        }

        $validated = $request->validate([
            'query' => 'required|string|max:190',
            'postcode' => 'nullable|string|max:12',
            'matched_area_id' => 'nullable|integer|exists:finder_areas,id',
            'service_id' => 'nullable|integer|exists:services,id',
            'match_type' => 'nullable|string|in:'.implode(',', FinderSearch::MATCH_TYPES),
            'results_count' => 'nullable|integer|min:0|max:500',
        ]);

        FinderSearch::create([
            'query' => mb_substr(trim($validated['query']), 0, 190),
            'postcode' => $validated['postcode'] ?? null,
            'matched_area_id' => $validated['matched_area_id'] ?? null,
            'service_id' => $validated['service_id'] ?? null,
            'match_type' => $validated['match_type'] ?? FinderSearch::MATCH_NONE,
            'results_count' => (int) ($validated['results_count'] ?? 0),
            // Hashed with the app key so repeat-visitor analysis stays
            // possible without storing an identifiable address.
            'ip_hash' => hash('sha256', ($request->header('CF-Connecting-IP') ?: $request->ip()).config('app.key')),
            'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
        ]);

        return response()->json(['success' => true, 'data' => null], 201);
    }

    /**
     * POST /api/frontend/service-finder/usage
     *
     * Fire-and-forget, same as logSearch: a beacon the map and the search
     * box call once for each of the two actions that actually cost money —
     * never something a visitor waits on or sees fail.
     */
    public function logUsage(Request $request): JsonResponse
    {
        if (config('service_finder.enabled', true)) {
            $validated = $request->validate([
                'event' => 'required|string|in:'.implode(',', array_keys(FinderUsageDaily::EVENTS)),
            ]);

            $this->usage->record($validated['event']);
        }

        return response()->json(['success' => true, 'data' => null], 202);
    }

    /**
     * Everything the finder needs in one document.
     *
     * @return array<string, mixed>
     */
    protected function buildPayload(): array
    {
        $areas = FinderArea::query()
            ->active()
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->with([
                'featuredService.slug',
                'featuredService.categories.slug',
                'location.slug',
                'services' => fn ($query) => $query
                    ->where('services.status', true)
                    ->wherePivot('is_active', true)
                    ->with(['slug', 'categories.slug']),
            ])
            ->orderBy('sort_order')
            ->orderByDesc('active_sites_count')
            ->get();

        // The payload is normalised: each service is described once in the
        // catalog, and an area only carries its id, its own site count and
        // any copy that genuinely differs. Repeating the full service object
        // per area inflated this response roughly six-fold for no benefit,
        // and the design's result cards use no imagery at all.
        $catalog = [];

        $mappedAreas = $areas->map(function (FinderArea $area) use (&$catalog) {
            $services = $area->services->map(function (Service $service) use ($area, &$catalog) {
                $pivot = $service->pivot;
                $default = HtmlCleaner::plainText(
                    $this->coverage->resolveDescription(null, $service->card_description)
                );

                if (! isset($catalog[$service->id])) {
                    $card = $this->formatServiceCard($service);

                    $catalog[$service->id] = [
                        'id' => $service->id,
                        'title' => $card['title'],
                        'url' => $card['url'],
                        'description' => $default,
                    ];
                }

                $override = HtmlCleaner::plainText((string) $pivot->description);
                $related = $this->coverage->decodeRelated($pivot->related_service_ids);

                return array_filter([
                    'id' => $service->id,
                    'sites_count' => (int) $pivot->sites_count,
                    'featured' => (bool) $pivot->is_featured
                        || (int) $service->id === (int) $area->featured_service_id,
                    // Only sent when an operator has written area-specific copy.
                    'description' => $override !== '' && $override !== $default ? $override : null,
                    'related_ids' => $related !== [] ? $related : null,
                ], static fn ($value) => $value !== null);
            })->values()->all();

            return array_filter([
                'id' => $area->id,
                'name' => $area->name,
                'slug' => $area->slug,
                'region' => $area->region ?: null,
                'intro' => HtmlCleaner::plainText($area->intro) ?: null,
                // Four decimals is ~11 m — ample for a city pin, and it keeps
                // the payload small.
                'lat' => round((float) $area->latitude, 4),
                'lng' => round((float) $area->longitude, 4),
                'prefixes' => array_values((array) $area->postcode_areas),
                'sites_count' => (int) $area->active_sites_count,
                'location_url' => $area->location?->slug?->slug
                    ? '/locations/'.$area->location->slug->slug
                    : null,
                'featured_id' => $area->featured_service_id ? (int) $area->featured_service_id : null,
                'services' => $services,
            ], static fn ($value) => $value !== null);
        })->values()->all();

        // Everything mapped to an area demonstrably has coverage.
        foreach ($catalog as $id => $entry) {
            $catalog[$id]['has_coverage'] = true;
        }

        // Every other active service joins the list too. A visitor has to be
        // able to ask for something we offer but have not plotted yet — that
        // enquiry still converts, and it records demand somewhere we have no
        // sites, which is worth knowing. The map, its colour key and the
        // results list all key off sites rather than this catalog, so nothing
        // uncovered is drawn as though it were coverage.
        Service::query()
            ->where('status', true)
            ->whereNotIn('id', array_keys($catalog) ?: [0])
            ->with(['slug', 'categories.slug'])
            ->get()
            ->each(function (Service $service) use (&$catalog) {
                $card = $this->formatServiceCard($service);

                $catalog[$service->id] = [
                    'id' => $service->id,
                    'title' => $card['title'],
                    'url' => $card['url'],
                    'description' => HtmlCleaner::plainText(
                        $this->coverage->resolveDescription(null, $service->card_description)
                    ),
                    'has_coverage' => false,
                ];
            });

        // Sort the dropdown the way a visitor reads it, not by discovery order.
        $catalog = array_values($catalog);
        usort($catalog, static fn ($a, $b) => strcmp((string) $a['title'], (string) $b['title']));

        return [
            'settings' => $this->settingsPayload(),
            'services' => $catalog,
            'areas' => $mappedAreas,
            'sites' => $this->sitesPayload(),
            'totals' => [
                'areas' => count($mappedAreas),
                'sites' => array_sum(array_column($mappedAreas, 'sites_count')),
            ],
        ];
    }

    /**
     * The individual pins the map declusters into on zoom. Deliberately thin
     * — id, area, service and a rounded position — so ~900 rows add only a
     * few KB to a payload that is cached for an hour regardless. See the
     * class docblock for why a bare position is safe to publish here.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function sitesPayload(): array
    {
        return FinderSite::query()
            ->where('is_active', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereNotNull('finder_area_id')
            ->select(['id', 'finder_area_id', 'service_id', 'latitude', 'longitude', 'outcode'])
            ->get()
            ->map(static fn (FinderSite $site) => array_filter([
                'id' => $site->id,
                'area_id' => $site->finder_area_id,
                'service_id' => $site->service_id,
                // Four decimals is ~11 m, the same precision used for area
                // centroids — a postcode-level dot, not a building address.
                'lat' => round((float) $site->latitude, 4),
                'lng' => round((float) $site->longitude, 4),
                // The postcode district only — "SW1A", not the full postcode.
                // Lets a single-pin popup say *where in the area* a service
                // sits (the point of the drill-down) without adding anything
                // more identifying than the coordinates above already are.
                'outcode' => $site->outcode ?: null,
            ], static fn ($value) => $value !== null))
            ->values()
            ->all();
    }

    /**
     * Operator-controlled copy and behaviour, grouped so the client can read
     * it without knowing individual setting keys.
     *
     * @return array<string, mixed>
     */
    protected function settingsPayload(): array
    {
        $get = static fn (string $key) => ServiceFinderSettings::get($key);

        return [
            'enabled' => (bool) $get('section_enabled'),
            'heading' => $get('heading'),
            'subheading' => $get('subheading'),
            // Which page types may render the section, and the heading each
            // one uses. Shipped inside the shared payload on purpose: gating
            // on the client keeps a single cached document serving every page
            // type, instead of one cache entry per placement.
            'placements' => [
                'home' => (bool) $get('placement_home'),
                'service' => (bool) $get('placement_service'),
                'sector' => (bool) $get('placement_sector'),
                'location' => (bool) $get('placement_location'),
                'preselect' => (bool) $get('placement_preselect'),
                'autoscroll' => (bool) $get('placement_autoscroll'),
                'headings' => [
                    'service' => $get('placement_heading_service'),
                    'sector' => $get('placement_heading_sector'),
                    'location' => $get('placement_heading_location'),
                ],
            ],
            'labels' => [
                'location' => $get('location_label'),
                'location_placeholder' => $get('location_placeholder'),
                'service' => $get('service_label'),
                'service_placeholder' => $get('service_placeholder'),
                'search' => $get('search_button_label'),
                'available_in' => $get('card_available_label'),
                'view_service' => $get('card_view_label'),
                'get_quote' => $get('card_quote_label'),
                'results_count' => $get('results_count_template'),
                'empty_state' => $get('empty_state_text'),
                'coverage_index' => $get('coverage_index_label'),
            ],
            'map' => [
                'enabled' => (bool) $get('map_enabled'),
                'load_mode' => $get('map_load_mode'),
                'lat' => (float) $get('map_center_lat'),
                'lng' => (float) $get('map_center_lng'),
                'zoom' => (int) $get('map_zoom'),
                'match_zoom' => (int) $get('map_zoom_on_match'),
                'restrict_uk' => (bool) $get('map_restrict_to_uk'),
                'cluster' => (bool) $get('map_cluster_enabled'),
                'legend' => (bool) $get('map_legend_enabled'),
                // Normalised to a plain list of valid #rrggbb strings here so
                // the frontend can index into it without validating anything.
                // Never empty: an operator who clears the field, or types
                // nonsense, still gets a usable key rather than invisible pins.
                'palette' => $this->normalisePalette($get('map_service_palette')),
                'colors' => [
                    'land' => $get('map_land_color'),
                    'water' => $get('map_water_color'),
                    'road' => $get('map_road_color'),
                    'label' => $get('map_label_color'),
                    'pin_outer' => $get('pin_outer_color'),
                    'pin_inner' => $get('pin_inner_color'),
                    'pin_active' => $get('pin_active_color'),
                    'pin_text' => $get('pin_text_color'),
                ],
                // The draggable search pin. `svg` is empty unless an operator
                // has pasted replacement artwork, and the frontend then falls
                // back to the file it ships with — so the map always has a
                // pointer, whatever is or is not configured here.
                'search_pin' => [
                    'svg' => (string) $get('search_pin_svg'),
                    'width' => max(8, (int) $get('search_pin_width') ?: 28),
                    'height' => max(8, (int) $get('search_pin_height') ?: 40),
                ],
            ],
            'behaviour' => [
                'autocomplete' => (bool) $get('autocomplete_enabled'),
                'min_chars' => (int) $get('autocomplete_min_chars'),
                'debounce_ms' => (int) $get('autocomplete_debounce_ms'),
                'max_results' => (int) $get('max_results'),
                'nearest_km' => (int) $get('nearest_area_km'),
                'log_searches' => (bool) $get('search_logging_enabled'),
                'show_index' => (bool) $get('show_coverage_index'),
            ],
            // Statistics bar under the map. Split into {value, label} pairs
            // server-side so the frontend renders fields rather than parsing
            // strings — a line with no "|" still yields a usable item.
            'stats' => [
                'enabled' => (bool) $get('stats_enabled'),
                'items' => $this->normaliseStats($get('stats_items')),
            ],
            'popup' => [
                'title' => $get('popup_title_template'),
                'body' => $get('popup_body_template'),
                'cta' => $get('popup_cta_template'),
                'show_site_postcode' => (bool) $get('popup_show_site_postcode'),
            ],
            'modal' => [
                'heading' => $get('modal_heading_template'),
                'heading_no_area' => $get('modal_heading_no_area'),
                'subheading' => $get('modal_subheading_template'),
                'submit' => $get('modal_submit_label'),
                'microcopy' => $get('modal_microcopy'),
                'usp' => $get('usp_items'),
                'start_dates' => (array) config('service_finder.leads.start_date_options', []),
                'success_heading' => $get('success_heading_template'),
                'success_body' => $get('success_body'),
                'success_cta' => $get('success_cta_label'),
                'error' => $get('error_message'),
                'honeypot' => $get('lead_honeypot_field'),
                'captcha' => (bool) $get('lead_captcha_enabled'),
            ],
        ];
    }

    /**
     * Service colours as a clean list of #rrggbb strings.
     *
     * Anything the operator typed that is not a colour is dropped rather than
     * passed through, and an empty result falls back to the shipped palette —
     * the map must never end up drawing sites in an invalid colour, which in
     * practice renders them invisible.
     */
    private function normalisePalette($value): array
    {
        $fallback = ['#48C6C2', '#EC814D', '#5E6AE2', '#24B9DA', '#DA5D6F', '#99CB6A', '#4276CA', '#EABB53', '#4EBA86'];

        $colors = collect($this->toLines($value))
            ->map(function (string $line) {
                $hex = ltrim(trim($line), '#');

                // Allow the 3-digit shorthand by expanding it, so #abc works.
                if (preg_match('/^[0-9a-f]{3}$/i', $hex)) {
                    $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                }

                return preg_match('/^[0-9a-f]{6}$/i', $hex) ? '#'.strtoupper($hex) : null;
            })
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $colors ?: $fallback;
    }

    /**
     * Statistics bar entries as {value, label} pairs.
     *
     * Split on the first "|" only, so a label containing a pipe survives. A
     * line with no separator becomes a label with no figure rather than being
     * discarded — an operator typing plain sentences still gets output.
     */
    private function normaliseStats($value): array
    {
        return collect($this->toLines($value))
            ->map(function (string $line) {
                if (! str_contains($line, '|')) {
                    return ['value' => '', 'label' => trim($line)];
                }

                [$figure, $label] = explode('|', $line, 2);

                return ['value' => trim($figure), 'label' => trim($label)];
            })
            ->filter(fn (array $item) => $item['value'] !== '' || $item['label'] !== '')
            ->take(12)
            ->values()
            ->all();
    }

    /** Accepts either the stored newline string or an already-split array. */
    private function toLines($value): array
    {
        if (is_array($value)) {
            $lines = $value;
        } elseif (is_string($value) && $value !== '') {
            $lines = preg_split('/\r\n|\r|\n/', $value);
        } else {
            return [];
        }

        return collect($lines)
            ->map(fn ($line) => is_string($line) ? trim($line) : '')
            ->filter()
            ->values()
            ->all();
    }
}
