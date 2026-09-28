<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\InquirySpamGuard;
use App\Services\MailDispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InquiryApiController extends Controller
{
    public function store(Request $request, InquirySpamGuard $spamGuard, MailDispatchService $mailDispatches): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|max:191',
            'phone' => 'nullable|string|max:64',
            'subject' => 'nullable|string|max:191',
            'message' => 'required|string|max:5000',
            'type_of_service_required' => 'required|string|max:191',
            'source_url' => 'nullable|string|max:500',
            'form_started_at' => 'nullable',
            (string) config('inquiries.captcha.token_field', 'captcha_token') => 'nullable|string|max:2048',
        ]);

        $assessment = $spamGuard->inspect($request, $validated);

        $sharedFields = array_merge(Arr::only($validated, [
            'name', 'email', 'phone', 'subject', 'message', 'type_of_service_required', 'source_url',
        ]), [
            'ip' => $request->header('CF-Connecting-IP') ?: $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'spam_score' => $assessment['score'],
            'spam_reasons' => $assessment['reasons'],
            'captcha_passed' => $assessment['captcha_passed'],
        ]);

        // Spam: save to DB for admin review, skip notification, and return a
        // correction error so legitimate users can fix bad/inappropriate input.
        if (! $assessment['passed']) {
            DB::transaction(static function () use ($sharedFields) {
                Inquiry::create(array_merge($sharedFields, [
                    'status' => Inquiry::STATUS_SPAM,
                ]));
            });

            Log::channel('mail')->warning('Inquiry blocked as spam', [
                'email' => $validated['email'] ?? null,
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

        [$inquiry, $mailDispatch] = DB::transaction(function () use ($sharedFields, $mailDispatches) {
            $inquiry = Inquiry::create(array_merge($sharedFields, [
                'status' => Inquiry::STATUS_NEW,
            ]));

            return [$inquiry, $mailDispatches->createInquiryNotification($inquiry)];
        });

        $mailDispatches->dispatchCreated($mailDispatch);

        Log::channel('mail')->info('Inquiry received', [
            'id' => $inquiry->id,
            'email' => $inquiry->email,
            'spam_score' => $inquiry->spam_score,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $inquiry->id],
            'message' => 'Thank you. We will contact you shortly.',
        ], 201);
    }
}
