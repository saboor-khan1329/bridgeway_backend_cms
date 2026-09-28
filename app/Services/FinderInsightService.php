<?php

namespace App\Services;

use App\Models\FinderArea;
use App\Models\FinderLead;
use App\Models\FinderSearch;
use App\Models\FinderSite;
use Illuminate\Support\Facades\DB;

/**
 * Read-only reporting for the Service Finder admin.
 *
 * Every screen that shows numbers — the dashboard, the leads list, the search
 * analytics — pulls them from here, so a figure means the same thing wherever
 * it appears and there is one place to optimise the queries.
 */
class FinderInsightService
{
    /** Headline counters for the dashboard. */
    public function overview(): array
    {
        // CASE rather than the FILTER clause so the same query runs on the
        // SQLite database the test suite uses.
        $sites = FinderSite::query()
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when is_active then 1 else 0 end) as active')
            ->first();

        return [
            'areas_total' => FinderArea::query()->count(),
            'areas_active' => FinderArea::query()->active()->count(),
            'sites_total' => (int) ($sites->total ?? 0),
            'sites_active' => (int) ($sites->active ?? 0),
            'coverage_rows' => DB::table('finder_area_service')->count(),
            'leads_total' => FinderLead::query()->where('status', '!=', FinderLead::STATUS_SPAM)->count(),
            'leads_new' => FinderLead::query()->new()->count(),
            'leads_spam' => FinderLead::query()->where('status', FinderLead::STATUS_SPAM)->count(),
            'searches_30d' => FinderSearch::query()->where('created_at', '>=', now()->subDays(30))->count(),
            'searches_unmatched_30d' => FinderSearch::query()->unmatched()
                ->where('created_at', '>=', now()->subDays(30))->count(),
        ];
    }

    /**
     * Configuration problems worth an operator's attention, most severe first.
     * Each entry carries a route so the dashboard can link straight to the fix.
     *
     * @return array<int, array{level:string, message:string, route:?string}>
     */
    public function healthChecks(): array
    {
        $checks = [];

        $noCoords = FinderArea::query()->active()
            ->where(fn ($q) => $q->whereNull('latitude')->orWhereNull('longitude'))
            ->count();

        if ($noCoords > 0) {
            $checks[] = [
                'level' => 'danger',
                'message' => "{$noCoords} active area(s) have no coordinates and cannot appear on the map.",
                'route' => route('admin.finder-areas.index'),
            ];
        }

        $noServices = FinderArea::query()->active()
            ->whereDoesntHave('services')
            ->count();

        if ($noServices > 0) {
            $checks[] = [
                'level' => 'warning',
                'message' => "{$noServices} active area(s) advertise no services, so their pin opens an empty panel.",
                'route' => route('admin.finder-areas.index'),
            ];
        }

        $noFeatured = FinderArea::query()->active()->whereNull('featured_service_id')->count();

        if ($noFeatured > 0) {
            $checks[] = [
                'level' => 'warning',
                'message' => "{$noFeatured} active area(s) have no featured service, so their map popup has no call to action.",
                'route' => route('admin.finder-areas.index'),
            ];
        }

        $failedGeocode = FinderSite::query()
            ->where('inactive_reason', FinderSite::REASON_INVALID_POSTCODE)
            ->count();

        if ($failedGeocode > 0) {
            $checks[] = [
                'level' => 'info',
                'message' => "{$failedGeocode} site(s) could not be placed from their postcode and are held back.",
                'route' => route('admin.finder-sites.index', ['reason' => FinderSite::REASON_INVALID_POSTCODE]),
            ];
        }

        $unmatched = FinderSearch::query()->unmatched()
            ->where('created_at', '>=', now()->subDays(30))->count();

        if ($unmatched > 0) {
            $checks[] = [
                'level' => 'info',
                'message' => "{$unmatched} search(es) in the last 30 days found no coverage — possible demand gaps.",
                'route' => route('admin.finder-searches.index', ['match_type' => FinderSearch::MATCH_NONE]),
            ];
        }

        if ($checks === []) {
            $checks[] = [
                'level' => 'success',
                'message' => 'No configuration issues detected.',
                'route' => null,
            ];
        }

        return $checks;
    }

    /** Most-searched terms in a window. */
    public function topSearches(int $days = 30, int $limit = 10)
    {
        return FinderSearch::query()
            ->selectRaw('LOWER(query) as term, count(*) as hits')
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('term')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get();
    }

    /** Searches that found nothing — where demand exists but coverage does not. */
    public function demandGaps(int $days = 30, int $limit = 10)
    {
        return FinderSearch::query()
            ->selectRaw('LOWER(query) as term, count(*) as hits')
            ->unmatched()
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('term')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get();
    }

    /** Which areas visitors actually land on. */
    public function topAreas(int $days = 30, int $limit = 10)
    {
        return FinderSearch::query()
            ->selectRaw('finder_areas.name, count(*) as hits')
            ->join('finder_areas', 'finder_areas.id', '=', 'finder_searches.matched_area_id')
            ->where('finder_searches.created_at', '>=', now()->subDays($days))
            ->groupBy('finder_areas.name')
            ->orderByDesc('hits')
            ->limit($limit)
            ->get();
    }

    /** Which gateway produces leads, so copy can be tuned where it matters. */
    public function leadsByOrigin(int $days = 90)
    {
        return FinderLead::query()
            ->selectRaw('origin, count(*) as total')
            ->where('status', '!=', FinderLead::STATUS_SPAM)
            ->where('created_at', '>=', now()->subDays($days))
            ->groupBy('origin')
            ->orderByDesc('total')
            ->get();
    }

    /** Recent leads for the dashboard strip. */
    public function recentLeads(int $limit = 8)
    {
        return FinderLead::query()
            ->with('area')
            ->where('status', '!=', FinderLead::STATUS_SPAM)
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}
