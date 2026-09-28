<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class SeoMeta extends Model
{
    protected $fillable = [
        'meta_title',
        'meta_description',
        'seo_content',
        'schema',
        'microdata',
        'enable_schema',
        'enable_microdata',
        'robots_index',
        'robots_follow',
        'og_tags',
        'canonical_url',
        'keywords',
        'og_title',
        'og_description',
        'og_type',
        'og_url',
        'og_image',
        'twitter_card',
        'twitter_title',
        'twitter_description',
        'twitter_image',
    ];

    protected $casts = [
        'enable_schema' => 'boolean',
        'enable_microdata' => 'boolean',
    ];

    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\FrontendCache::bump());
        static::deleted(fn () => \App\Support\FrontendCache::bump());
    }
}
