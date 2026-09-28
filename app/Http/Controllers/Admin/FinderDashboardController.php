<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FinderInsightService;
use App\Services\FinderRecountService;
use App\Support\FrontendCache;

/**
 * Landing screen for the Service Finder module: the numbers behind the map,
 * anything misconfigured, and the two maintenance actions an operator needs
 * without dropping to a terminal.
 */
class FinderDashboardController extends Controller
{
    public function index(FinderInsightService $insights)
    {
        return view('admin.finder.dashboard', [
            'title' => 'Service Finder',
            'stats' => $insights->overview(),
            'health' => $insights->healthChecks(),
            'topSearches' => $insights->topSearches(),
            'gaps' => $insights->demandGaps(),
            'topAreas' => $insights->topAreas(),
            'leadsByOrigin' => $insights->leadsByOrigin(),
            'recentLeads' => $insights->recentLeads(),
        ]);
    }

    /** Rebuild every area's counts, centroids and import-derived coverage. */
    public function rebuild(FinderRecountService $recount)
    {
        $result = $recount->rebuildAll();

        return back()->with('success', sprintf(
            'Coverage rebuilt — %d area(s) created, %d updated, %d coverage rows.',
            $result['areas_created'],
            $result['areas_updated'],
            $result['pivots'],
        ));
    }

    /** Force the public site to pick up changes immediately. */
    public function clearCache()
    {
        FrontendCache::bump();

        return back()->with('success', 'Public cache cleared — the website will show the latest coverage.');
    }
}
