<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class OnboardingSubmissionFile extends Model
{
    protected $fillable = [
        'submission_id',
        'field_key',
        'field_label',
        'item_index',
        'file_path',
        'original_name',
        'file_size',
        'mime_type',
    ];

    protected $casts = [
        'file_size'  => 'integer',
        'item_index' => 'integer',
    ];

    public function submission(): BelongsTo
    {
        return $this->belongsTo(OnboardingSubmission::class, 'submission_id');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function isPdf(): bool
    {
        return $this->mime_type === 'application/pdf';
    }

    public function formatSize(): string
    {
        $bytes = (int) $this->file_size;
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1_048_576) {
            return round($bytes / 1024, 1) . ' KB';
        }
        return round($bytes / 1_048_576, 1) . ' MB';
    }

    public function exists(): bool
    {
        return Storage::disk('local')->exists($this->file_path);
    }
}
