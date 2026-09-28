<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Slug;
use App\Models\Redirect;
use App\Support\FrontendCache;
use App\Services\CategoryTreeService;
use App\Services\SlugRedirectService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FlattenServiceCategoryTree extends Command
{
    protected $signature = 'category:flatten-service-tree
        {--execute : Apply the changes. Without this option the command only previews.}
        {--keep-empty-parents-active : Keep emptied structural parent categories active.}
        {--sector-slug= : Optionally set the first sector category slug, for example "sectors".}';

    protected $description = 'Flatten service/sector categories to direct top-level categories without deleting content.';

    public function handle(): int
    {
        $execute = (bool) $this->option('execute');
        $keepEmptyParentsActive = (bool) $this->option('keep-empty-parents-active');
        $sectorSlug = trim((string) $this->option('sector-slug'));
        $sectorSlug = $sectorSlug !== '' ? Str::slug($sectorSlug) : '';

        if ($sectorSlug === 'sector') {
            $sectorSlug = 'sectors';
        }

        $categoriesToFlatten = Category::query()
            ->where('type', 'service')
            ->whereNotNull('parent_id')
            ->with(['slug', 'parent.slug'])
            ->orderBy('depth')
            ->orderBy('id')
            ->get();

        $parentIds = $categoriesToFlatten
            ->pluck('parent_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        $emptyParentsToDeactivate = Category::query()
            ->whereIn('id', $parentIds)
            ->where('type', 'service')
            ->withCount('services')
            ->get()
            ->filter(fn (Category $category) => (int) $category->services_count === 0)
            ->values();

        if (! $execute) {
            $this->warn('Preview only. No database changes were made.');
        }

        if ($categoriesToFlatten->isEmpty()) {
            $this->info('No child service/sector categories need flattening.');
        } else {
            $this->info('Categories that will become direct top-level categories:');

            foreach ($categoriesToFlatten as $category) {
                $this->line(sprintf(
                    '- #%d %s (%s) parent: %s -> none',
                    $category->id,
                    $category->name,
                    $category->slug?->slug ?: 'no-slug',
                    $category->parent?->slug?->slug ?: (string) $category->parent_id
                ));
            }
        }

        if (! $keepEmptyParentsActive && $emptyParentsToDeactivate->isNotEmpty()) {
            $this->info('Empty structural parent categories that will be set inactive:');

            foreach ($emptyParentsToDeactivate as $category) {
                $this->line(sprintf(
                    '- #%d %s (%s)',
                    $category->id,
                    $category->name,
                    $category->slug?->slug ?: 'no-slug'
                ));
            }
        }

        if ($sectorSlug !== '') {
            $sectorCategory = Category::query()
                ->where('type', 'service')
                ->where('category_type', 'sector')
                ->with('slug')
                ->orderBy('id')
                ->first();

            if (! $sectorCategory) {
                $this->error('No sector category exists, so --sector-slug cannot be applied.');

                return self::FAILURE;
            }

            $slugIsUsed = Slug::query()
                ->where('slug', $sectorSlug)
                ->where(function ($query) use ($sectorCategory) {
                    $query->where('sluggable_type', '!=', $sectorCategory->getMorphClass())
                        ->orWhere('sluggable_id', '!=', $sectorCategory->getKey());
                })
                ->exists();

            if ($slugIsUsed) {
                $this->error("Slug '{$sectorSlug}' is already used by another record.");

                return self::FAILURE;
            }

            $this->info(sprintf(
                'Sector category slug will be set: %s -> %s',
                $sectorCategory->slug?->slug ?: 'no-slug',
                $sectorSlug
            ));
        }

        if (! $execute) {
            $this->line('');
            $this->info('Run with --execute to apply these changes.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($categoriesToFlatten, $emptyParentsToDeactivate, $keepEmptyParentsActive, $sectorSlug) {
            foreach ($categoriesToFlatten as $category) {
                $category->forceFill([
                    'parent_id' => null,
                    'depth' => 0,
                ])->saveQuietly();
            }

            if (! $keepEmptyParentsActive) {
                foreach ($emptyParentsToDeactivate as $category) {
                    $category->forceFill(['status' => false])->saveQuietly();
                }
            }

            if ($sectorSlug !== '') {
                $sectorCategory = Category::query()
                    ->where('type', 'service')
                    ->where('category_type', 'sector')
                    ->with(['slug', 'services.slug'])
                    ->orderBy('id')
                    ->firstOrFail();

                $oldSlug = $sectorCategory->slug?->slug;

                if ($oldSlug && $oldSlug !== $sectorSlug) {
                    app(SlugRedirectService::class)->prepareSlugChange($sectorCategory, $oldSlug, $sectorSlug);
                }

                if ($sectorCategory->slug) {
                    $sectorCategory->slug()->update(['slug' => $sectorSlug]);
                } else {
                    $sectorCategory->slug()->create(['slug' => $sectorSlug]);
                }

                collect([$oldSlug, $sectorSlug === 'sector' ? 'sectors' : null])
                    ->filter(fn ($legacySlug) => $legacySlug && $legacySlug !== $sectorSlug)
                    ->unique()
                    ->each(function (string $legacySlug) use ($sectorCategory, $sectorSlug): void {
                        $this->upsertRedirect($sectorCategory, "/{$legacySlug}", "/{$sectorSlug}");

                        $sectorCategory->services->each(function ($service) use ($sectorCategory, $legacySlug, $sectorSlug): void {
                            $serviceSlug = $service->slug?->slug;

                            if ($serviceSlug) {
                                $this->upsertRedirect(
                                    $sectorCategory,
                                    "/{$legacySlug}/{$serviceSlug}",
                                    "/{$sectorSlug}/{$serviceSlug}"
                                );
                            }
                        });
                    });
            }

            Category::query()
                ->orderBy('id')
                ->get()
                ->each(fn (Category $category) => CategoryTreeService::attach($category));

            FrontendCache::bump();
        });

        $this->info('Service/sector category tree flattened successfully. No content records were deleted.');

        return self::SUCCESS;
    }

    private function upsertRedirect(Category $category, string $fromUrl, string $toUrl): void
    {
        Redirect::query()->updateOrCreate(
            [
                'from_url' => $fromUrl,
                'sourceable_type' => $category->getMorphClass(),
                'sourceable_id' => $category->getKey(),
            ],
            [
                'to_url' => $toUrl,
                'status_code' => 301,
                'status' => true,
                'is_auto_generated' => true,
            ]
        );
    }
}
