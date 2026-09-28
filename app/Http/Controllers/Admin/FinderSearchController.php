<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderSearch;
use App\Services\FinderInsightService;
use App\Support\ServiceFinderSettings;
use Illuminate\Http\Request;

/**
 * What visitors typed into the finder.
 *
 * Read-only by design. The valuable part is not the log itself but the
 * demand-gap report: searches that matched nothing are places where people
 * are looking for cover that is not advertised yet.
 */
class FinderSearchController extends Controller
{
    public function index(Request $request, FinderInsightService $insights)
    {
        $query = FinderSearch::query()->with(['area', 'service']);

        if ($request->filled('match_type')) {
            $query->where('match_type', $request->query('match_type'));
        }

        if ($request->filled('area_id')) {
            $query->where('matched_area_id', $request->query('area_id'));
        }

        $days = max(1, min(365, (int) $request->query('days', 30)));

        return $this->renderAdminIndex(
            $query->where('created_at', '>=', now()->subDays($days))->latest('id'),
            [
                'title' => 'Search Analytics',
                'routes' => [],
                'columns' => [
                    'id' => 'ID',
                    'query' => 'Search',
                    'area.name' => 'Matched area',
                    'match_type' => 'Match',
                    'results_count' => 'Results',
                    'created_at' => 'When',
                ],
                'search' => ['query', 'postcode'],
                'days' => $days,
                'stats' => [
                    'top' => $insights->topSearches($days),
                    'gaps' => $insights->demandGaps($days),
                    'areas' => $insights->topAreas($days),
                ],
                'match_types' => FinderSearch::MATCH_TYPES,
            ],
            'admin.finder.searches.index'
        );
    }

    /** Trim the log to the configured retention window. */
    public function prune()
    {
        $days = max(7, (int) ServiceFinderSettings::get('search_retention_days'));
        $deleted = FinderSearch::query()->where('created_at', '<', now()->subDays($days))->delete();

        return back()->with('success', "Removed {$deleted} search log entries older than {$days} days.");
    }
}
