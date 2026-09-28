<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderArea;
use App\Models\Location;
use App\Models\Service;
use App\Services\FinderRecountService;
use App\Services\ServiceFinderGeocoder;
use App\Support\FrontendCache;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Coverage areas — the pins on the public map.
 *
 * Uses the shared CRUD form so the finder looks and behaves like every other
 * admin module. Areas may be created by hand or seeded by the site import;
 * either way an operator can rename, move, merge or retire them, and their
 * edits survive the next import.
 */
class FinderAreaController extends Controller
{
    public function __construct(
        protected FinderRecountService $recount,
        protected ServiceFinderGeocoder $geocoder,
    ) {
    }

    public function index(Request $request)
    {
        // withCount keeps the listing to one query regardless of page size.
        $query = FinderArea::query()
            ->with(['featuredService:id,title', 'location:id,title'])
            ->withCount('services');

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        if ($request->query('missing') === 'coords') {
            $query->whereNull('latitude');
        }

        if ($request->query('missing') === 'featured') {
            $query->whereNull('featured_service_id');
        }

        return $this->renderAdminIndex(
            $query->orderBy('sort_order')->orderByDesc('active_sites_count'),
            [
                'title' => 'Coverage Areas',
                'routes' => [
                    'create' => route('admin.finder-areas.create'),
                    'edit' => 'admin.finder-areas.edit',
                    'destroy' => 'admin.finder-areas.destroy',
                ],
                'columns' => [
                    'id' => 'ID',
                    'name' => 'Area',
                    'region' => 'Region',
                    'active_sites_count' => 'Sites',
                    'featuredService.title' => 'Featured Service',
                    'is_active' => 'Status',
                ],
                'search' => ['name', 'region', 'slug'],
            ],
            'admin.finder.areas.index'
        );
    }

    public function create()
    {
        return view('admin.shared.crud', [
            'item' => null,
            'config' => $this->formConfig(null),
        ]);
    }

    public function edit(FinderArea $finderArea)
    {
        return view('admin.shared.crud', [
            'item' => $finderArea,
            'config' => $this->formConfig($finderArea),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateRequest($request);
        $area = FinderArea::create($this->attributes($data, null));

        $this->afterSave($area, $data);

        return redirect()
            ->route('admin.finder-areas.coverage.edit', $area)
            ->with('success', 'Area created. Now choose the services offered here.');
    }

    public function update(Request $request, FinderArea $finderArea)
    {
        $data = $this->validateRequest($request, $finderArea);
        $finderArea->update($this->attributes($data, $finderArea));

        $this->afterSave($finderArea, $data);

        return redirect()
            ->route('admin.finder-areas.index')
            ->with('success', 'Area updated.');
    }

    public function destroy(FinderArea $finderArea)
    {
        // Sites survive: they are detached and re-linked on the next recount,
        // so deleting a pin never destroys imported operational data.
        $finderArea->sites()->update(['finder_area_id' => null]);
        $finderArea->delete();

        FrontendCache::bump();

        return back()->with('success', 'Area deleted. Its sites were kept and unlinked.');
    }

    /** Recompute one area's counts, centroid and coverage from its sites. */
    public function recount(FinderArea $finderArea)
    {
        $this->recount->recountArea($finderArea);

        return back()->with('success', "Recounted {$finderArea->name}.");
    }

    /**
     * Resolve a centroid from the area's postcode prefixes so an operator
     * never has to look up coordinates by hand.
     */
    public function geocode(FinderArea $finderArea)
    {
        $prefix = collect((array) $finderArea->postcode_areas)->first();

        if (! $prefix) {
            return back()->with('error', 'Add at least one postcode prefix first.');
        }

        // A prefix alone is not a lookup key; "B" becomes the "B1" district.
        $result = $this->geocoder->resolve($prefix.'1');

        if (! $result || ! $result->isResolved()) {
            return back()->with('error', "Could not resolve coordinates for prefix {$prefix}.");
        }

        $finderArea->forceFill([
            'latitude' => $result->latitude,
            'longitude' => $result->longitude,
            'centroid_is_locked' => true,
        ])->save();

        FrontendCache::bump();

        return back()->with('success', "Centroid set from postcode district {$prefix}1 and locked.");
    }

    protected function afterSave(FinderArea $area, array $data): void
    {
        // Re-link sites whose prefix now belongs to this area, then recount.
        $this->recount->linkOrphanSites();
        $this->recount->recountArea($area);
    }

    /** @return array<string, mixed> */
    protected function attributes(array $data, ?FinderArea $area): array
    {
        return [
            'name' => $data['name'],
            'slug' => Str::slug($data['slug'] ?: $data['name']),
            'region' => $data['region'] ?? null,
            'intro' => $data['intro'] ?? null,
            'postcode_areas' => $this->parsePrefixes($data['postcode_areas'] ?? ''),
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'centroid_is_locked' => (bool) ($data['centroid_is_locked'] ?? false),
            'featured_service_id' => $data['featured_service_id'] ?: null,
            'location_id' => $data['location_id'] ?: null,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'is_active' => (bool) ($data['is_active'] ?? true),
            'source' => $area?->source ?? FinderArea::SOURCE_ADMIN,
        ];
    }

    /** "b, ec , nw" => ["B","EC","NW"] */
    protected function parsePrefixes(string $raw): array
    {
        return collect(preg_split('/[\s,;]+/', strtoupper($raw)) ?: [])
            ->map(fn ($p) => trim($p))
            ->filter(fn ($p) => preg_match('/^[A-Z]{1,2}$/', $p) === 1)
            ->unique()
            ->values()
            ->all();
    }

    protected function validateRequest(Request $request, ?FinderArea $area = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120', Rule::unique('finder_areas', 'name')->ignore($area?->id)],
            'slug' => ['nullable', 'string', 'max:140', Rule::unique('finder_areas', 'slug')->ignore($area?->id)],
            'region' => 'nullable|string|max:80',
            'intro' => 'nullable|string|max:1000',
            'postcode_areas' => 'nullable|string|max:255',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'centroid_is_locked' => 'nullable|boolean',
            'featured_service_id' => 'nullable|integer|exists:services,id',
            'location_id' => 'nullable|integer|exists:locations,id',
            'sort_order' => 'nullable|integer|min:0|max:9999',
            'is_active' => 'nullable|boolean',
        ]);

        // Two areas claiming the same prefix would make postcode matching
        // ambiguous, so overlaps are rejected rather than silently resolved.
        $prefixes = $this->parsePrefixes($data['postcode_areas'] ?? '');

        if ($prefixes !== []) {
            $clashes = FinderArea::query()
                ->when($area, fn ($q) => $q->whereKeyNot($area->id))
                ->get(['id', 'name', 'postcode_areas'])
                ->filter(fn ($other) => array_intersect($prefixes, (array) $other->postcode_areas) !== [])
                ->pluck('name');

            if ($clashes->isNotEmpty()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'postcode_areas' => 'These prefixes are already used by: '.$clashes->implode(', ').'.',
                ]);
            }
        }

        return $data;
    }

    /** @return array<string, mixed> */
    protected function formConfig(?FinderArea $area): array
    {
        $services = Service::query()->where('status', true)->orderBy('title')->pluck('title', 'id')->all();
        $locations = Location::query()->where('status', true)->orderBy('title')->pluck('title', 'id')->all();

        return [
            'title' => $area ? "Edit Area: {$area->name}" : 'New Coverage Area',
            'routes' => [
                'index' => route('admin.finder-areas.index'),
                'store' => route('admin.finder-areas.store'),
                'update' => $area ? route('admin.finder-areas.update', $area) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Area name', 'name' => 'name', 'required' => true, 'col' => 4,
                        'value' => $area?->name],
                    ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 4,
                        'value' => $area?->slug, 'attr' => ['placeholder' => 'Generated from the name if left blank']],
                    ['type' => 'text', 'label' => 'Region', 'name' => 'region', 'col' => 4,
                        'value' => $area?->region, 'attr' => ['placeholder' => 'e.g. West Midlands']],
                ],
                [
                    ['type' => 'text', 'label' => 'Postcode prefixes', 'name' => 'postcode_areas', 'col' => 6,
                        'value' => implode(', ', (array) ($area?->postcode_areas ?? [])),
                        'attr' => ['placeholder' => 'e.g. B  —  or for London: E, EC, N, NW, SE, SW, W, WC']],
                    ['type' => 'select', 'label' => 'Linked location page', 'name' => 'location_id', 'col' => 6,
                        'options' => ['' => '— None —'] + $locations, 'selected' => $area?->location_id],
                ],
                [
                    ['type' => 'textarea', 'label' => 'Map popup intro (optional)', 'name' => 'intro', 'editor' => false,
                        'col' => 12, 'value' => $area?->intro,
                        'attr' => ['placeholder' => 'Overrides the standard popup wording for this area only.']],
                ],
                [
                    ['type' => 'number', 'label' => 'Latitude', 'name' => 'latitude', 'col' => 3,
                        'value' => $area?->latitude, 'attr' => ['step' => '0.0000001']],
                    ['type' => 'number', 'label' => 'Longitude', 'name' => 'longitude', 'col' => 3,
                        'value' => $area?->longitude, 'attr' => ['step' => '0.0000001']],
                    ['type' => 'select', 'label' => 'Pin position', 'name' => 'centroid_is_locked', 'col' => 3,
                        'options' => ['0' => 'Auto (average of sites)', '1' => 'Locked to the values above'],
                        'selected' => $area?->centroid_is_locked ? '1' : '0'],
                    ['type' => 'number', 'label' => 'Sort order', 'name' => 'sort_order', 'col' => 3,
                        'value' => $area?->sort_order ?? 0],
                ],
                [
                    ['type' => 'select', 'label' => 'Featured service', 'name' => 'featured_service_id', 'col' => 6,
                        'options' => ['' => '— Auto (most sites) —'] + $services,
                        'selected' => $area?->featured_service_id],
                    ['type' => 'select', 'label' => 'Status', 'name' => 'is_active', 'col' => 6,
                        'options' => ['1' => 'Active (shown on the map)', '0' => 'Hidden'],
                        'selected' => $area === null ? '1' : ($area->is_active ? '1' : '0')],
                ],
            ],
        ];
    }
}
