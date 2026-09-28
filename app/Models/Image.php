<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Image extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\FrontendCache::bump());
        static::deleted(fn () => \App\Support\FrontendCache::bump());
    }

    protected $fillable = [
        'path',
        'disk',
        'alt',
        'title',
        'caption',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'source',
        'image_type',
    ];

    protected $casts = [
        'size_bytes' => 'integer',
        'width' => 'integer',
        'height' => 'integer',
    ];

    public function imageable(): MorphTo
    {
        return $this->morphTo();
    }
}
