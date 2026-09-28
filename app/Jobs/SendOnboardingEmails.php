<?php

namespace App\Jobs;

use App\Mail\OnboardingAdminMail;
use App\Mail\OnboardingApplicantMail;
use App\Models\OnboardingSubmission;
use App\Services\OnboardingPdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SendOnboardingEmails implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 3;
    public int $backoff = 60; // seconds between retries

    public function __construct(public readonly int $submissionId)
    {
        $this->onQueue('onboarding');
        $this->afterCommit();
    }

    /** Prevent concurrent duplicate sends for the same submission. */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping("onboarding-email:{$this->submissionId}"))
                ->releaseAfter(30)
                ->expireAfter(600),
        ];
    }

    public function handle(OnboardingPdfService $pdfService): void
    {
        $submission = OnboardingSubmission::with(['files', 'formConfig'])->find($this->submissionId);

        if (! $submission) {
            Log::warning('[SendOnboardingEmails] Submission not found', ['id' => $this->submissionId]);
            return;
        }

        $config = $submission->formConfig;

        // Admin notification with PDF attachment (idempotent — skip if already sent)
        if (! $submission->email_sent_to_admin) {
            $adminEmail = $config?->getSetting('admin_email') ?: config('mail.from.address');
            try {
                $pdfContent = $pdfService->generate($submission);
                Mail::to($adminEmail)->send(new OnboardingAdminMail($submission, $pdfContent));
                $submission->update(['email_sent_to_admin' => true]);
            } catch (\Throwable $e) {
                Log::error('[SendOnboardingEmails] Admin email failed', [
                    'submission_id' => $this->submissionId,
                    'error'         => $e->getMessage(),
                ]);
                throw $e; // triggers automatic retry
            }
        }

        // Applicant confirmation (idempotent — skip if already sent)
        $sendConfirm = $config?->getSetting('send_applicant_confirmation', true);
        if ($submission->applicant_email && $sendConfirm && ! $submission->email_sent_to_applicant) {
            try {
                Mail::to($submission->applicant_email)->send(new OnboardingApplicantMail($submission, $config));
                $submission->update(['email_sent_to_applicant' => true]);
            } catch (\Throwable $e) {
                Log::error('[SendOnboardingEmails] Applicant email failed', [
                    'submission_id' => $this->submissionId,
                    'error'         => $e->getMessage(),
                ]);
                throw $e;
            }
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('[SendOnboardingEmails] Job permanently failed after all retries', [
            'submission_id' => $this->submissionId,
            'error'         => $exception->getMessage(),
        ]);
    }
}
