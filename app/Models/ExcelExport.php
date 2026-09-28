<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExcelExport extends Model
{
    protected $fillable = [
        'resource',
        'filename',
        'stored_path',
        'status',
        'columns',
        'filters',
        'metadata',
        'disk',
        'exported_rows',
        'failure_message',
        'started_at',
        'finished_at',
        'created_by',
    ];

    protected $casts = [
        'columns' => 'array',
        'filters' => 'array',
        'metadata' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
