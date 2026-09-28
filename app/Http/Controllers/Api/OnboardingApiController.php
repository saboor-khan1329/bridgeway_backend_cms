<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendOnboardingEmails;
use App\Models\OnboardingFormConfig;
use App\Models\OnboardingSubmission;
use App\Models\OnboardingSubmissionFile;
use App\Models\SiteSetting;
use App\Services\OnboardingValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OnboardingApiController extends Controller
{
    public function __construct(
        private readonly OnboardingValidationService $validationService,
    ) {
    }

    /**
     * GET /api/onboarding/config
     * Returns the active form configuration for the frontend.
     */
    public function config(): JsonResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $config = OnboardingFormConfig::active();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'No active onboarding form is configured.',
            ], 503);
        }

        return response()->json([
            'success' => true,
            'data'    => $config->toApiArray(),
        ]);
    }

    /**
     * Admin kill-switch: Site Settings → General → Onboarding Page.
     * Mirrors the frontend's own check (frontend/src/app/onboarding/page.js)
     * so the raw API can't be used to bypass the feature being turned off —
     * both layers read the same single source of truth (SiteSetting).
     */
    private function disabledResponse(): ?JsonResponse
    {
        $enabled = filter_var(
            SiteSetting::allCached()['onboarding_enabled'] ?? '0',
            FILTER_VALIDATE_BOOLEAN
        );

        if ($enabled) {
            return null;
        }

        return response()->json([
            'success' => false,
            'message' => 'Onboarding is not currently available.',
        ], 503);
    }

    /**
     * POST /api/onboarding/submit
     * Accepts multipart/form-data.
     * Scalars: submission_data[{stepId}][{key}]
     * Files:   files[{stepId}][{key}] | files[{stepId}][{key}][front|back]
     * Returns JSON { success, message, reference_number }.
     */
    public function submit(Request $request): JsonResponse
    {
        if ($disabled = $this->disabledResponse()) {
            return $disabled;
        }

        $config = OnboardingFormConfig::active();

        if (! $config) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding form is not currently available. Please try again later.',
            ], 503);
        }

        // Validate files globally (type + size) before field-level validation
        $fileErrors = $this->validateUploadedFiles($request);
        if (! empty($fileErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'File validation failed.',
                'errors'  => $fileErrors,
            ], 422);
        }

        // Validate all form fields against the form config
        $fieldErrors = $this->validationService->validate($request, $config);
        if (! empty($fieldErrors)) {
            return response()->json([
                'success' => false,
                'message' => 'Please correct the highlighted errors and try again.',
                'errors'  => $fieldErrors,
            ], 422);
        }

        try {
            $submission = DB::transaction(function () use ($request, $config) {
                $reference      = OnboardingSubmission::generateReference();
                $stepData       = $this->extractStepData($request, $config);
                $applicantName  = $this->extractApplicantName($stepData);
                $applicantEmail = $this->extractApplicantEmail($stepData);

                $submission = OnboardingSubmission::create([
                    'reference_number'    => $reference,
                    'form_config_id'      => $config->id,
                    'form_config_snapshot'=> $config->form_config,
                    'submission_data'     => $stepData,
                    'applicant_name'      => $applicantName,
                    'applicant_email'     => $applicantEmail,
                    'status'              => OnboardingSubmission::STATUS_PENDING,
                    'ip_address'          => $request->ip(),
                    'user_agent'          => substr((string) $request->userAgent(), 0, 500),
                ]);

                $this->storeUploadedFiles($request, $submission, $config);

                return $submission;
            });

            // Dispatch email job to queue (non-blocking, with overlap protection)
            SendOnboardingEmails::dispatch($submission->id);

            // Bust dashboard stats cache so counts update immediately
            Cache::forget('dashboard_submission_stats');

            return response()->json([
                'success'          => true,
                'message'          => 'Your application has been submitted successfully.',
                'reference_number' => $submission->reference_number,
            ], 201);

        } catch (\Throwable $e) {
            Log::error('Onboarding submission failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Submission failed due to a server error. Please try again or contact support.',
            ], 500);
        }
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Recursively flatten all uploaded files from allFiles() into a flat key→UploadedFile map.
     */
    private function flattenFiles(array $files, string $prefix, array &$flat): void
    {
        foreach ($files as $key => $value) {
            $path = $prefix !== '' ? "{$prefix}.{$key}" : (string) $key;
            if ($value instanceof \Illuminate\Http\UploadedFile) {
                $flat[$path] = $value;
            } elseif (is_array($value)) {
                $this->flattenFiles($value, $path, $flat);
            }
        }
    }

    private function validateUploadedFiles(Request $request): array
    {
        $errors   = [];
        $allowed  = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
        $maxBytes = 10 * 1_048_576; // 10 MB hard cap per file

        $flat = [];
        $this->flattenFiles($request->allFiles(), '', $flat);

        foreach ($flat as $path => $file) {
            $this->checkFile($file, $path, $allowed, $maxBytes, $errors);
        }

        return $errors;
    }

    private function checkFile(mixed $file, string $key, array $allowed, int $maxBytes, array &$errors): void
    {
        if (! ($file instanceof \Illuminate\Http\UploadedFile) || ! $file->isValid()) {
            $errors[$key][] = 'File upload failed or is corrupted.';
            return;
        }

        if (! in_array($file->getMimeType(), $allowed, true)) {
            $errors[$key][] = 'Only JPG, PNG, and PDF files are accepted.';
        }

        if ($file->getSize() > $maxBytes) {
            $errors[$key][] = 'File size must not exceed 10 MB.';
        }
    }

    /**
     * Extract scalar/repeatable field values from submission_data[stepId][key] inputs.
     */
    private function extractStepData(Request $request, OnboardingFormConfig $config): array
    {
        $steps = $config->getSteps();
        $data  = [];

        foreach ($steps as $step) {
            $stepId   = $step['id'];
            $stepData = [];

            foreach ($step['fields'] ?? [] as $field) {
                $key  = $field['key'];
                $type = $field['type'] ?? 'text';

                if (in_array($type, ['file', 'file_pair', 'static_text', 'group_validation'], true)) {
                    continue;
                }

                if ($type === 'repeatable') {
                    $stepData[$key] = $this->extractRepeatableData($request, $stepId, $key, $field['fields'] ?? []);
                    continue;
                }

                $value = $request->input("submission_data.{$stepId}.{$key}");
                if ($value !== null) {
                    $stepData[$key] = is_string($value) ? substr(strip_tags($value), 0, 10000) : $value;
                }
            }

            if (! empty($stepData)) {
                $data[$stepId] = $stepData;
            }
        }

        return $data;
    }

    private function extractRepeatableData(Request $request, string $stepId, string $key, array $childFields): array
    {
        $items = $request->input("submission_data.{$stepId}.{$key}", []);
        if (! is_array($items)) {
            return [];
        }

        $result = [];
        foreach ($items as $index => $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            $item = [];
            foreach ($childFields as $field) {
                $fieldKey = $field['key'];
                $type     = $field['type'] ?? 'text';
                if (in_array($type, ['file', 'file_pair'], true)) {
                    continue;
                }
                if (isset($itemData[$fieldKey])) {
                    $item[$fieldKey] = is_string($itemData[$fieldKey])
                        ? substr(strip_tags($itemData[$fieldKey]), 0, 5000)
                        : $itemData[$fieldKey];
                }
            }
            $result[(int) $index] = $item;
        }

        return array_values($result);
    }

    /**
     * Store uploaded files from files[stepId][key] inputs.
     */
    private function storeUploadedFiles(Request $request, OnboardingSubmission $submission, OnboardingFormConfig $config): void
    {
        foreach ($config->getSteps() as $step) {
            $stepId = $step['id'];

            foreach ($step['fields'] ?? [] as $field) {
                $key   = $field['key'];
                $type  = $field['type'] ?? 'text';
                $label = $field['label'] ?? $key;

                if ($type === 'file') {
                    $file = $request->file("files.{$stepId}.{$key}");
                    if ($file && $file->isValid()) {
                        $this->persistFile($file, $submission, $key, $label, null);
                    }
                }

                if ($type === 'file_pair') {
                    foreach (['front', 'back'] as $side) {
                        $file = $request->file("files.{$stepId}.{$key}.{$side}");
                        if ($file && $file->isValid()) {
                            $this->persistFile($file, $submission, "{$key}_{$side}", "{$label} ({$side})", null);
                        }
                    }
                }

                if ($type === 'repeatable') {
                    $this->storeRepeatableFiles($request, $submission, $field, $stepId);
                }
            }
        }
    }

    private function storeRepeatableFiles(
        Request $request,
        OnboardingSubmission $submission,
        array $field,
        string $stepId,
    ): void {
        $key      = $field['key'];
        $children = $field['fields'] ?? [];

        // Use submission_data indices to know which items were submitted
        $items = $request->input("submission_data.{$stepId}.{$key}", []);
        if (! is_array($items)) {
            return;
        }

        foreach (array_keys($items) as $index) {
            foreach ($children as $child) {
                $childKey   = $child['key'];
                $childType  = $child['type'] ?? 'text';
                $childLabel = $child['label'] ?? $childKey;

                if ($childType === 'file') {
                    $file = $request->file("files.{$stepId}.{$key}.{$index}.{$childKey}");
                    if ($file && $file->isValid()) {
                        $this->persistFile($file, $submission, "{$key}_{$childKey}", $childLabel, (int) $index);
                    }
                }

                if ($childType === 'file_pair') {
                    foreach (['front', 'back'] as $side) {
                        $file = $request->file("files.{$stepId}.{$key}.{$index}.{$childKey}.{$side}");
                        if ($file && $file->isValid()) {
                            $this->persistFile($file, $submission, "{$key}_{$childKey}_{$side}", "{$childLabel} ({$side})", (int) $index);
                        }
                    }
                }
            }
        }
    }

    private function persistFile(
        \Illuminate\Http\UploadedFile $file,
        OnboardingSubmission $submission,
        string $fieldKey,
        string $fieldLabel,
        ?int $itemIndex,
    ): void {
        $dir      = "onboarding/{$submission->id}";
        $filename = Str::uuid() . '.' . $file->getClientOriginalExtension();
        $path     = "{$dir}/{$filename}";

        $file->storeAs($dir, $filename, ['disk' => 'local']);

        OnboardingSubmissionFile::create([
            'submission_id' => $submission->id,
            'field_key'     => $fieldKey,
            'field_label'   => $fieldLabel,
            'item_index'    => $itemIndex,
            'file_path'     => $path,
            'original_name' => $file->getClientOriginalName(),
            'file_size'     => $file->getSize(),
            'mime_type'     => $file->getMimeType(),
        ]);
    }

    private function extractApplicantName(array $stepData): ?string
    {
        $personal  = $stepData['personal'] ?? [];
        $firstName = $personal['first_names'] ?? $personal['firstName'] ?? '';
        $surname   = $personal['surname'] ?? '';
        $full      = trim("{$firstName} {$surname}");
        return $full !== '' ? substr($full, 0, 255) : null;
    }

    private function extractApplicantEmail(array $stepData): ?string
    {
        foreach ($stepData as $step) {
            if (is_array($step)) {
                $email = $step['email'] ?? null;
                if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    return substr($email, 0, 255);
                }
            }
        }
        return null;
    }
}
