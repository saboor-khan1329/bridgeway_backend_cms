<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderGeocode;
use App\Services\FinderUsageService;
use Illuminate\Http\Request;

/**
 * How much of Google's metered API the finder has actually used.
 *
 * Google does not hand live quota or billing figures to an application key —
 * reading the real number needs a Cloud Billing service-account credential
 * this project does not have configured. What is shown here instead is what
 * our own code asked Google for, which is knowable for free and normally
 * tracks the real figure closely. See FinderUsageService's docblock.
 */
class FinderUsageController extends Controller
{
    public function index(Request $request, FinderUsageService $usage)
    {
        $days = (int) $request->integer('days', 30);
        $days = in_array($days, [7, 30, 90], true) ? $days : 30;

        return view('admin.finder.usage', [
            'title' => 'Service Finder — Usage',
            'days' => $days,
            'series' => $usage->series($days),
            'allTime' => $usage->allTimeTotals(),
            // A separate, one-off figure: the CSV import's own fallback
            // geocoding, already permanently recorded per postcode. Shown
            // alongside the live counters for the full cost picture, but it
            // is not part of the day-by-day graph — it happens on demand,
            // from the Import screen, not from ordinary visitor traffic.
            'importGeocodesGoogle' => FinderGeocode::query()->where('source', FinderGeocode::SOURCE_GOOGLE)->count(),
            'importGeocodesFree' => FinderGeocode::query()->where('source', '!=', FinderGeocode::SOURCE_GOOGLE)->count(),
        ]);
    }
}
