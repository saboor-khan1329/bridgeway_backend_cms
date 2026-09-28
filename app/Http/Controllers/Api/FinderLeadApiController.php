<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FinderArea;
use App\Models\FinderLead;
use App\Models\Service;
use App\Services\MailDispatchService;
use App\Services\ServiceFinderSpamGuard;
use App\Support\ServiceFinderSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Quote requests from the Service Finder popup.
 *
 * Mirrors the inquiry pipeline: the same layered spam assessment, the same
 * store-then-notify-through-the-outbox flow, and the same habit of keeping
 * suspected spam in the database for review rather than discarding it.
 *
 * The one deliberate difference is the honeypot. The inquiry guard's decoy
 * field is literally named "company", and this form asks for a real company
 * name, so ServiceFinderSpamGuard swaps in a different decoy.
 */
class FinderLeadApiController extends Controller
{
    public function store(
        Request $request,
        ServiceFinderSpamGuard $spamGuard,
        MailDispatchService $mailDispatches,
    ): JsonResponse {
        // Environment kill switch — refuse submissions outright when the
        // feature is switched off, so a cached page cannot keep posting.
        if (! config('service_finder.enabled', true)) {
            return response()->json([
                'success' => false,
                'data' => ['id' => null],
                'message' => 'Quote requests are temporarily unavailable. Please use the contact form.',
            ], 503);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:40',
            'company' => 'nullable|string|max:120',
            'email' => 'required|email|max:190',
            'service_id' => 'nullable|integer|exists:services,id',
            'service_label' => 'nullable|string|max:190',
            'finder_area_id' => 'nullable|integer|exists:finder_areas,id',
            'postcode_or_area' => 'required|string|max:120',
            'start_date' => 'nullable|string|max:64',
            'origin' => 'nullable|string|in:'.implode(',', FinderLead::ORIGINS),
            // Which package tier the visitor clicked, when the lead came from
            // a packages section. Kept out of its own column on purpose: it is
            // free text authored by the content team, not a foreign key, and
            // an operator only ever reads it alongside the rest of the lead.
            'package_label' => 'nullable|string|max:190',
            'source_url' => 'nullable|string|max:500',
            'form_started_at' => 'nullable',
            ServiceFinderSettings::get('lead_honeypot_field') => 'nullable|string|max:190',
            (string) config('inquiries.captcha.token_field', 'captcha_token') => 'nullable|string|max:2048',
        ]);

        $service = isset($validated['service_id'])
            ? Service::query()->find($validated['service_id'])
            : null;
        $area = isset($validated['finder_area_id'])
            ? FinderArea::query()->find($validated['finder_area_id'])
            : null;

        // The guard scans free text for links and blocked keywords; give it
        // the fields this form actually collects.
        $assessment = $spamGuard->inspect($request, [
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'subject' => $validated['postcode_or_area'],
            'message' => trim(($validated['company'] ?? '').' '.($validated['service_label'] ?? '')),
        ]);

        $fields = array_merge(Arr::only($validated, [
            'name', 'phone', 'company', 'email', 'service_id',
            'finder_area_id', 'postcode_or_area', 'start_date',
        ]), [
            'service_label' => $validated['service_label'] ?? $service?->title,
            'origin' => $validated['origin'] ?? FinderLead::ORIGIN_RESULT_CARD,
            'spam_score' => $assessment['score'],
            'spam_reasons' => $assessment['reasons'],
            'captcha_passed' => $assessment['captcha_passed'],
            'meta' => [
                'ip' => $request->header('CF-Connecting-IP') ?: $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 500),
                'source_url' => $validated['source_url'] ?? null,
                'area_name' => $area?->name,
                'package_label' => $validated['package_label'] ?? null,
            ],
        ]);

        if (! $assessment['passed']) {
            DB::transaction(static function () use ($fields) {
                FinderLead::create(array_merge($fields, ['status' => FinderLead::STATUS_SPAM]));
            });

            Log::channel('mail')->warning('Service Finder lead blocked as spam', [
                'email' => $validated['email'],
                'score' => $assessment['score'],
                'reasons' => $assessment['reasons'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'data' => ['id' => null],
                'message' => 'Please review the form and provide proper, appropriate details.',
                'errors' => $assessment['errors'],
            ], 422);
        }

        [$lead, $dispatch] = DB::transaction(function () use ($fields, $mailDispatches) {
            $lead = FinderLead::create(array_merge($fields, ['status' => FinderLead::STATUS_NEW]));

            return [$lead, $mailDispatches->createFinderLeadNotification($lead)];
        });

        $mailDispatches->dispatchCreated($dispatch);

        Log::channel('mail')->info('Service Finder lead received', [
            'id' => $lead->id,
            'email' => $lead->email,
            'area' => $area?->name,
            'service' => $lead->service_label,
            'origin' => $lead->origin,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $lead->id, 'name' => $lead->name],
            'message' => ServiceFinderSettings::render('success_heading_template', ['name' => $lead->name]),
        ], 201);
    }
}
