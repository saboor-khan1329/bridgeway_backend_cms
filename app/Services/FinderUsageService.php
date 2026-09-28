<?php

namespace App\Services;

use App\Models\FinderUsageDaily;
use Illuminate\Support\Carbon;

/**
 * Self-tracked counters for the two Service Finder actions that actually
 * cost money at runtime: a map load (the Dynamic Maps SKU) and an address
 * autocomplete session — billed once per session token however many
 * keystrokes happen inside it, which is why the frontend logs this once per
 * new `AutocompleteSessionToken`, not once per suggestion request. Nothing
 * else the finder does calls Google at runtime; the CSV import's own
 * (separate, one-off) Google geocoding fallback is already reported on the
 * Import screen and counted permanently in `finder_geocodes`.
 *
 * This is not Google's own billing figure — it is what our code *asked*
 * Google for, counted here because reading Google's real number needs a
 * Cloud Billing service-account credential this project does not have
 * configured (see SERVICE_FINDER.md §usage analytics). The two normally
 * match closely; they would only drift if a request left the browser and
 * never actually reached Google — an ad blocker, a network failure.
 */
class FinderUsageService
{
    /** Record one occurrence of `event` today. */
    public function record(string $event): void
    {
        if (! array_key_exists($event, FinderUsageDaily::EVENTS)) {
            return;
        }

        $row = FinderUsageDaily::query()->firstOrCreate(
            ['date' => now()->toDateString(), 'event' => $event],
            ['count' => 0],
        );

        $row->increment('count');
    }

    /**
     * Per-day counts for every event, zero-filled for days with no activity —
     * exactly what the admin usage graph draws.
     *
     * @return array{labels: array<int, string>, series: array<string, array<int, int>>, totals: array<string, int>}
     */
    public function series(int $days = 30): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $rows = FinderUsageDaily::query()
            ->where('date', '>=', $start->toDateString())
            ->get()
            ->groupBy(fn (FinderUsageDaily $row) => $row->date->toDateString());

        $labels = [];
        $series = array_fill_keys(array_keys(FinderUsageDaily::EVENTS), []);
        $totals = array_fill_keys(array_keys(FinderUsageDaily::EVENTS), 0);

        for ($i = 0; $i < $days; $i++) {
            /** @var Carbon $date */
            $date = $start->copy()->addDays($i);
            $dayRows = $rows->get($date->toDateString()) ?? collect();
            $labels[] = $date->format('j M');

            foreach (array_keys(FinderUsageDaily::EVENTS) as $event) {
                $count = (int) ($dayRows->firstWhere('event', $event)?->count ?? 0);
                $series[$event][] = $count;
                $totals[$event] += $count;
            }
        }

        return ['labels' => $labels, 'series' => $series, 'totals' => $totals];
    }

    /** Running totals since tracking began, for the summary cards above the graph. */
    public function allTimeTotals(): array
    {
        $totals = array_fill_keys(array_keys(FinderUsageDaily::EVENTS), 0);

        FinderUsageDaily::query()
            ->selectRaw('event, sum(count) as total')
            ->groupBy('event')
            ->get()
            ->each(function (FinderUsageDaily $row) use (&$totals) {
                $totals[$row->event] = (int) $row->getAttribute('total');
            });

        return $totals;
    }
}
