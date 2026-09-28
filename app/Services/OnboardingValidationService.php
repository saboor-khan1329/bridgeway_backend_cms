<?php

namespace App\Services;

use App\Models\OnboardingFormConfig;
use Illuminate\Http\Request;

/**
 * Validates a submitted onboarding form against the active form configuration.
 *
 * Frontend FormData key convention:
 *   Scalars: submission_data[{stepId}][{key}]
 *   Files:   files[{stepId}][{key}]  |  files[{stepId}][{key}][front|back]
 *
 * Returns a flat { field_path => [errors] } map (empty = passed).
 */
class OnboardingValidationService
{
    public function validate(Request $request, OnboardingFormConfig $config): array
    {
        $errors = [];
        $steps  = $config->getSteps();

        foreach ($steps as $step) {
            $stepId = $step['id'];

            foreach ($step['fields'] ?? [] as $field) {
                if (! ($field['is_enabled'] ?? true)) {
                    continue;
                }

                $key  = $field['key'];
                $type = $field['type'] ?? 'text';

                if ($type === 'repeatable') {
                    $this->validateRepeatable($request, $field, $stepId, $errors);
                    continue;
                }

                if (in_array($type, ['file', 'file_pair'], true)) {
                    $this->validateFileField($request, $field, $stepId, $errors);
                    continue;
                }

                if (in_array($type, ['static_text', 'group_validation'], true)) {
                    continue;
                }

                // Error key stays as {stepId}.{key} so the frontend can map it back
                $errPath = "{$stepId}.{$key}";
                $value   = $request->input("submission_data.{$errPath}");
                $this->validateScalarField($value, $field, $errPath, $errors);
            }
        }

        $this->runGroupValidations($request, $steps, $errors);

        return $errors;
    }

    private function validateScalarField(mixed $value, array $field, string $path, array &$errors): void
    {
        $type     = $field['type'] ?? 'text';
        $required = (bool) ($field['required'] ?? false);
        $label    = $field['label'] ?? $path;
        $rules    = $field['validation'] ?? [];

        $strValue = $value !== null ? trim((string) $value) : '';
        $isEmpty  = $strValue === '' || $value === null;

        if ($required && $isEmpty) {
            $errors[$path][] = "{$label} is required.";
            return;
        }

        if ($isEmpty) {
            return;
        }

        if ($type === 'email' && ! filter_var($strValue, FILTER_VALIDATE_EMAIL)) {
            $errors[$path][] = "{$label} must be a valid email address.";
        }

        if (isset($rules['min']) && mb_strlen($strValue) < (int) $rules['min']) {
            $errors[$path][] = "{$label} must be at least {$rules['min']} characters.";
        }

        if (isset($rules['max']) && mb_strlen($strValue) > (int) $rules['max']) {
            $errors[$path][] = "{$label} must not exceed {$rules['max']} characters.";
        }

        if (! empty($rules['pattern'])) {
            $pattern = '@' . str_replace('@', '\@', $rules['pattern']) . '@';
            if (! preg_match($pattern, $strValue)) {
                $errors[$path][] = $rules['pattern_message'] ?? "{$label} format is invalid.";
            }
        }

        if ($type === 'date' && $strValue !== '' && ! strtotime($strValue)) {
            $errors[$path][] = "{$label} must be a valid date.";
        }
    }

    private function validateFileField(Request $request, array $field, string $stepId, array &$errors): void
    {
        $key      = $field['key'];
        $required = (bool) ($field['required'] ?? false);
        $label    = $field['label'] ?? $key;
        $type     = $field['type'] ?? 'file';
        $rules    = $field['validation'] ?? [];
        $maxMb    = (float) ($rules['max_size_mb'] ?? 10);

        if ($type === 'file') {
            $errPath = "{$stepId}.{$key}";
            $file    = $request->file("files.{$stepId}.{$key}");

            if ($required && ! $file) {
                $errors[$errPath][] = "{$label} is required.";
                return;
            }

                if ($file && $file->isValid()) {
                    $this->assertAcceptedMime($file, $rules, $errPath, $label, $errors);
                    $this->assertFileSizeMb($file, $maxMb, $errPath, $label, $errors);
                }
        }

        if ($type === 'file_pair') {
            foreach (['front', 'back'] as $side) {
                $errPath = "{$stepId}.{$key}.{$side}";
                $file    = $request->file("files.{$stepId}.{$key}.{$side}");

                if ($required && ! $file) {
                    $errors[$errPath][] = "{$label} ({$side}) is required.";
                    continue;
                }

                if ($file && $file->isValid()) {
                    $this->assertAcceptedMime($file, $rules, $errPath, "{$label} ({$side})", $errors);
                    $this->assertFileSizeMb($file, $maxMb, $errPath, "{$label} ({$side})", $errors);
                }
            }
        }
    }

    private function assertFileSizeMb(
        \Illuminate\Http\UploadedFile $file,
        float $maxMb,
        string $path,
        string $label,
        array &$errors,
    ): void {
        $maxBytes = (int) ($maxMb * 1_048_576);
        if ($file->getSize() > $maxBytes) {
            $errors[$path][] = "{$label} must not exceed {$maxMb} MB.";
        }
    }

    private function assertAcceptedMime(
        \Illuminate\Http\UploadedFile $file,
        array $rules,
        string $path,
        string $label,
        array &$errors,
    ): void {
        $accept = collect(explode(',', (string) ($rules['accept'] ?? 'image/jpeg,image/jpg,image/png,application/pdf')))
            ->map(fn ($mime) => trim($mime))
            ->filter()
            ->values()
            ->all();

        if ($accept !== [] && ! in_array($file->getMimeType(), $accept, true)) {
            $errors[$path][] = "{$label} must be one of: ".implode(', ', $accept).'.';
        }
    }

    private function validateRepeatable(Request $request, array $field, string $stepId, array &$errors): void
    {
        $key      = $field['key'];
        $children = $field['fields'] ?? [];
        $minItems = (int) ($field['min_items'] ?? 0);
        $maxItems = $field['max_items'] ?? null;

        $items = $request->input("submission_data.{$stepId}.{$key}", []);

        if (! is_array($items)) {
            if ($minItems > 0) {
                $errors[$key][] = "At least {$minItems} {$field['label']} record(s) required.";
            }
            return;
        }

        $count = count($items);

        if ($minItems > 0 && $count < $minItems) {
            $errors[$key][] = "At least {$minItems} {$field['label']} record(s) required.";
        }

        if ($maxItems !== null && $count > (int) $maxItems) {
            $errors[$key][] = "Maximum {$maxItems} {$field['label']} records allowed.";
        }

        foreach ($items as $index => $itemData) {
            if (! is_array($itemData)) {
                continue;
            }
            foreach ($children as $child) {
                if (! ($child['is_enabled'] ?? true)) {
                    continue;
                }
                $childType = $child['type'] ?? 'text';
                if (in_array($childType, ['file', 'file_pair'], true)) {
                    continue;
                }
                $path  = "{$key}.{$index}.{$child['key']}";
                $value = $itemData[$child['key']] ?? null;
                $this->validateScalarField($value, $child, $path, $errors);
            }
        }
    }

    private function runGroupValidations(Request $request, array $steps, array &$errors): void
    {
        foreach ($steps as $step) {
            $stepId = $step['id'];

            foreach ($step['fields'] ?? [] as $field) {
                if (($field['type'] ?? '') !== 'group_validation') {
                    continue;
                }

                $keys    = $field['keys'] ?? [];
                $minFill = (int) ($field['min_filled'] ?? 1);
                $message = $field['message'] ?? 'At least one field in this group is required.';
                $path    = "{$stepId}._group." . implode('_', $keys);

                $filled = 0;
                foreach ($keys as $k) {
                    $hasFile  = $request->hasFile("files.{$stepId}.{$k}");
                    $hasValue = trim((string) $request->input("submission_data.{$stepId}.{$k}")) !== '';
                    if ($hasFile || $hasValue) {
                        $filled++;
                    }
                }

                if ($filled < $minFill) {
                    $errors[$path][] = $message;
                }
            }
        }
    }
}
