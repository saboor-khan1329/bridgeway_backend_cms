<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Services\InquirySpamGuard;
use App\Services\MailDispatchService;
use App\Support\FrontendFormRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * POST /api/forms — every form the Next.js frontend submits.
 *
 * The frontend posts `{ formName, payload }` for all six of its forms, so this
 * is one endpoint rather than six: the shape of `payload` is what varies, and
 * FrontendFormRegistry holds the per-form rules and field mapping.
 *
 * Everything past validation is the existing inquiry pipeline, unchanged —
 * the same spam guard, the same transaction, the same mail dispatch, the same
 * admin queue. InquiryApiController remains the endpoint for the other site's
 * single contact form; neither is affected by the other.
 */
class FormSubmissionController extends Controller
{
    public function store(Request $request, InquirySpamGuard $spamGuard, MailDispatchService $mailDispatches): JsonResponse
    {
        // Which form this is, before anything else — the payload rules cannot
        // be chosen until it is known. Validated by hand rather than with
        // $request->validate() so a bad envelope returns the same JSON shape as
        // a bad field, and the frontend has one error format to read.
        $envelope = Validator::make($request->all(), [
            'formName' => ['required', 'string', Rule::in(FrontendFormRegistry::names())],
            'payload' => ['required', 'array'],
            'sourceUrl' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'form_started_at' => ['nullable', 'integer', 'min:1'],
            'company' => ['nullable', 'string', 'max:100'],
        ]);

        if ($envelope->fails()) {
            return $this->validationFailed($envelope->errors()->toArray());
        }

        $formName = (string) $request->input('formName');
        $form = FrontendFormRegistry::get($formName);

        $validator = Validator::make(
            $request->all(),
            FrontendFormRegistry::rulesFor($formName),
            [],
            FrontendFormRegistry::attributesFor($formName)
        );

        if ($validator->fails()) {
            return $this->validationFailed($validator->errors()->toArray());
        }

        /** @var array<string, mixed> $payload */
        $payload = $validator->validated()['payload'] ?? [];

        $mapped = $this->mapToInquiry($payload, $form);
        $assessment = $spamGuard->inspect($request, $mapped);
        $attachment = $payload['attachment'] ?? null;
        unset($payload['attachment']);

        $sharedFields = array_merge($mapped, [
            'form_name' => $formName,
            'subject' => $form['subject'] ?? 'Website enquiry',
            'source_url' => $this->sourceUrl($request),
            'details' => $this->extraDetails($payload, $form),
            'ip' => $request->header('CF-Connecting-IP') ?: $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 500),
            'spam_score' => $assessment['score'],
            'spam_reasons' => $assessment['reasons'],
            'captcha_passed' => $assessment['captcha_passed'],
        ]);

        // Spam is stored for admin review but never notified, and the user gets
        // a correction message so a false positive is recoverable.
        if (! $assessment['passed']) {
            DB::transaction(static function () use ($sharedFields) {
                Inquiry::create(array_merge($sharedFields, [
                    'status' => Inquiry::STATUS_SPAM,
                ]));
            });

            Log::channel('mail')->warning('Form submission blocked as spam', [
                'form' => $formName,
                'email' => $sharedFields['email'] ?? null,
                'score' => $assessment['score'],
                'reasons' => $assessment['reasons'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Please review the form and provide proper, appropriate details.',
                'fieldErrors' => $this->toFieldErrors($assessment['errors'] ?? [], $form),
            ], 422);
        }

        $attachmentPath = null;
        try {
            if ($attachment) {
                $attachmentPath = $attachment->store('inquiry-attachments', 'local');
                if (! $attachmentPath) {
                    throw new \RuntimeException('Unable to store the attachment.');
                }
                $sharedFields['details']['attachment'] = [
                    'path' => $attachmentPath,
                    'name' => $attachment->getClientOriginalName(),
                    'size' => $attachment->getSize(),
                    'mime' => $attachment->getMimeType(),
                ];
            }
            [$inquiry, $mailDispatch] = DB::transaction(function () use ($sharedFields, $mailDispatches) {
                $inquiry = Inquiry::create(array_merge($sharedFields, [
                    'status' => Inquiry::STATUS_NEW,
                ]));

                return [$inquiry, $mailDispatches->createInquiryNotification($inquiry)];
            });
        } catch (\Throwable $exception) {
            if ($attachmentPath) {
                Storage::disk('local')->delete($attachmentPath);
            }
            throw $exception;
        }

        $mailDispatches->dispatchCreated($mailDispatch);

        Log::channel('mail')->info('Form submission received', [
            'id' => $inquiry->id,
            'form' => $formName,
            'email' => $inquiry->email,
            'spam_score' => $inquiry->spam_score,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $inquiry->id],
            'message' => $form['success'] ?? 'Thank you. We will contact you shortly.',
        ], 201);
    }

    /**
     * Move the mapped fields onto Inquiry's own columns.
     *
     * `message` is required by the inquiries table but optional on several of
     * these forms, so a blank one is replaced by a line naming the form. That
     * keeps the column honest without forcing a user to write something.
     */
    private function mapToInquiry(array $payload, array $form): array
    {
        $mapped = [];

        foreach ($form['map'] ?? [] as $column => $field) {
            $value = $payload[$field] ?? null;

            if (is_string($value)) {
                $value = trim($value);
            }

            $mapped[$column] = $value === '' ? null : $value;
        }

        $mapped['name'] ??= null;
        $mapped['email'] ??= null;
        $mapped['phone'] ??= null;

        // A form with a single "enter your details" box collects a contact
        // detail without saying which kind. Reading it here means an admin gets
        // a usable address or number in the right column instead of a lead
        // whose only contact detail is buried in the message body.
        if ($mapped['email'] === null) {
            [$email, $phone] = $this->splitContactDetail($mapped['message'] ?? null);

            $mapped['email'] ??= $email;
            $mapped['phone'] ??= $phone;
        }

        if (($mapped['message'] ?? null) === null) {
            $mapped['message'] = sprintf('Submitted via the %s form.', $form['subject'] ?? 'website');
        }

        // The table requires a name; the newsletter form has no name field.
        if (($mapped['name'] ?? null) === null) {
            $mapped['name'] = $this->nameFromEmail($mapped['email'] ?? null);
        }

        return $mapped;
    }

    /**
     * Read a free-text contact detail as an email or a phone number.
     *
     * @return array{0: ?string, 1: ?string} [email, phone]
     */
    private function splitContactDetail(?string $value): array
    {
        $value = trim((string) $value);

        if ($value === '') {
            return [null, null];
        }

        if (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return [$value, null];
        }

        // Enough digits to be a phone number, and nothing but the characters
        // one is normally written with.
        $digits = preg_replace('/\D/', '', $value);

        if (strlen((string) $digits) >= 7 && preg_match('/^[0-9 ()+\-.]+$/', $value)) {
            return [null, mb_substr($value, 0, 64)];
        }

        return [null, null];
    }

    private function nameFromEmail(?string $email): string
    {
        if (! $email) {
            return 'Website visitor';
        }

        return substr((string) strstr($email, '@', true) ?: $email, 0, 191);
    }

    /**
     * The per-form extras, kept as JSON.
     *
     * Anything the map already stored in a column is skipped, so a value is
     * never held in two places and cannot disagree with itself.
     */
    private function extraDetails(array $payload, array $form): ?array
    {
        $mappedFields = array_values($form['map'] ?? []);

        $details = [];

        foreach ($payload as $field => $value) {
            if (in_array($field, $mappedFields, true)) {
                continue;
            }

            if ($value === null || $value === '' || $value === []) {
                continue;
            }

            $details[$field] = $value;
        }

        return $details === [] ? null : $details;
    }

    /**
     * The page the form was submitted from.
     *
     * The frontend proxy sends this as validated envelope metadata. Direct
     * API callers can still be traced through their Referer when it is absent.
     */
    private function sourceUrl(Request $request): ?string
    {
        $sourceUrl = $request->input('sourceUrl')
            ?: $request->header('Referer');

        return $sourceUrl ? substr((string) $sourceUrl, 0, 500) : null;
    }

    /**
     * Laravel's `payload.email` keys back to the `email` the frontend knows,
     * as a flat field => first-message map, which is what the Next.js route
     * handler forwards to react-hook-form's setError().
     */
    private function validationFailed(array $errors): JsonResponse
    {
        $fieldErrors = [];

        foreach ($errors as $key => $messages) {
            $field = str_starts_with($key, 'payload.')
                ? substr($key, strlen('payload.'))
                : $key;

            // `subscriptions.0` belongs to the `subscriptions` field.
            $field = explode('.', $field)[0];

            $fieldErrors[$field] ??= is_array($messages) ? ($messages[0] ?? null) : $messages;
        }

        return response()->json([
            'success' => false,
            'message' => 'Please correct the highlighted fields and try again.',
            'fieldErrors' => array_filter($fieldErrors),
        ], 422);
    }

    /**
     * Spam-guard errors are keyed by inquiry column ('message', 'name'), so
     * they are translated back to the field the user can actually see.
     */
    private function toFieldErrors(array $errors, array $form): array
    {
        $map = $form['map'] ?? [];
        $fieldErrors = [];

        foreach ($errors as $column => $message) {
            $field = $map[$column] ?? $column;
            $fieldErrors[$field] = $message;
        }

        return $fieldErrors;
    }
}
