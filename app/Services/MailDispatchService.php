<?php

namespace App\Services;

use App\Jobs\ProcessMailDispatchJob;
use App\Mail\FinderLeadReceivedMail;
use App\Mail\InquiryReceivedMail;
use App\Models\FinderLead;
use App\Models\Inquiry;
use App\Models\MailDispatch;
use App\Support\ServiceFinderSettings;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class MailDispatchService
{
    public function createInquiryNotification(Inquiry $inquiry): ?MailDispatch
    {
        if (! (bool) config('inquiries.notifications.enabled', true)) {
            return null;
        }

        $recipients = $this->inquiryRecipients($inquiry);
        $hasRecipients = ! empty($recipients);

        return MailDispatch::query()->create([
            'type' => MailDispatch::TYPE_INQUIRY_RECEIVED,
            'status' => $hasRecipients ? MailDispatch::STATUS_PENDING : MailDispatch::STATUS_FAILED,
            'mailer' => (string) config('mail.notification_mailer', config('mail.default')),
            'queue' => (string) config('mail_outbox.queue.name', 'mail-outbox'),
            'recipients' => $recipients,
            'payload' => ['inquiry_id' => $inquiry->id],
            'related_type' => Inquiry::class,
            'related_id' => $inquiry->id,
            'max_attempts' => (int) config('mail_outbox.max_attempts', 10),
            'available_at' => $hasRecipients ? now() : null,
            'error_message' => $hasRecipients ? null : 'No admin inquiry recipients are configured.',
        ]);
    }

    /**
     * Queue an admin notification for a Service Finder quote request.
     *
     * Recipients come from the finder configurator, which falls back to the
     * shared inquiry recipient list when no override is set.
     */
    public function createFinderLeadNotification(FinderLead $lead): ?MailDispatch
    {
        if (! ServiceFinderSettings::get('lead_notifications_enabled')) {
            return null;
        }

        $recipients = $this->normalizeEmails(ServiceFinderSettings::leadRecipients());
        $hasRecipients = ! empty($recipients);

        return MailDispatch::query()->create([
            'type' => MailDispatch::TYPE_FINDER_LEAD_RECEIVED,
            'status' => $hasRecipients ? MailDispatch::STATUS_PENDING : MailDispatch::STATUS_FAILED,
            'mailer' => (string) config('mail.notification_mailer', config('mail.default')),
            'queue' => (string) config('mail_outbox.queue.name', 'mail-outbox'),
            'recipients' => $recipients,
            'payload' => ['finder_lead_id' => $lead->id],
            'related_type' => FinderLead::class,
            'related_id' => $lead->id,
            'max_attempts' => (int) config('mail_outbox.max_attempts', 10),
            'available_at' => $hasRecipients ? now() : null,
            'error_message' => $hasRecipients ? null : 'No quote-request recipients are configured.',
        ]);
    }

    public function dispatchCreated(?MailDispatch $dispatch): void
    {
        if (! $dispatch || ! (bool) config('mail_outbox.enabled', true)) {
            return;
        }

        if ($dispatch->status !== MailDispatch::STATUS_PENDING) {
            return;
        }

        try {
            if ((bool) config('mail_outbox.queue.enabled', true)) {
                $this->queueOne($dispatch->id);

                return;
            }

            $this->processOne($dispatch->id);
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function dispatchDue(int $limit): int
    {
        if (! (bool) config('mail_outbox.enabled', true)) {
            return 0;
        }

        $ids = MailDispatch::query()
            ->due()
            ->orderBy('available_at')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $count = 0;

        foreach ($ids as $id) {
            if ((bool) config('mail_outbox.queue.enabled', true)) {
                $count += $this->queueOne((int) $id) ? 1 : 0;
            } else {
                $count += $this->processOne((int) $id) ? 1 : 0;
            }
        }

        return $count;
    }

    public function processOne(int|MailDispatch $dispatch): ?MailDispatch
    {
        $dispatchId = $dispatch instanceof MailDispatch ? $dispatch->id : $dispatch;
        $lock = $this->acquireLock($dispatchId);

        if (! $lock) {
            return MailDispatch::query()->find($dispatchId);
        }

        try {
            $mailDispatch = MailDispatch::query()->find($dispatchId);

            if (! $mailDispatch || ! $this->canProcessNow($mailDispatch)) {
                return $mailDispatch;
            }

            $mailDispatch->forceFill([
                'status' => MailDispatch::STATUS_PROCESSING,
                'attempts' => $mailDispatch->attempts + 1,
                'locked_at' => now(),
                'last_attempt_at' => now(),
            ])->save();

            try {
                $this->send($mailDispatch->refresh());

                $mailDispatch->forceFill([
                    'status' => MailDispatch::STATUS_SENT,
                    'available_at' => null,
                    'locked_at' => null,
                    'sent_at' => now(),
                    'error_message' => null,
                ])->save();

                Log::channel('mail')->info('Mail dispatch sent', [
                    'dispatch_id' => $mailDispatch->id,
                    'type' => $mailDispatch->type,
                    'recipients' => $mailDispatch->recipients,
                    'attempts' => $mailDispatch->attempts,
                ]);
            } catch (Throwable $exception) {
                $this->recordFailure($mailDispatch, $exception);
            }

            return $mailDispatch->refresh();
        } finally {
            $lock->release();
        }
    }

    protected function send(MailDispatch $dispatch): void
    {
        match ($dispatch->type) {
            MailDispatch::TYPE_INQUIRY_RECEIVED => $this->sendInquiryNotification($dispatch),
            MailDispatch::TYPE_FINDER_LEAD_RECEIVED => $this->sendFinderLeadNotification($dispatch),
            default => throw new RuntimeException('Unsupported mail dispatch type: '.$dispatch->type),
        };
    }

    protected function queueOne(int $dispatchId): bool
    {
        $lock = $this->acquireLock($dispatchId);

        if (! $lock) {
            return false;
        }

        $released = false;

        try {
            $dispatch = MailDispatch::query()->find($dispatchId);

            if (! $dispatch || ! $dispatch->isSendable()) {
                return false;
            }

            $dispatch->forceFill([
                'status' => MailDispatch::STATUS_PROCESSING,
                'locked_at' => now(),
            ])->save();

            $lock->release();
            $released = true;

            ProcessMailDispatchJob::dispatch($dispatch->id);

            return true;
        } catch (Throwable $exception) {
            $dispatch = MailDispatch::query()->find($dispatchId);

            if ($dispatch && $dispatch->status === MailDispatch::STATUS_PROCESSING) {
                $dispatch->forceFill([
                    'status' => $dispatch->attempts > 0 ? MailDispatch::STATUS_FAILED : MailDispatch::STATUS_PENDING,
                    'available_at' => now(),
                    'locked_at' => null,
                    'error_message' => substr($exception->getMessage(), 0, 10000),
                ])->save();
            }

            report($exception);

            return false;
        } finally {
            if (! $released) {
                $lock->release();
            }
        }
    }

    protected function canProcessNow(MailDispatch $dispatch): bool
    {
        if (! $dispatch->isSendable()) {
            return false;
        }

        if ($dispatch->status === MailDispatch::STATUS_PROCESSING) {
            return true;
        }

        if ($dispatch->status === MailDispatch::STATUS_PENDING) {
            return ! $dispatch->available_at || $dispatch->available_at->lte(now());
        }

        if ($dispatch->status === MailDispatch::STATUS_FAILED) {
            return $dispatch->available_at && $dispatch->available_at->lte(now());
        }

        return false;
    }

    protected function sendInquiryNotification(MailDispatch $dispatch): void
    {
        $inquiryId = (int) Arr::get($dispatch->payload ?? [], 'inquiry_id');
        $inquiry = Inquiry::query()->find($inquiryId);

        if (! $inquiry) {
            throw new RuntimeException('Inquiry #'.$inquiryId.' no longer exists.');
        }

        $recipients = $this->normalizeEmails($dispatch->recipients ?? []);

        if (empty($recipients)) {
            throw new RuntimeException('No recipients are available for inquiry notification.');
        }

        Mail::mailer($dispatch->mailer ?: (string) config('mail.default'))
            ->to($recipients)
            ->send(new InquiryReceivedMail($inquiry));
    }

    protected function sendFinderLeadNotification(MailDispatch $dispatch): void
    {
        $leadId = (int) Arr::get($dispatch->payload ?? [], 'finder_lead_id');
        $lead = FinderLead::query()->with(['service', 'area'])->find($leadId);

        if (! $lead) {
            throw new RuntimeException('Service Finder lead #'.$leadId.' no longer exists.');
        }

        $recipients = $this->normalizeEmails($dispatch->recipients ?? []);

        if (empty($recipients)) {
            throw new RuntimeException('No recipients are available for the quote-request notification.');
        }

        Mail::mailer($dispatch->mailer ?: (string) config('mail.default'))
            ->to($recipients)
            ->send(new FinderLeadReceivedMail($lead));
    }

    protected function recordFailure(MailDispatch $dispatch, Throwable $exception): void
    {
        $canRetry = $dispatch->attempts < $dispatch->max_attempts;

        $dispatch->forceFill([
            'status' => MailDispatch::STATUS_FAILED,
            'available_at' => $canRetry ? now()->addSeconds((int) config('mail_outbox.retry_seconds', 300)) : null,
            'locked_at' => null,
            'error_message' => substr($exception->getMessage(), 0, 10000),
        ])->save();

        Log::channel('mail')->error('Mail dispatch failed', [
            'dispatch_id' => $dispatch->id,
            'type' => $dispatch->type,
            'attempts' => $dispatch->attempts,
            'max_attempts' => $dispatch->max_attempts,
            'will_retry' => $canRetry,
            'error' => $exception->getMessage(),
        ]);

        report($exception);
    }

    public function inquiryRecipients(?Inquiry $inquiry = null): array
    {
        if ($inquiry && ! empty($inquiry->form_name)) {
            $formEmail = \App\Models\SiteSetting::where('key', 'form_email_'.$inquiry->form_name)->value('value');
            if (! empty($formEmail)) {
                $emails = $this->normalizeEmails(explode(',', (string) $formEmail));
                if (! empty($emails)) {
                    return $emails;
                }
            }
        }

        $generalEmail = \App\Models\SiteSetting::where('key', 'form_notification_emails')->value('value');
        if (! empty($generalEmail)) {
            $emails = $this->normalizeEmails(explode(',', (string) $generalEmail));
            if (! empty($emails)) {
                return $emails;
            }
        }

        return $this->normalizeEmails(config('inquiries.recipients', config('admin.inquiry_recipients', [])));
    }

    protected function normalizeEmails(mixed $emails): array
    {
        $emails = array_map(static fn ($email) => is_string($email) ? trim($email) : $email, (array) $emails);

        return array_values(array_filter($emails, function ($email) {
            return is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL);
        }));
    }

    protected function acquireLock(int $dispatchId): ?object
    {
        $key = 'mail-dispatch:'.$dispatchId;
        $seconds = (int) config('mail_outbox.lock_seconds', 120);

        foreach ($this->lockStores() as $store) {
            try {
                /** @var \Illuminate\Contracts\Cache\LockProvider $cacheStore */
                $cacheStore = Cache::store($store);
                $lock = $cacheStore->lock($key, $seconds);

                return $lock->get() ? $lock : null;
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    protected function lockStores(): array
    {
        $configured = trim((string) config('mail_outbox.cache_store', ''));
        $default = trim((string) config('cache.default', 'file'));

        return collect([$configured ?: $default, 'file'])
            ->filter()
            ->unique()
            ->values()
            ->all();
    }
}
