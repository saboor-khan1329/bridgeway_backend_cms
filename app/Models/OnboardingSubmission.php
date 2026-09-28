<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingSubmission extends Model
{
    public const STATUS_PENDING   = 'pending';
    public const STATUS_REVIEWING = 'reviewing';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING   => 'Pending',
        self::STATUS_REVIEWING => 'Under Review',
        self::STATUS_APPROVED  => 'Approved',
        self::STATUS_REJECTED  => 'Rejected',
    ];

    protected $fillable = [
        'reference_number',
        'form_config_id',
        'form_config_snapshot',
        'submission_data',
        'applicant_name',
        'applicant_email',
        'status',
        'admin_notes',
        'ip_address',
        'user_agent',
        'email_sent_to_admin',
        'email_sent_to_applicant',
        'submitted_at',
    ];

    protected $casts = [
        'form_config_snapshot'    => 'array',
        'submission_data'         => 'array',
        'email_sent_to_admin'     => 'boolean',
        'email_sent_to_applicant' => 'boolean',
        'submitted_at'            => 'datetime',
    ];

    public function formConfig(): BelongsTo
    {
        return $this->belongsTo(OnboardingFormConfig::class, 'form_config_id');
    }

    public function files(): HasMany
    {
        return $this->hasMany(OnboardingSubmissionFile::class, 'submission_id');
    }

    public function getStatusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }

    /** Generate a unique human-readable reference number like IG-A3B9C1-2026. */
    public static function generateReference(): string
    {
        $attempts = 0;
        do {
            $ref = 'IG-' . strtoupper(bin2hex(random_bytes(3))) . '-' . date('Y');
            $attempts++;
            if ($attempts > 50) {
                throw new \RuntimeException('Unable to generate unique reference number.');
            }
        } while (self::where('reference_number', $ref)->exists());

        return $ref;
    }

    /** Returns steps from the config snapshot, safe for rendering. */
    public function getSnapshotSteps(): array
    {
        return collect($this->form_config_snapshot['steps'] ?? [])
            ->filter(fn ($s) => is_array($s) && ($s['is_enabled'] ?? true))
            ->sortBy('order')
            ->values()
            ->all();
    }

    /** Flat list of all field values for a given step ID. */
    public function getStepData(string $stepId): array
    {
        $data = $this->submission_data ?? [];
        return $data[$stepId] ?? [];
    }
}
