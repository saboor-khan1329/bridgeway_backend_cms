<?php

namespace App\Services;

use App\Models\FinderArea;
use App\Models\Service;
use App\Support\FrontendCache;
use App\Support\ServiceFinderSettings;
use Illuminate\Support\Facades\DB;

/**
 * Authoring layer for what the finder advertises.
 *
 * Coverage is decided by an operator: they pick a service, the areas it is
 * offered in, its description for that area, and the related services to
 * cross-sell alongside it. Imported site counts only ever inform those
 * choices — they never make them.
 *
 * Both directions of the same relationship are supported, because operators
 * think in both: "which services does Birmingham offer?" (area editor) and
 * "where do we offer Key Holding?" (service editor).
 */
class FinderCoverageService
{
    /**
     * Replace one area's coverage list.
     *
     * @param  array<int, array{service_id:int, description?:?string, related_service_ids?:array<int,int>, is_featured?:bool, is_active?:bool, sort_order?:int}>  $rows
     */
    public function syncArea(FinderArea $area, array $rows): void
    {
        $seen = [];
        $featuredId = null;

        DB::transaction(function () use ($area, $rows, &$seen, &$featuredId) {
            $existing = DB::table('finder_area_service')
                ->where('finder_area_id', $area->id)
                ->get()
                ->keyBy('service_id');

            foreach (array_values($rows) as $index => $row) {
                $serviceId = (int) ($row['service_id'] ?? 0);

                if ($serviceId <= 0) {
                    continue;
                }

                $previous = $existing->get($serviceId);
                $seen[] = $serviceId;

                if (! empty($row['is_featured'])) {
                    $featuredId = $serviceId;
                }

                $payload = [
                    'description' => $this->cleanText($row['description'] ?? null),
                    'related_service_ids' => $this->encodeRelated($row['related_service_ids'] ?? [], $serviceId),
                    'is_featured' => ! empty($row['is_featured']),
                    'is_active' => ! array_key_exists('is_active', $row) || (bool) $row['is_active'],
                    'sort_order' => (int) ($row['sort_order'] ?? $index),
                    // Anything saved through the editor becomes admin-owned,
                    // so a later re-import tops up counts without clobbering it.
                    'source' => 'admin',
                    'sites_count' => (int) ($previous->sites_count ?? 0),
                    'updated_at' => now(),
                ];

                if ($previous) {
                    DB::table('finder_area_service')->where('id', $previous->id)->update($payload);

                    continue;
                }

                DB::table('finder_area_service')->insert($payload + [
                    'finder_area_id' => $area->id,
                    'service_id' => $serviceId,
                    'created_at' => now(),
                ]);
            }

            // Rows the operator removed from the editor are deleted outright —
            // this is an explicit "we do not advertise that here" decision.
            DB::table('finder_area_service')
                ->where('finder_area_id', $area->id)
                ->when($seen !== [], fn ($query) => $query->whereNotIn('service_id', $seen))
                ->delete();

            $area->forceFill([
                'featured_service_id' => $featuredId ?: $area->featured_service_id,
            ])->save();
        });

        // A featured service that is no longer covered must not linger.
        if ($area->featured_service_id && ! in_array((int) $area->featured_service_id, $seen, true)) {
            $area->forceFill(['featured_service_id' => $seen[0] ?? null])->save();
        }

        FrontendCache::bump();
    }

    /**
     * Replace the set of areas one service is offered in.
     *
     * @param  array<int, int>  $areaIds
     */
    public function syncService(Service $service, array $areaIds, ?string $description = null): void
    {
        $areaIds = array_values(array_unique(array_map('intval', $areaIds)));

        DB::transaction(function () use ($service, $areaIds, $description) {
            foreach ($areaIds as $index => $areaId) {
                DB::table('finder_area_service')->updateOrInsert(
                    ['finder_area_id' => $areaId, 'service_id' => $service->id],
                    [
                        'description' => $this->cleanText($description),
                        'is_active' => true,
                        'source' => 'admin',
                        'sort_order' => $index,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }

            DB::table('finder_area_service')
                ->where('service_id', $service->id)
                ->when($areaIds !== [], fn ($query) => $query->whereNotIn('finder_area_id', $areaIds))
                ->delete();
        });

        FrontendCache::bump();
    }

    /**
     * Coverage rows for the area editor, joined to service titles.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsForArea(FinderArea $area): array
    {
        $rows = DB::table('finder_area_service as fas')
            ->join('services as s', 's.id', '=', 'fas.service_id')
            ->where('fas.finder_area_id', $area->id)
            ->orderBy('fas.sort_order')
            ->orderByDesc('fas.sites_count')
            ->get([
                'fas.service_id', 'fas.description', 'fas.related_service_ids',
                'fas.is_featured', 'fas.is_active', 'fas.source', 'fas.sites_count',
                'fas.sort_order', 's.title', 's.card_description',
            ]);

        return $rows->map(fn ($row) => [
            'service_id' => (int) $row->service_id,
            'title' => (string) $row->title,
            'description' => (string) ($row->description ?? ''),
            'card_description' => (string) ($row->card_description ?? ''),
            'related_service_ids' => $this->decodeRelated($row->related_service_ids),
            'is_featured' => (bool) $row->is_featured || (int) $row->service_id === (int) $area->featured_service_id,
            'is_active' => (bool) $row->is_active,
            'source' => (string) $row->source,
            'sites_count' => (int) $row->sites_count,
            'sort_order' => (int) $row->sort_order,
        ])->all();
    }

    /**
     * Resolve the description shown on a result card:
     * per-area override, then the service's own card copy, then the
     * configurator's generic fallback. A card is never left blank.
     */
    public function resolveDescription(?string $override, ?string $cardDescription): string
    {
        foreach ([$override, $cardDescription] as $candidate) {
            $candidate = trim((string) $candidate);

            if ($candidate !== '') {
                return $candidate;
            }
        }

        return (string) ServiceFinderSettings::get('generic_service_description');
    }

    /** @param mixed $value */
    public function decodeRelated($value): array
    {
        if (is_array($value)) {
            return array_values(array_map('intval', $value));
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? array_values(array_map('intval', $decoded)) : [];
    }

    /** @param array<int, int|string> $ids */
    protected function encodeRelated(array $ids, int $excludeServiceId): ?string
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $ids),
            static fn ($id) => $id > 0 && $id !== $excludeServiceId
        )));

        return $ids === [] ? null : json_encode($ids);
    }

    protected function cleanText(?string $value): ?string
    {
        $value = trim(strip_tags((string) $value));

        return $value === '' ? null : $value;
    }
}
