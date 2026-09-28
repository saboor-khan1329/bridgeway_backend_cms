<?php

namespace App\Traits;

use App\Models\Slug;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasSlug
{
    public function slug(): MorphOne
    {
        return $this->morphOne(Slug::class, 'sluggable');
    }

    public function getSlugValueAttribute(): ?string
    {
        return $this->slug?->slug;
    }

    protected static function bootHasSlug(): void
    {
        static::deleting(function ($model) {
            $isForceDeleting = method_exists($model, 'isForceDeleting')
                ? $model->isForceDeleting()
                : true;

            if ($isForceDeleting) {
                $model->slug()?->delete();
            }
        });
    }
}
