<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class MailDispatch extends Model
{
    public const TYPE_INQUIRY_RECEIVED = 'inquiry_received';

    /** Quote request captured by the Service Finder popup. */
    public const TYPE_FINDER_LEAD_RECEIVED = 'finder_lead_received';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SENT = 'sent';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'type',
        'status',
        'mailer',
        'queue',
        'recipients',
        'payload',
        'related_type',
        'related_id',
        'attempts',
        'max_attempts',
        'available_at',
        'locked_at',
        'last_attempt_at',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'recipients' => 'array',
        'payload' => 'array',
        'attempts' => 'integer',
        'max_attempts' => 'integer',
        'available_at' => 'datetime',
        'locked_at' => 'datetime',
        'last_attempt_at' => 'datetime',
        'sent_at' => 'datetime',
    ];

    public function scopeDue(Builder $query): Builder
    {
        $now = now();
        $staleLockedAt = now()->subSeconds((int) config('mail_outbox.lock_seconds', 120));

        return $query
            ->whereColumn('attempts', '<', 'max_attempts')
            ->where(function (Builder $query) use ($now, $staleLockedAt) {
                $query
                    ->where(function (Builder $query) use ($now) {
                        $query->where('status', self::STATUS_PENDING)
                            ->where(function (Builder $query) use ($now) {
                                $query->whereNull('available_at')->orWhere('available_at', '<=', $now);
                            });
                    })
                    ->orWhere(function (Builder $query) use ($now) {
                        $query->where('status', self::STATUS_FAILED)
                            ->whereNotNull('available_at')
                            ->where('available_at', '<=', $now);
                    })
                    ->orWhere(function (Builder $query) use ($staleLockedAt) {
                        $query->where('status', self::STATUS_PROCESSING)
                            ->whereNotNull('locked_at')
                            ->where('locked_at', '<=', $staleLockedAt);
                    });
            });
    }

    public function scopeForInquiry(Builder $query, Inquiry $inquiry): Builder
    {
        return $query
            ->where('related_type', Inquiry::class)
            ->where('related_id', $inquiry->id);
    }

    public function isSendable(): bool
    {
        return $this->status !== self::STATUS_SENT
            && $this->attempts < $this->max_attempts;
    }
}
