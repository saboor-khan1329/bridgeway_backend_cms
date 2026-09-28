<?php

namespace App\Services;

use App\Models\FinderArea;
use App\Models\FinderSite;
use App\Models\Location;
use App\Models\Service;
use App\Support\FrontendCache;
use App\Support\ServiceFinderSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Derives the public coverage map from imported sites.
 *
 * Runs after an import and after any admin change that can move the numbers
 * (activating a site, reassigning an area, editing coverage). Everything it
 * writes is recomputed from scratch except the things an operator has
 * deliberately set — manual centroids, manually attached services and a
 * hand-picked featured service are all preserved.
 */
class FinderRecountService
{
    /**
     * Rebuild every area from the current site rows.
     *
     * @return array{areas_created:int, areas_updated:int, pivots:int}
     */
    public function rebuildAll(bool $bumpCache = true): array
    {
        $created = $this->ensureAreasExist();

        // Sites must be attached to their area before anything is counted,
        // otherwise every area recounts against zero members.
        $this->linkOrphanSites();

        $areas = FinderArea::query()->get();

        foreach ($areas as $area) {
            $this->recountArea($area, false);
        }

        if ($bumpCache) {
            FrontendCache::bump();
        }

        return [
            'areas_created' => $created,
            'areas_updated' => $areas->count(),
            'pivots' => DB::table('finder_area_service')->count(),
        ];
    }

    /**
     * Create any area implied by an active site's postcode prefix.
     *
     * Areas are keyed by the display city, so all eight London prefixes
     * collapse into a single London row rather than eight near-identical pins.
     */
    public function ensureAreasExist(): int
    {
        $map = ServiceFinderSettings::postcodeAreaMap();
        $created = 0;

        $prefixes = FinderSite::query()
            ->active()
            ->whereNotNull('postcode_area')
            ->distinct()
            ->pluck('postcode_area');

        // city => [prefixes...]
        $cities = [];

        foreach ($prefixes as $prefix) {
            $entry = $map[strtoupper($prefix)] ?? null;

            if (! $entry) {
                continue;
            }

            $cities[$entry['city']]['prefixes'][] = strtoupper($prefix);
            $cities[$entry['city']]['region'] ??= $entry['region'];
        }

        foreach ($cities as $city => $data) {
            $area = FinderArea::query()->where('name', $city)->first();

            if (! $area) {
                $area = new FinderArea([
                    'name' => $city,
                    'slug' => $this->uniqueSlug($city),
                    'region' => $data['region'] ?? null,
                    'is_active' => true,
                ]);
                $created++;
            }

            // Merge prefixes so a manually added one is never dropped.
            $existing = (array) ($area->postcode_areas ?? []);
            $area->postcode_areas = array_values(array_unique(array_merge($existing, $data['prefixes'])));
            $area->region ??= $data['region'] ?? null;
            $area->save();
        }

        return $created;
    }

    /**
     * Attach every site to the area that owns its postcode prefix.
     * Sites whose prefix has no mapping stay unlinked and inactive.
     */
    public function linkOrphanSites(): int
    {
        $prefixToArea = [];

        foreach (FinderArea::query()->get() as $area) {
            foreach ((array) $area->postcode_areas as $prefix) {
                $prefixToArea[strtoupper($prefix)] = $area->id;
            }
        }

        $linked = 0;

        FinderSite::query()
            ->whereNotNull('postcode_area')
            ->orderBy('id')
            ->chunkById(500, function ($sites) use ($prefixToArea, &$linked) {
                foreach ($sites as $site) {
                    $areaId = $prefixToArea[strtoupper((string) $site->postcode_area)] ?? null;

                    if ($areaId && $site->finder_area_id !== $areaId) {
                        $site->forceFill(['finder_area_id' => $areaId])->save();
                        $linked++;
                    }
                }
            });

        return $linked;
    }

    /**
     * Recompute one area: site count, centroid, import-derived coverage and
     * (when unset) the featured service.
     */
    public function recountArea(FinderArea $area, bool $bumpCache = true): void
    {
        $sites = FinderSite::query()
            ->active()
            ->where('finder_area_id', $area->id)
            ->get(['id', 'service_id', 'latitude', 'longitude']);

        $area->active_sites_count = $sites->count();

        if (! $area->centroid_is_locked) {
            $coords = $sites->filter(fn ($s) => $s->latitude !== null && $s->longitude !== null);

            if ($coords->isNotEmpty()) {
                // Median rather than mean: one mis-geocoded outlier should
                // not drag a city pin into the sea.
                $area->latitude = $this->median($coords->pluck('latitude')->all());
                $area->longitude = $this->median($coords->pluck('longitude')->all());
            }
        }

        $counts = $sites->whereNotNull('service_id')
            ->groupBy('service_id')
            ->map(fn ($group) => $group->count())
            ->sortDesc();

        $this->syncImportCoverage($area, $counts->all());

        if ($area->featured_service_id === null && ServiceFinderSettings::get('import_auto_feature')) {
            $area->featured_service_id = $counts->keys()->first();
        }

        if ($area->location_id === null) {
            $area->location_id = $this->matchLocationId($area->name);
        }

        $area->save();

        if ($bumpCache) {
            FrontendCache::bump();
        }
    }

    /**
     * Sync the import-derived half of an area's coverage.
     *
     * Rows an admin attached (source = admin) are never touched here, so a
     * manually added service such as CCTV survives every re-import.
     *
     * @param  array<int, int>  $counts  service_id => active site count
     */
    protected function syncImportCoverage(FinderArea $area, array $counts): void
    {
        $existing = DB::table('finder_area_service')
            ->where('finder_area_id', $area->id)
            ->get()
            ->keyBy('service_id');

        foreach ($counts as $serviceId => $count) {
            $row = $existing->get($serviceId);

            if ($row && $row->source === 'admin') {
                // Keep the admin's description, but let the real count show.
                DB::table('finder_area_service')
                    ->where('id', $row->id)
                    ->update(['sites_count' => $count, 'updated_at' => now()]);

                continue;
            }

            DB::table('finder_area_service')->updateOrInsert(
                ['finder_area_id' => $area->id, 'service_id' => $serviceId],
                [
                    'source' => 'import',
                    'sites_count' => $count,
                    'updated_at' => now(),
                    'created_at' => $row->created_at ?? now(),
                ]
            );
        }

        // Import rows for services no longer present in this area are dropped;
        // admin rows are left alone even at zero sites, because an operator
        // may legitimately advertise a service before the first site lands.
        $stale = $existing
            ->filter(fn ($row) => $row->source !== 'admin' && ! array_key_exists((int) $row->service_id, $counts))
            ->pluck('id')
            ->all();

        if ($stale !== []) {
            DB::table('finder_area_service')->whereIn('id', $stale)->delete();
        }
    }

    /** Link an area to the matching /locations page when the name is unambiguous. */
    protected function matchLocationId(string $areaName): ?int
    {
        $matches = Location::query()
            ->where('status', true)
            ->where(function ($query) use ($areaName) {
                $query->whereRaw('LOWER(title) = ?', [strtolower($areaName)])
                    ->orWhereRaw('LOWER(title) = ?', [strtolower('Security Services in '.$areaName)]);
            })
            ->limit(2)
            ->get(['id']);

        return $matches->count() === 1 ? (int) $matches->first()->id : null;
    }

    protected function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'area';
        $slug = $base;
        $suffix = 1;

        while (FinderArea::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$suffix);
        }

        return $slug;
    }

    /** @param array<int, float> $values */
    protected function median(array $values): ?float
    {
        $values = array_values(array_filter($values, static fn ($v) => $v !== null));

        if ($values === []) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = intdiv($count, 2);

        return $count % 2 === 0
            ? round(($values[$middle - 1] + $values[$middle]) / 2, 7)
            : round($values[$middle], 7);
    }

    /** Service catalog for the finder dropdown, ordered for humans. */
    public function catalogServices()
    {
        return Service::query()
            ->where('status', true)
            ->with(['slug', 'categories.slug'])
            ->orderBy('title')
            ->get();
    }
}
