<?php

namespace App\Services;

use App\Models\Redirect;
use App\Support\FrontendPath;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SlugRedirectService
{
    public function prepareSlugChange(Model $model, ?string $oldSlug, string $newSlug): void
    {
        $newPath = FrontendPath::forModelSlug($model, $newSlug);
        $oldPath = FrontendPath::forModelSlug($model, (string) $oldSlug);

        $this->preparePathChange($model, $oldPath, $newPath);
    }

    public function preparePathChange(Model $model, ?string $oldPath, ?string $newPath, string $errorField = 'slug'): void
    {
        if (! $newPath) {
            return;
        }

        $sourceType = $model->getMorphClass();
        $sourceId = (int) $model->getKey();
        $activeAtNewPath = Redirect::query()
            ->where('from_url', $newPath)
            ->active()
            ->get();

        $blockingNewPath = $activeAtNewPath->first(fn (Redirect $redirect) => ! $this->belongsToSource($redirect, $sourceType, $sourceId));

        if ($blockingNewPath) {
            throw ValidationException::withMessages([
                $errorField => "The frontend path {$newPath} is already used by an active redirect.",
            ]);
        }

        if (! $oldPath || $oldPath === $newPath) {
            return;
        }

        $activeAtOldPath = Redirect::query()
            ->where('from_url', $oldPath)
            ->active()
            ->get();

        $blockingOldPath = $activeAtOldPath->first(fn (Redirect $redirect) => ! $this->belongsToSource($redirect, $sourceType, $sourceId));

        if ($blockingOldPath) {
            throw ValidationException::withMessages([
                $errorField => "The previous frontend path {$oldPath} is already managed by another active redirect.",
            ]);
        }

        $autoRedirects = $this->sourceRedirects($sourceType, $sourceId);

        $autoRedirects
            ->filter(fn (Redirect $redirect) => $redirect->from_url === $newPath)
            ->each(function (Redirect $redirect): void {
                $redirect->update([
                    'status' => false,
                ]);
            });

        $autoRedirects
            ->filter(fn (Redirect $redirect) => $redirect->from_url !== $newPath)
            ->each(function (Redirect $redirect) use ($newPath): void {
                $redirect->update([
                    'to_url' => $newPath,
                    'status_code' => 301,
                    'status' => true,
                    'is_auto_generated' => true,
                ]);
            });

        Redirect::query()->updateOrCreate(
            [
                'from_url' => $oldPath,
                'sourceable_type' => $sourceType,
                'sourceable_id' => $sourceId,
            ],
            [
                'to_url' => $newPath,
                'status_code' => 301,
                'status' => true,
                'is_auto_generated' => true,
            ]
        );
    }

    protected function sourceRedirects(string $sourceType, int $sourceId): Collection
    {
        return Redirect::query()
            ->where('sourceable_type', $sourceType)
            ->where('sourceable_id', $sourceId)
            ->where('is_auto_generated', true)
            ->get();
    }

    protected function belongsToSource(Redirect $redirect, string $sourceType, int $sourceId): bool
    {
        return $redirect->is_auto_generated
            && $redirect->sourceable_type === $sourceType
            && (int) $redirect->sourceable_id === $sourceId;
    }
}
