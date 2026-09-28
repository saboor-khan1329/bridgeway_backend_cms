<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';
    public const STATUS_READ = 'read';
    public const STATUS_REPLIED = 'replied';
    public const STATUS_ARCHIVED = 'archived';
    public const STATUS_SPAM = 'spam';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_READ,
        self::STATUS_REPLIED,
        self::STATUS_ARCHIVED,
        self::STATUS_SPAM,
    ];

    protected $fillable = [
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'type_of_service_required',
        // Which frontend form produced this row, and the fields specific to
        // that form which have no column of their own.
        'form_name',
        'details',
        'source_url',
        'ip',
        'user_agent',
        'spam_score',
        'spam_reasons',
        'captcha_passed',
        'status',
        'read_at',
    ];

    protected $casts = [
        'read_at' => 'datetime',
        'spam_score' => 'integer',
        'spam_reasons' => 'array',
        'details' => 'array',
        'captcha_passed' => 'boolean',
    ];

    public function scopeNew(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_NEW);
    }

    protected static function booted(): void
    {
        static::deleted(function (self $inquiry): void {
            $path = data_get($inquiry->details, 'attachment.path');
            if (is_string($path) && str_starts_with($path, 'inquiry-attachments/')) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($path);
            }
        });
    }

    public function markAsRead(): void
    {
        if ($this->status === self::STATUS_NEW) {
            $this->forceFill([
                'status' => self::STATUS_READ,
                'read_at' => now(),
            ])->save();
        }
    }

    public function hasAttachment(): bool
    {
        $path = data_get($this->details, 'attachment.path');
        return is_string($path) && $path !== '';
    }
}

