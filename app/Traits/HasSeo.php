<?php

namespace App\Traits;

use App\Models\SeoMeta;
use App\Support\SeoFormatter;

trait HasSeo
{
    /* ===============================
     | SEO RELATION
     =============================== */
    public function seo()
    {
        return $this->morphOne(SeoMeta::class, 'seoable');
    }

    public function seoApi(): ?array
    {
        return $this->seo ? SeoFormatter::format($this->seo) : null;
    }

    /* ===============================
     | ACCESSORS
     =============================== */
    public function getMetaTitleAttribute()
    {
        return $this->seo?->meta_title;
    }

    public function getMetaDescriptionAttribute()
    {
        return $this->seo?->meta_description;
    }

    public function getSeoContentAttribute()
    {
        return $this->seo?->seo_content;
    }

    public function getSchemaAttribute()
    {
        return $this->seo?->schema;
    }

    public function getMicrodataAttribute()
    {
        return $this->seo?->microdata;
    }

    public function getEnableSchemaAttribute()
    {
        return $this->seo?->enable_schema;
    }

    public function getEnableMicrodataAttribute()
    {
        return $this->seo?->enable_microdata;
    }

    public function getRobotsIndexAttribute()
    {
        return $this->seo?->robots_index ?? 'index';
    }

    public function getRobotsFollowAttribute()
    {
        return $this->seo?->robots_follow ?? 'follow';
    }

    public function getOgTagsAttribute()
    {
        return $this->seo?->og_tags;
    }

    public function getCanonicalUrlAttribute() { return $this->seo?->canonical_url; }
    public function getKeywordsAttribute() { return $this->seo?->keywords; }
    public function getOgTitleAttribute() { return $this->seo?->og_title; }
    public function getOgDescriptionAttribute() { return $this->seo?->og_description; }
    public function getOgTypeAttribute() { return $this->seo?->og_type; }
    public function getOgUrlAttribute() { return $this->seo?->og_url; }
    public function getOgImageAttribute() { return $this->seo?->og_image; }
    public function getTwitterCardAttribute() { return $this->seo?->twitter_card; }
    public function getTwitterTitleAttribute() { return $this->seo?->twitter_title; }
    public function getTwitterDescriptionAttribute() { return $this->seo?->twitter_description; }
    public function getTwitterImageAttribute() { return $this->seo?->twitter_image; }

    /* ===============================
     | AUTO CLEANUP (GLOBAL DELETE)
     =============================== */
    protected static function bootHasSeo()
    {
        static::deleting(function ($model) {
            $model->seo()?->delete();
        });
    }
}
