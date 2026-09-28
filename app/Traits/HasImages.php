<?php

namespace App\Traits;

use App\Models\Image;
use App\Services\AdminFileManagerService;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasImages
{
    public function images(): MorphMany
    {
        return $this->morphMany(Image::class, 'imageable')->orderBy('id');
    }

    public function getImage(string $type = 'default'): ?Image
    {
        return $this->images()->where('image_type', $type)->first();
    }

    public function getImagesByType(string $type): \Illuminate\Database\Eloquent\Collection
    {
        return $this->images()->where('image_type', $type)->get();
    }

    protected static function bootHasImages(): void
    {
        static::deleting(function ($model) {
            $isForceDeleting = method_exists($model, 'isForceDeleting')
                ? $model->isForceDeleting()
                : true;

            if (! $isForceDeleting) {
                return;
            }

            foreach ($model->images()->get() as $image) {
                app(AdminFileManagerService::class)->purgeImage($image);
            }
        });
    }
}
