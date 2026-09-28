<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ImageFormatter
{
    public static function single(Model $model, string $type = 'default'): ?array
    {
        $img = $model->relationLoaded('images')
            ? $model->images->firstWhere('image_type', $type)
            : $model->images()->where('image_type', $type)->first();

        return $img ? [
            'path' => $img->path,
            'disk' => $img->disk,
            'url' => self::url($img->path, $img->disk),
            'alt' => $img->alt,
            'title' => $img->title,
            'caption' => $img->caption,
            'mime_type' => $img->mime_type,
            'size_bytes' => $img->size_bytes,
            'width' => $img->width,
            'height' => $img->height,
        ] : null;
    }

    public static function many(Model $model, string $type = 'default'): array
    {
        $images = $model->relationLoaded('images')
            ? $model->images->where('image_type', $type)
            : $model->images()->where('image_type', $type)->get();

        return $images->map(fn ($img) => [
            'id' => $img->id,
            'path' => $img->path,
            'disk' => $img->disk,
            'url' => self::url($img->path, $img->disk),
            'alt' => $img->alt,
            'title' => $img->title,
            'caption' => $img->caption,
            'mime_type' => $img->mime_type,
            'size_bytes' => $img->size_bytes,
            'width' => $img->width,
            'height' => $img->height,
        ])->values()->all();
    }

    /**
     * The public URL for a stored image path.
     *
     * A path that is already a URL, or already root-relative, is returned
     * untouched: it names an asset that is served by something other than this
     * app's storage disk — an external CDN, or a file shipped in the frontend's
     * own `public/` directory. Uploads never look like this; they come back
     * from `storeAs()` as a disk-relative path with no leading slash, so this
     * cannot change how an uploaded image resolves.
     */
    public static function url(?string $path, ?string $disk = 'public'): ?string
    {
        if (! $path) {
            return null;
        }

        if (preg_match('#^(https?://|//|/)#i', $path)) {
            return $path;
        }

        return Storage::disk($disk ?: 'public')->url($path);
    }
}
