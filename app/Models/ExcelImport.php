<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelImport extends Model
{
    protected $fillable = [
        'resource',
        'original_filename',
        'stored_path',
        'lookup_field',
        'mode',
        'dry_run',
        'status',
        'total_rows',
        'processed_rows',
        'failed_rows',
        'metadata',
        'row_errors',
        'error_report_path',
        'failure_message',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'metadata' => 'array',
        'row_errors' => 'array',
        'dry_run' => 'boolean',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
