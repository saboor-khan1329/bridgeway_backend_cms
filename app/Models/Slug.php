<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Slug extends Model
{
    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\FrontendCache::bump());
        static::deleted(fn () => \App\Support\FrontendCache::bump());
    }

    protected $fillable = ['slug'];

    public function sluggable(): MorphTo
    {
        return $this->morphTo();
    }
}
