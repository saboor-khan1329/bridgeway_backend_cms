<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderArea;
use App\Models\FinderSite;
use App\Models\Service;
use App\Services\FinderRecountService;
use App\Services\ServiceFinderGeocoder;
use App\Support\UkPostcode;
use Illuminate\Http\Request;

/**
 * Imported operational sites.
 *
 * Sites are the evidence behind an area's numbers, not public content — the
 * API never exposes a site name. This screen exists so an operator can audit
 * what the import produced, fix the rows it could not place, and hide the
 * ones that should never have been counted.
 *
 * Any row edited here is marked admin-owned and is then immune to a forced
 * re-import.
 */
class FinderSiteController extends Controller
{
    public function __construct(
        protected FinderRecountService $recount,
        protected ServiceFinderGeocoder $geocoder,
    ) {
    }

    public function index(Request $request)
    {
        $query = FinderSite::query()->with(['area', 'service']);

        if ($request->filled('area_id')) {
            $query->where('finder_area_id', $request->query('area_id'));
        }

        if ($request->filled('service_id')) {
            $query->where('service_id', $request->query('service_id'));
        }

        if ($request->filled('status')) {
            $query->where('is_active', $request->query('status') === 'active');
        }

        if ($request->filled('reason')) {
            $query->where('inactive_reason', $request->query('reason'));
        }

        if ($request->query('missing') === 'coords') {
            $query->whereNull('latitude');
        }

        return $this->renderAdminIndex(
            $query->orderByDesc('is_active')->orderBy('postcode_raw'),
            [
                'title' => 'Operational Sites',
                'routes' => [
                    'edit' => 'admin.finder-sites.edit',
                    'destroy' => 'admin.finder-sites.destroy',
                ],
                'columns' => [
                    'id' => 'ID',
                    'postcode_raw' => 'Postcode',
                    'area.name' => 'Area',
                    'service.title' => 'Service',
                    'is_active' => 'Status',
                ],
                'search' => ['postcode_raw', 'postcode', 'outcode'],
                'filters' => [
                    'areas' => FinderArea::query()->orderBy('name')->pluck('name', 'id')->all(),
                    'services' => Service::query()->where('status', true)->orderBy('title')->pluck('title', 'id')->all(),
                    'reasons' => [
                        FinderSite::REASON_NON_UK => 'Outside the UK',
                        FinderSite::REASON_INVALID_POSTCODE => 'Postcode could not be placed',
                        FinderSite::REASON_TEST_ROW => 'Test or internal row',
                        FinderSite::REASON_UNMAPPED_SERVICE => 'Service type not mapped',
                        FinderSite::REASON_ADMIN => 'Hidden by an admin',
                    ],
                ],
            ],
            'admin.finder.sites.index'
        );
    }

    public function edit(FinderSite $finderSite)
    {
        return view('admin.shared.crud', [
            'item' => $finderSite,
            'config' => $this->formConfig($finderSite),
        ]);
    }

    public function update(Request $request, FinderSite $finderSite)
    {
        $data = $request->validate([
            'postcode' => 'nullable|string|max:12',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'service_id' => 'nullable|integer|exists:services,id',
            'finder_area_id' => 'nullable|integer|exists:finder_areas,id',
            'is_active' => 'nullable|boolean',
        ]);

        $parsed = UkPostcode::parse($data['postcode'] ?? '');

        $finderSite->update([
            'postcode' => $parsed['postcode'],
            'outcode' => $parsed['outcode'],
            'postcode_area' => $parsed['area'],
            'latitude' => $data['latitude'] ?? null,
            'longitude' => $data['longitude'] ?? null,
            'service_id' => $data['service_id'] ?: null,
            'finder_area_id' => $data['finder_area_id'] ?: null,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'inactive_reason' => ($data['is_active'] ?? false) ? null : FinderSite::REASON_ADMIN,
            'geocode_source' => 'manual',
            // Protects this row from the next forced re-import.
            'source' => FinderSite::SOURCE_ADMIN,
        ]);

        $this->recountAffected($finderSite);

        return redirect()->route('admin.finder-sites.index')->with('success', 'Site updated.');
    }

    public function destroy(FinderSite $finderSite)
    {
        $area = $finderSite->area;
        $finderSite->delete();

        if ($area) {
            $this->recount->recountArea($area);
        }

        return back()->with('success', 'Site deleted.');
    }

    /**
     * Apply one action to a selection. Keeps large clean-ups to a single
     * pass instead of dozens of individual saves.
     */
    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|string|in:activate,deactivate,geocode,assign_area,assign_service,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:finder_sites,id',
            'area_id' => 'nullable|integer|exists:finder_areas,id',
            'service_id' => 'nullable|integer|exists:services,id',
        ]);

        $sites = FinderSite::query()->whereIn('id', $data['ids'])->get();
        $affected = $sites->pluck('finder_area_id')->filter()->unique();
        $count = $sites->count();

        switch ($data['action']) {
            case 'activate':
                // A site with no coordinates cannot be drawn, so activating it
                // would silently do nothing — surface that instead.
                $placeable = $sites->filter(fn ($s) => $s->latitude !== null);
                FinderSite::whereIn('id', $placeable->pluck('id'))->update([
                    'is_active' => true, 'inactive_reason' => null, 'source' => FinderSite::SOURCE_ADMIN,
                ]);
                $skipped = $count - $placeable->count();
                $message = "{$placeable->count()} site(s) activated."
                    .($skipped > 0 ? " {$skipped} skipped — no coordinates yet, geocode them first." : '');
                break;

            case 'deactivate':
                FinderSite::whereIn('id', $data['ids'])->update([
                    'is_active' => false,
                    'inactive_reason' => FinderSite::REASON_ADMIN,
                    'source' => FinderSite::SOURCE_ADMIN,
                ]);
                $message = "{$count} site(s) hidden from the map.";
                break;

            case 'geocode':
                $message = $this->geocodeSites($sites);
                break;

            case 'assign_area':
                FinderSite::whereIn('id', $data['ids'])->update([
                    'finder_area_id' => $data['area_id'] ?: null,
                    'source' => FinderSite::SOURCE_ADMIN,
                ]);
                $affected = $affected->push($data['area_id'])->filter()->unique();
                $message = "{$count} site(s) reassigned.";
                break;

            case 'assign_service':
                FinderSite::whereIn('id', $data['ids'])->update([
                    'service_id' => $data['service_id'] ?: null,
                    'source' => FinderSite::SOURCE_ADMIN,
                ]);
                $message = "{$count} site(s) updated.";
                break;

            default:
                FinderSite::whereIn('id', $data['ids'])->delete();
                $message = "{$count} site(s) deleted.";
        }

        FinderArea::query()->whereIn('id', $affected)->get()
            ->each(fn ($area) => $this->recount->recountArea($area, false));

        \App\Support\FrontendCache::bump();

        return back()->with('success', $message);
    }

    /** Resolve coordinates for a selection, reusing the shared geocode cache. */
    protected function geocodeSites($sites): string
    {
        $resolved = 0;

        foreach ($sites as $site) {
            $lookup = $site->postcode ?: $site->outcode;

            if (! $lookup) {
                continue;
            }

            $hit = $this->geocoder->resolve($lookup);

            if ($hit && $hit->isResolved()) {
                $site->forceFill([
                    'latitude' => $hit->latitude,
                    'longitude' => $hit->longitude,
                    'geocode_source' => $hit->source,
                ])->save();
                $resolved++;
            }
        }

        return "{$resolved} of {$sites->count()} site(s) geocoded.";
    }

    protected function recountAffected(FinderSite $site): void
    {
        $this->recount->recountArea($site->area ?? new FinderArea());
    }

    /** @return array<string, mixed> */
    protected function formConfig(FinderSite $site): array
    {
        return [
            'title' => "Edit Site #{$site->id}: {$site->postcode_raw}",
            'routes' => [
                'index' => route('admin.finder-sites.index'),
                'store' => route('admin.finder-sites.index'),
                'update' => route('admin.finder-sites.update', $site),
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Postcode', 'name' => 'postcode', 'col' => 12,
                        'value' => $site->postcode ?: $site->postcode_raw],
                ],
                [
                    ['type' => 'select', 'label' => 'Service', 'name' => 'service_id', 'col' => 6,
                        'options' => ['' => '— None —'] + Service::query()->where('status', true)
                            ->orderBy('title')->pluck('title', 'id')->all(),
                        'selected' => $site->service_id],
                    ['type' => 'select', 'label' => 'Coverage area', 'name' => 'finder_area_id', 'col' => 6,
                        'options' => ['' => '— Unassigned —'] + FinderArea::query()
                            ->orderBy('name')->pluck('name', 'id')->all(),
                        'selected' => $site->finder_area_id],
                ],
                [
                    ['type' => 'number', 'label' => 'Latitude', 'name' => 'latitude', 'col' => 4,
                        'value' => $site->latitude, 'attr' => ['step' => '0.0000001']],
                    ['type' => 'number', 'label' => 'Longitude', 'name' => 'longitude', 'col' => 4,
                        'value' => $site->longitude, 'attr' => ['step' => '0.0000001']],
                    ['type' => 'select', 'label' => 'Counted on the map', 'name' => 'is_active', 'col' => 4,
                        'options' => ['1' => 'Yes', '0' => 'No'],
                        'selected' => $site->is_active ? '1' : '0'],
                ],
            ],
        ];
    }
}
