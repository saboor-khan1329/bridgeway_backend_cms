<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\FinderArea;
use App\Models\FinderLead;
use App\Services\MailDispatchService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Quote requests captured by the Service Finder.
 *
 * Mirrors the inquiries module so the sales team works the same way in both
 * places: filter, open, mark progress, export. Suspected spam is kept and
 * filterable rather than deleted, because the spam score is a judgement and
 * occasionally a real lead lands in it.
 */
class FinderLeadController extends Controller
{
    public function index(Request $request)
    {
        $query = FinderLead::query()->with(['area', 'service']);

        // Spam is excluded by default so the working list stays clean.
        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', '!=', FinderLead::STATUS_SPAM);
        }

        if ($request->filled('area_id')) {
            $query->where('finder_area_id', $request->query('area_id'));
        }

        if ($request->filled('origin')) {
            $query->where('origin', $request->query('origin'));
        }

        return $this->renderAdminIndex(
            $query->latest('id'),
            [
                'title' => 'Quote Requests',
                'routes' => [
                    'show' => 'admin.finder-leads.show',
                    'destroy' => 'admin.finder-leads.destroy',
                ],
                'columns' => [
                    'id' => 'ID',
                    'name' => 'Name',
                    'email' => 'Email',
                    'service_label' => 'Service',
                    'postcode_or_area' => 'Location',
                    'status' => 'Status',
                    'created_at' => 'Received',
                ],
                'search' => ['name', 'email', 'phone', 'company', 'postcode_or_area', 'service_label'],
                'filters' => [
                    'statuses' => FinderLead::STATUSES,
                    'origins' => FinderLead::ORIGINS,
                    'areas' => FinderArea::query()->orderBy('name')->pluck('name', 'id')->all(),
                ],
            ],
            'admin.finder.leads.index'
        );
    }

    public function show(FinderLead $finderLead)
    {
        $finderLead->markAsRead();
        $finderLead->load(['area', 'service']);

        return view('admin.finder.leads.show', [
            'title' => 'Quote Request #'.$finderLead->id,
            'lead' => $finderLead,
        ]);
    }

    public function updateStatus(Request $request, FinderLead $finderLead)
    {
        $data = $request->validate([
            'status' => 'required|string|in:'.implode(',', FinderLead::STATUSES),
        ]);

        $finderLead->update(['status' => $data['status']]);

        return back()->with('success', 'Status updated to '.$data['status'].'.');
    }

    /** Re-queue the admin notification, e.g. after fixing mail credentials. */
    public function resend(FinderLead $finderLead, MailDispatchService $mailDispatches)
    {
        $dispatch = $mailDispatches->createFinderLeadNotification($finderLead);

        if (! $dispatch) {
            return back()->with('error', 'Quote notifications are switched off in the configurator.');
        }

        $mailDispatches->dispatchCreated($dispatch);

        return back()->with('success', 'Notification re-queued.');
    }

    public function destroy(FinderLead $finderLead)
    {
        $finderLead->delete();

        return back()->with('success', 'Quote request deleted.');
    }

    /**
     * CSV of the current filter selection, streamed so a large export never
     * builds the whole file in memory.
     */
    public function export(Request $request): StreamedResponse
    {
        $query = FinderLead::query()->with(['area'])->latest('id');

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        } else {
            $query->where('status', '!=', FinderLead::STATUS_SPAM);
        }

        $filename = 'service-finder-leads-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($query) {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'ID', 'Received', 'Name', 'Phone', 'Email', 'Company',
                'Service', 'Postcode/Area', 'Coverage area', 'Start date',
                'Came from', 'Status',
            ]);

            $query->chunk(500, function ($leads) use ($handle) {
                foreach ($leads as $lead) {
                    fputcsv($handle, [
                        $lead->id,
                        $lead->created_at?->format('Y-m-d H:i'),
                        $lead->name,
                        $lead->phone,
                        $lead->email,
                        $lead->company,
                        $lead->service_label,
                        $lead->postcode_or_area,
                        $lead->area?->name,
                        $lead->start_date,
                        $lead->origin,
                        $lead->status,
                    ]);
                }
            });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
