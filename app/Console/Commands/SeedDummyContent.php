<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Service;
use App\Services\GlobalCrudService;
use Illuminate\Console\Command;
use Illuminate\Console\ConfirmableTrait;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeedDummyContent extends Command
{
    use ConfirmableTrait;

    protected $signature = 'demo:seed-content
        {--services=12 : Number of services to create}
        {--locations=10 : Number of locations to create}
        {--blogs=10 : Number of blogs to create}
        {--pages=8 : Number of pages to create}
        {--faqs=20 : Number of FAQs to create}
        {--service-categories=6 : Number of service categories to create}
        {--sector-categories=4 : Number of sector categories to create}
        {--location-categories=4 : Number of location categories to create}
        {--blog-categories=4 : Number of blog categories to create}
        {--redirect-histories=6 : Number of records to rename for slug redirect history}
        {--force : Run the command even in production}';

    protected $description = 'Insert dummy CMS content using factories for local testing';

    public function handle(): int
    {
        if (! $this->confirmToProceed()) {
            return self::FAILURE;
        }

        $counts = [
            'services' => $this->integerOption('services'),
            'locations' => $this->integerOption('locations'),
            'blogs' => $this->integerOption('blogs'),
            'pages' => $this->integerOption('pages'),
            'faqs' => $this->integerOption('faqs'),
            'service_categories' => $this->integerOption('service-categories'),
            'sector_categories' => $this->integerOption('sector-categories'),
            'location_categories' => $this->integerOption('location-categories'),
            'blog_categories' => $this->integerOption('blog-categories'),
            'redirect_histories' => $this->integerOption('redirect-histories'),
        ];

        $summary = DB::transaction(function () use ($counts) {
            $this->components->info('Creating taxonomies...');

            $serviceRoot = Category::factory()->service()->create([
                'name' => 'Demo Services',
                'category_type' => 'service',
            ]);

            $sectorRoot = Category::factory()->sector()->create([
                'name' => 'Demo Sectors',
            ]);

            $locationRoot = Category::factory()->location()->create([
                'name' => 'Demo Locations',
            ]);

            $blogRoot = Category::factory()->blog()->create([
                'name' => 'Demo Blog Topics',
            ]);

            $serviceCategories = $this->createCategoryBranch($serviceRoot, $counts['service_categories'], 'service');
            $sectorCategories = $this->createCategoryBranch($sectorRoot, $counts['sector_categories'], 'sector');
            $locationCategories = $this->createCategoryBranch($locationRoot, $counts['location_categories']);
            $blogCategories = $this->createCategoryBranch($blogRoot, $counts['blog_categories']);

            $serviceLeafCategories = $this->leafCategories($serviceCategories);
            $sectorLeafCategories = $this->leafCategories($sectorCategories);
            $blogLeafCategories = $this->leafCategories($blogCategories);

            $this->components->info('Creating shared content pools...');

            $faqs = Faq::factory()->count($counts['faqs'])->create();

            $this->components->info('Creating services and locations...');

            $services = Service::factory()->count($counts['services'])->create();
            $locations = Location::factory()->count($counts['locations'])->create();
            $blogs = Blog::factory()->count($counts['blogs'])->create();

            $this->assignHierarchy($services);
            $this->assignHierarchy($locations);
            $serviceGraphLinks = $this->assignServiceLinkGroups($services);
            $locationGraphLinks = $this->assignGraphLinks($locations, 'linkedParents');
            $blogGraphLinks = $this->assignGraphLinks($blogs, 'linkedParents');

            $serviceLocationLinks = 0;
            $serviceBlogLinks = 0;

            foreach ($services as $service) {
                $service->categories()->sync($this->modelIds($this->randomSubset($serviceLeafCategories, 1, 2)));
                $service->faqs()->sync($this->modelIds($this->randomSubset($faqs, 1, 4)));
                $serviceLocationLinks += $this->syncSimpleRelation($service->relatedLocations(), $locations, 1, 3);
                $serviceBlogLinks += $this->syncSimpleRelation($service->relatedBlogs(), $blogs, 1, 3);
            }

            $locationBlogLinks = 0;

            foreach ($locations as $location) {
                $location->faqs()->sync($this->modelIds($this->randomSubset($faqs, 1, 3)));
                $locationBlogLinks += $this->syncSimpleRelation($location->relatedBlogs(), $blogs, 1, 3);
            }

            foreach ($blogs as $blog) {
                $blog->categories()->sync($this->modelIds($this->randomSubset($blogLeafCategories, 1, 2)));
                $blog->faqs()->sync($this->modelIds($this->randomSubset($faqs, 1, 3)));
            }

            $this->components->info('Creating pages...');

            $pages = collect();

            for ($i = 1; $i <= $counts['pages']; $i++) {
                $pageType = match ($i % 3) {
                    1 => 'service',
                    2 => 'sector',
                    default => null,
                };

                $page = Page::factory()
                    ->state([
                        'page_title' => match ($pageType) {
                            'service' => 'Demo Service Page '.$i,
                            'sector' => 'Demo Sector Page '.$i,
                            default => 'Demo Page '.$i,
                        },
                        'page_type' => $pageType,
                    ])
                    ->create();

                $page->faqs()->sync($this->modelIds($this->randomSubset($faqs, 1, 4)));
                $page->services()->sync($this->modelIds($this->randomSubset($services, 1, 4)));
                $page->locations()->sync($this->modelIds($this->randomSubset($locations, 0, 3)));
                $page->blogs()->sync($this->modelIds($this->randomSubset($blogs, 0, 3)));

                $pageCategories = match ($pageType) {
                    'service' => $serviceLeafCategories,
                    'sector' => $sectorLeafCategories,
                    default => $serviceLeafCategories
                        ->concat($sectorLeafCategories)
                        ->concat($locationCategories)
                        ->concat($blogCategories)
                        ->values(),
                };

                $page->categories()->sync($this->modelIds($this->randomSubset($pageCategories, 1, 3)));

                $pages->push($page);
            }

            $redirectHistoryCount = $this->createSlugHistories(
                collect()
                    ->concat($services)
                    ->concat($locations)
                    ->concat($blogs)
                    ->concat($pages),
                $counts['redirect_histories']
            );

            return [
                'service_categories' => $serviceCategories->count(),
                'sector_categories' => $sectorCategories->count(),
                'location_categories' => $locationCategories->count(),
                'blog_categories' => $blogCategories->count(),
                'faqs' => $faqs->count(),
                'services' => $services->count(),
                'service_children' => $services->whereNotNull('parent_id')->count(),
                'service_graph_links' => $serviceGraphLinks,
                'service_location_links' => $serviceLocationLinks,
                'service_blog_links' => $serviceBlogLinks,
                'locations' => $locations->count(),
                'location_children' => $locations->whereNotNull('parent_id')->count(),
                'location_graph_links' => $locationGraphLinks,
                'location_blog_links' => $locationBlogLinks,
                'blogs' => $blogs->count(),
                'blog_graph_links' => $blogGraphLinks,
                'pages' => $pages->count(),
                'service_pages' => $pages->where('page_type', 'service')->count(),
                'sector_pages' => $pages->where('page_type', 'sector')->count(),
                'standard_pages' => $pages->whereNull('page_type')->count(),
                'auto_redirects' => $redirectHistoryCount,
            ];
        });

        $this->components->info('Dummy content inserted successfully.');
        $this->newLine();
        $this->table(['Metric', 'Count'], collect($summary)->map(fn ($count, $label) => [
            'Metric' => str_replace('_', ' ', ucfirst((string) $label)),
            'Count' => $count,
        ])->values()->all());

        return self::SUCCESS;
    }

    protected function createCategoryBranch(Category $root, int $count, ?string $serviceCategoryType = null): Collection
    {
        $nodes = collect();

        for ($i = 1; $i <= $count; $i++) {
            $parent = $nodes->isNotEmpty() && fake()->boolean(35)
                ? $nodes->random()
                : $root;

            $factory = match ($root->type) {
                'service' => $serviceCategoryType === 'sector'
                    ? Category::factory()->sector()
                    : Category::factory()->service(),
                'location' => Category::factory()->location(),
                'blog' => Category::factory()->blog(),
                default => Category::factory(),
            };

            $nodes->push($factory->create([
                'parent_id' => $parent->id,
            ]));
        }

        return $nodes;
    }

    protected function leafCategories(Collection $categories): Collection
    {
        if ($categories->isEmpty()) {
            return collect();
        }

        return Category::query()
            ->whereKey($categories->pluck('id'))
            ->leaf()
            ->get();
    }

    protected function assignHierarchy(EloquentCollection $items, int $maxChildrenPerParent = 3): void
    {
        if ($items->count() < 2) {
            return;
        }

        $pool = collect([$items->first()]);
        $childCounts = [];

        foreach ($items->slice(1) as $item) {
            if ($pool->isNotEmpty() && fake()->boolean(70)) {
                $parent = $pool->random();

                $item->update([
                    'parent_id' => $parent->id,
                ]);

                $childCounts[$parent->id] = ($childCounts[$parent->id] ?? 0) + 1;

                if ($childCounts[$parent->id] >= $maxChildrenPerParent) {
                    $pool = $pool->reject(fn ($candidate) => $candidate->id === $parent->id)->values();
                }
            }

            if (($childCounts[$item->id] ?? 0) < $maxChildrenPerParent) {
                $pool->push($item);
            }
        }
    }

    protected function assignGraphLinks(EloquentCollection $items, string $relation, int $maxParents = 2): int
    {
        if ($items->count() < 2) {
            return 0;
        }

        $ordered = $items->sortBy('id')->values();
        $links = 0;

        foreach ($ordered as $index => $item) {
            if ($index === 0) {
                continue;
            }

            $candidates = $ordered->take($index);
            $limit = min($maxParents, $candidates->count());

            if ($limit <= 0) {
                continue;
            }

            $take = random_int(1, $limit);

            $parentIds = $candidates->shuffle()->take($take)->pluck('id')->all();
            $item->{$relation}()->sync($parentIds);
            $links += count($parentIds);
        }

        return $links;
    }

    protected function assignServiceLinkGroups(EloquentCollection $services): int
    {
        if ($services->count() < 2) {
            return 0;
        }

        $relations = [
            'linkedServicesV1' => 'v1',
            'linkedServicesV2' => 'v2',
            'linkedServicesV3' => 'v3',
        ];
        $links = 0;

        foreach ($services as $service) {
            $candidates = $services
                ->where('id', '!=', $service->id)
                ->shuffle()
                ->values();

            foreach ($relations as $relation => $group) {
                $selected = $candidates->take(random_int(0, min(2, $candidates->count())))->pluck('id')->all();
                $service->{$relation}()->sync(
                    collect($selected)->mapWithKeys(fn ($id) => [$id => ['link_group' => $group]])->all()
                );
                $links += count($selected);
            }
        }

        return $links;
    }

    protected function randomSubset(Collection|EloquentCollection $items, int $min, int $max): Collection
    {
        $items = collect($items)->values();

        if ($items->isEmpty() || $max <= 0) {
            return collect();
        }

        $upper = min($max, $items->count());
        $lower = min($min, $upper);
        $take = random_int($lower, $upper);

        return $items->shuffle()->take($take)->values();
    }

    protected function modelIds(Collection $models): array
    {
        return $models
            ->values()
            ->pluck('id')
            ->all();
    }

    protected function syncSimpleRelation($relation, Collection|EloquentCollection $items, int $min, int $max): int
    {
        $selectedIds = $this->randomSubset($items, $items->isNotEmpty() ? $min : 0, $max)
            ->pluck('id')
            ->all();

        $relation->sync($selectedIds);

        return count($selectedIds);
    }

    protected function createSlugHistories(Collection $models, int $count): int
    {
        if ($count <= 0 || $models->isEmpty()) {
            return 0;
        }

        $renamed = 0;

        foreach ($models->shuffle()->take($count)->values() as $index => $model) {
            $currentSlug = trim((string) data_get($model, 'slug.slug'));

            if ($currentSlug === '') {
                continue;
            }

            $firstPass = Str::slug($currentSlug.'-redirect-'.($index + 1));
            $updated = GlobalCrudService::update($model, [
                'attributes' => [],
                'slug' => $firstPass,
            ]);

            if ($index < 2) {
                $updated = GlobalCrudService::update($updated, [
                    'attributes' => [],
                    'slug' => Str::slug($firstPass.'-final'),
                ]);
            }

            $renamed++;
        }

        return DB::table('redirects')
            ->where('is_auto_generated', true)
            ->count();
    }

    protected function integerOption(string $name): int
    {
        return max(0, (int) $this->option($name));
    }
}
