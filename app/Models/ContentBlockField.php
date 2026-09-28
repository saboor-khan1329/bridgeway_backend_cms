<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContentBlockField extends Model
{
    public const TYPES = ['string', 'integer', 'float', 'boolean', 'null', 'array', 'object'];

    protected $fillable = ['content_block_id', 'path', 'value_type', 'value', 'sort_order'];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function block(): BelongsTo
    {
        return $this->belongsTo(ContentBlock::class, 'content_block_id');
    }
}
