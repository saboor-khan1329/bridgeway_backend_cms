<?php

namespace App\Services;

use App\Models\Image;
use App\Models\Slug;
use App\Support\FrontendCache;
use App\Support\FrontendPath;
use App\Support\ImageField;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GlobalCrudService
{
    public static function create(string $modelClass, array $payload): Model
    {
        return DB::transaction(function () use ($modelClass, $payload) {
            /** @var Model $model */
            $model = new $modelClass();
            $model->fill($payload['attributes'] ?? []);
            $model->save();

            self::syncRelations($model, $payload);

            if (isset($payload['after']) && is_callable($payload['after'])) {
                $payload['after']($model);
            }

            FrontendCache::bump();

            return $model->refresh();
        });
    }

    public static function update(Model $model, array $payload): Model
    {
        return DB::transaction(function () use ($model, $payload) {
            $locked = $model->newQuery()
                ->whereKey($model->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            self::assertFreshLock($locked, $payload);
            $oldFrontendPath = FrontendPath::forModel($locked);

            $locked->fill($payload['attributes'] ?? []);
            $locked->save();

            self::syncRelations($locked, $payload);
            app(SlugRedirectService::class)->preparePathChange(
                $locked,
                $oldFrontendPath,
                FrontendPath::forModel($locked->refresh()),
                'category_id'
            );

            if (isset($payload['after']) && is_callable($payload['after'])) {
                $payload['after']($locked);
            }

            FrontendCache::bump();

            return $locked->refresh();
        });
    }

    public static function delete(Model $model): bool
    {
        return DB::transaction(function () use ($model) {
            if (method_exists($model, 'forceDelete')) {
                $model->forceDelete();
            } else {
                $model->delete();
            }

            FrontendCache::bump();

            return true;
        });
    }

    public static function forceDelete(Model $model): bool
    {
        return DB::transaction(function () use ($model) {
            if (method_exists($model, 'forceDelete')) {
                $model->forceDelete();
            } else {
                $model->delete();
            }
            FrontendCache::bump();

            return true;
        });
    }

    public static function restore(Model $model): bool
    {
        if (method_exists($model, 'restore')) {
            $restored = (bool) $model->restore();
            FrontendCache::bump();

            return $restored;
        }
        return false;
    }

    protected static function syncRelations(Model $model, array $payload): void
    {
        self::handleJsonFields($model, $payload);
        self::handlePivot($model, $payload);
        self::handleImages($model, $payload);
        self::handleSlug($model, $payload);
        self::handleSeo($model, $payload);
    }

    protected static function assertFreshLock(Model $model, array $payload): void
    {
        $submitted = $payload['lock_updated_at'] ?? request()?->input('_lock_updated_at');

        if ($submitted === null || $submitted === '' || ! $model->usesTimestamps()) {
            return;
        }

        $current = $model->updated_at?->getTimestamp();

        if ($current && (int) $submitted !== $current) {
            throw ValidationException::withMessages([
                '_lock_updated_at' => 'This record changed after you opened the form. Refresh and try again.',
            ]);
        }
    }

    protected static function handleJsonFields(Model $model, array $payload): void
    {
        if (empty($payload['jsonFields'])) {
            return;
        }

        foreach ($payload['jsonFields'] as $field => $value) {
            $model->{$field} = is_string($value) ? json_decode($value, true) : $value;
        }
        $model->save();
    }

    protected static function handlePivot(Model $model, array $payload): void
    {
        if (empty($payload['pivot'])) {
            return;
        }

        foreach ($payload['pivot'] as $relation => $value) {
            if (! method_exists($model, $relation)) {
                continue;
            }

            $relationObj = $model->{$relation}();

            if (method_exists($relationObj, 'sync')) {
                $relationObj->sync($value ?? []);
            }
        }
    }

    protected static function handleImages(Model $model, array $payload): void
    {
        if (empty($payload['images']) || ! method_exists($model, 'images')) {
            return;
        }

        $folder = $payload['image_folder'] ?? null;
        $fileManager = app(AdminFileManagerService::class);

        foreach ($payload['images'] as $type => $data) {
            if (! is_array($data)) {
                continue;
            }

            $multiple = ! empty($data['multiple']) || isset($data['files']);

            if ($multiple) {
                try {
                    self::handleMultipleImages($model, $type, $data, $folder, $fileManager);
                } catch (ValidationException $exception) {
                    throw ImageField::remapValidationException($type, $exception, true);
                }

                continue;
            }

            try {
                self::handleSingleImage($model, $type, $data, $folder, $fileManager);
            } catch (ValidationException $exception) {
                throw ImageField::remapValidationException($type, $exception);
            }
        }
    }

    protected static function handleSingleImage(
        Model $model,
        string $type,
        array $data,
        ?string $folder,
        AdminFileManagerService $fileManager
    ): void {
        $existing = $model->images()->where('image_type', $type)->first();
        $selectedPath = trim((string) ($data['path'] ?? ''));

        if (! empty($data['file'])) {
            $directory = self::resolveImageDirectory($model, $type, $folder, $fileManager);
            $replacePath = $existing && $existing->source === 'upload' ? $existing->path : null;
            $fileMeta = $fileManager->storeUploadedFile($data['file'], $directory, true, $replacePath);
            $attributes = self::buildImageAttributes($type, $data, $fileMeta, 'upload');

            if ($existing) {
                self::updateExistingImage($existing, $attributes, $fileManager);
                return;
            }

            $model->images()->create($attributes);
            return;
        }

        if ($selectedPath !== '') {
            $fileMeta = $fileManager->assertManagedImagePath($selectedPath);
            $attributes = self::buildImageAttributes($type, $data, $fileMeta, 'selection');

            if ($existing) {
                self::updateExistingImage($existing, $attributes, $fileManager);
                return;
            }

            $model->images()->create($attributes);
            return;
        }

        if (! empty($data['remove']) && $existing) {
            self::deleteImage($existing);
            return;
        }

        if ($existing) {
            $existing->update(self::metadataOnlyAttributes($data, $existing));
        }
    }

    protected static function handleMultipleImages(
        Model $model,
        string $type,
        array $data,
        ?string $folder,
        AdminFileManagerService $fileManager
    ): void
    {
        if (! empty($data['remove'])) {
            $model->images()->where('image_type', $type)->get()
                ->each(fn (Image $image) => self::deleteImage($image));
        }

        foreach (Arr::wrap($data['delete_ids'] ?? []) as $id) {
            $existing = $model->images()->where('image_type', $type)->whereKey($id)->first();
            if ($existing) {
                self::deleteImage($existing);
            }
        }

        $directory = self::resolveImageDirectory($model, $type, $folder, $fileManager);

        foreach (Arr::wrap($data['files'] ?? []) as $file) {
            if ($file) {
                $fileMeta = $fileManager->storeUploadedFile($file, $directory, true);
                $model->images()->create(self::buildImageAttributes($type, $data, $fileMeta, 'upload'));
            }
        }
    }

    protected static function buildImageAttributes(string $type, array $data, array $fileMeta, string $source): array
    {
        $width = isset($data['width']) && $data['width'] !== '' && $data['width'] !== null ? (int) $data['width'] : ($fileMeta['width'] ?? null);
        $height = isset($data['height']) && $data['height'] !== '' && $data['height'] !== null ? (int) $data['height'] : ($fileMeta['height'] ?? null);

        return [
            'path' => $fileMeta['path'],
            'disk' => $fileMeta['disk'] ?? 'public',
            'alt' => $data['alt'] ?? null,
            'title' => $data['title'] ?? null,
            'caption' => $data['caption'] ?? null,
            'mime_type' => $fileMeta['mime_type'] ?? null,
            'size_bytes' => $fileMeta['size_bytes'] ?? null,
            'width' => $width,
            'height' => $height,
            'source' => $source,
            'image_type' => $type,
        ];
    }

    protected static function metadataOnlyAttributes(array $data, Image $existing): array
    {
        return [
            'alt' => $data['alt'] ?? $existing->alt,
            'title' => $data['title'] ?? $existing->title,
            'caption' => $data['caption'] ?? $existing->caption,
            'width' => isset($data['width']) && $data['width'] !== '' && $data['width'] !== null ? (int) $data['width'] : $existing->width,
            'height' => isset($data['height']) && $data['height'] !== '' && $data['height'] !== null ? (int) $data['height'] : $existing->height,
        ];
    }

    protected static function updateExistingImage(
        Image $existing,
        array $attributes,
        AdminFileManagerService $fileManager
    ): void {
        $oldDisk = $existing->disk;
        $oldPath = $existing->path;
        $oldSource = $existing->source;

        $existing->update($attributes);

        if ($oldDisk !== ($attributes['disk'] ?? $oldDisk) || $oldPath !== ($attributes['path'] ?? $oldPath)) {
            $fileManager->deleteManagedFileIfUnreferenced($oldDisk, $oldPath, $oldSource, $existing->getKey());
        }
    }

    protected static function resolveImageDirectory(
        Model $model,
        string $type,
        ?string $folder,
        AdminFileManagerService $fileManager
    ): string {
        if (filled($folder)) {
            return trim((string) $folder, '/');
        }

        return $fileManager->suggestedImageDirectory($model, $type);
    }

    protected static function deleteImage(Image $image): void
    {
        app(AdminFileManagerService::class)->purgeImage($image);
    }

    protected static function handleSlug(Model $model, array $payload): void
    {
        if (! array_key_exists('slug', $payload) || ! method_exists($model, 'slug')) {
            return;
        }

        $existingSlug = trim((string) $model->slug?->slug);
        $submitted = trim((string) ($payload['slug'] ?? ''));
        $slugWasSubmitted = (bool) ($payload['slug_was_submitted'] ?? true);

        if ($existingSlug !== '') {
            if (! $slugWasSubmitted) {
                return;
            }

            if ($submitted === '') {
                throw ValidationException::withMessages([
                    'slug' => 'Slug is required when editing an existing record.',
                ]);
            }

            $base = Str::slug($submitted);

            if ($base === $existingSlug) {
                return;
            }

            $candidate = self::uniqueSlug($model, $base, false);
        } else {
            $source = trim((string) ($payload['slug_source'] ?? ''));
            $base = Str::slug($submitted !== '' ? $submitted : $source);

            if ($base === '') {
                throw ValidationException::withMessages([
                    'slug' => 'Enter a slug or provide a title/name that can generate one.',
                ]);
            }

            $candidate = self::uniqueSlug($model, $base, $submitted === '');
        }

        app(SlugRedirectService::class)->prepareSlugChange($model, $existingSlug, $candidate);

        if ($model->slug) {
            $model->slug()->update(['slug' => $candidate]);
        } else {
            $model->slug()->create(['slug' => $candidate]);
        }
    }

    protected static function uniqueSlug(Model $model, string $base, bool $allowSuffix): string
    {
        $modelType = $model->getMorphClass();
        $candidate = $base;
        $i = 1;

        while (self::slugExistsForAnotherOwner($model, $modelType, $candidate)) {
            if (! $allowSuffix) {
                throw ValidationException::withMessages([
                    'slug' => 'This slug is already used by another record.',
                ]);
            }

            $suffix = '-'.$i++;
            $candidate = Str::limit($base, 191 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }

    protected static function slugExistsForAnotherOwner(Model $model, string $modelType, string $candidate): bool
    {
        return Slug::query()
                ->where('slug', $candidate)
                ->where(function ($q) use ($model, $modelType) {
                    $q->where('sluggable_type', '!=', $modelType)
                        ->orWhere('sluggable_id', '!=', $model->getKey());
                })
                ->exists();
    }

    protected static function handleSeo(Model $model, array $payload): void
    {
        if (empty($payload['seo']) || ! method_exists($model, 'seo')) {
            return;
        }

        if ($model->seo) {
            $model->seo()->update($payload['seo']);
        } else {
            $model->seo()->create($payload['seo']);
        }
    }
}
