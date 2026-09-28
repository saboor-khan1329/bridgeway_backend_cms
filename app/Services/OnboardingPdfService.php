<?php

namespace App\Services;

use App\Models\OnboardingSubmission;

/**
 * Generates a PDF representation of an onboarding submission.
 * Uses barryvdh/laravel-dompdf if available; falls back to plain HTML string.
 */
class OnboardingPdfService
{
    public function generate(OnboardingSubmission $submission): string
    {
        $html = $this->buildHtml($submission);

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            /** @var \Barryvdh\DomPDF\PDF $pdf */
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadHTML($html)
                ->setPaper('a4', 'portrait');
            $pdf->getDomPDF()->set_option('isHtml5ParserEnabled', true);
            $pdf->getDomPDF()->set_option('isRemoteEnabled', false);

            return $pdf->output();
        }

        return $html;
    }

    private function buildHtml(OnboardingSubmission $submission): string
    {
        $steps = $submission->getSnapshotSteps();
        $data = $submission->submission_data ?? [];
        $files = $submission->relationLoaded('files') ? $submission->files : collect();
        $rows = '';

        foreach ($steps as $step) {
            $stepId = $step['id'];
            $stepTitle = htmlspecialchars($step['title'] ?? $stepId);
            $stepData = $data[$stepId] ?? [];
            $fieldsHtml = '';

            foreach ($step['fields'] ?? [] as $field) {
                if (! ($field['is_enabled'] ?? true)) {
                    continue;
                }

                $key = $field['key'];
                $label = htmlspecialchars($field['label'] ?? $key);
                $type = $field['type'] ?? 'text';

                if (in_array($type, ['static_text', 'section_header', 'group_validation'], true)) {
                    continue;
                }

                if ($type === 'file' || $type === 'file_pair') {
                    $matchedFiles = $files->filter(fn ($file) => str_starts_with($file->field_key, $key));

                    foreach ($matchedFiles as $file) {
                        $nameHtml = htmlspecialchars($file->original_name);
                        $sizeHtml = htmlspecialchars($file->formatSize());
                        $fieldsHtml .= "<tr><td class=\"lbl\">{$label} ({$file->field_key})</td><td>{$nameHtml} ({$sizeHtml})</td></tr>";
                    }

                    if ($matchedFiles->isEmpty()) {
                        $fieldsHtml .= "<tr><td class=\"lbl\">{$label}</td><td class=\"empty\">-</td></tr>";
                    }

                    continue;
                }

                if ($type === 'repeatable') {
                    $items = $stepData[$key] ?? [];

                    if (is_array($items)) {
                        foreach ($items as $index => $itemData) {
                            $num = $index + 1;

                            foreach ($field['fields'] ?? [] as $child) {
                                $childKey = $child['key'];
                                $childLabel = htmlspecialchars($child['label'] ?? $childKey);
                                $childVal = $itemData[$childKey] ?? '';
                                $valHtml = htmlspecialchars((string) $childVal);
                                $fieldsHtml .= "<tr><td class=\"lbl\">{$childLabel} (#{$num})</td><td>".($valHtml !== '' ? $valHtml : '<span class="empty">-</span>')."</td></tr>";
                            }
                        }
                    }

                    continue;
                }

                $rawVal = $stepData[$key] ?? '';
                $valHtml = htmlspecialchars((string) $rawVal);

                if ($type === 'checkbox') {
                    $valHtml = ($rawVal === true || $rawVal === '1' || $rawVal === 'true') ? 'Yes' : 'No';
                }

                $fieldsHtml .= "<tr><td class=\"lbl\">{$label}</td><td>".($valHtml !== '' ? $valHtml : '<span class="empty">-</span>').'</td></tr>';
            }

            if ($fieldsHtml !== '') {
                $rows .= "
                <tr class=\"step-header\">
                    <td colspan=\"2\">{$stepTitle}</td>
                </tr>
                {$fieldsHtml}";
            }
        }

        $refHtml = htmlspecialchars($submission->reference_number);
        $nameHtml = htmlspecialchars($submission->applicant_name ?? '-');
        $emailHtml = htmlspecialchars($submission->applicant_email ?? '-');
        $submittedHtml = htmlspecialchars($submission->submitted_at?->format('d M Y H:i') ?? '-');
        $appNameHtml = htmlspecialchars(config('app.name', 'Bridgeway Digital CMS'));

        return <<<HTML
        <!DOCTYPE html>
        <html lang="en">
        <head>
        <meta charset="UTF-8">
        <title>Onboarding Application - {$refHtml}</title>
        <style>
            body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 11px; color: #1a1a2e; margin: 0; padding: 20px; }
            h1 { font-size: 18px; color: #1a1a2e; margin: 0 0 4px; }
            .subtitle { font-size: 12px; color: #555; margin: 0 0 16px; }
            .meta { background: #f4f4f8; border: 1px solid #ddd; border-radius: 4px; padding: 10px 14px; margin-bottom: 18px; font-size: 11px; }
            .meta td { padding: 3px 12px 3px 0; }
            .meta .lbl { font-weight: bold; color: #444; width: 120px; }
            table.fields { width: 100%; border-collapse: collapse; margin-bottom: 6px; }
            table.fields td { padding: 5px 8px; vertical-align: top; border-bottom: 1px solid #eee; }
            table.fields td.lbl { width: 38%; font-weight: bold; color: #333; background: #f9f9fc; }
            tr.step-header td { background: #1a1a2e; color: #fff; font-weight: bold; font-size: 12px; padding: 7px 10px; }
            .empty { color: #aaa; }
            .footer { margin-top: 20px; text-align: center; font-size: 9px; color: #aaa; }
        </style>
        </head>
        <body>
        <h1>{$appNameHtml} - Onboarding Application</h1>
        <p class="subtitle">Onboarding Portal Submission</p>
        <table class="meta">
            <tr><td class="lbl">Reference</td><td>{$refHtml}</td></tr>
            <tr><td class="lbl">Applicant</td><td>{$nameHtml}</td></tr>
            <tr><td class="lbl">Email</td><td>{$emailHtml}</td></tr>
            <tr><td class="lbl">Submitted</td><td>{$submittedHtml}</td></tr>
        </table>
        <table class="fields">
            {$rows}
        </table>
        <div class="footer">Generated by {$appNameHtml} Admin &bull; Confidential</div>
        </body>
        </html>
        HTML;
    }
}
