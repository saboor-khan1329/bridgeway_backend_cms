<?php

namespace Database\Seeders;

use App\Models\Blog;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Models\Slug;
use App\Support\SectionDocument;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

/** Initial authored content only: runtime APIs never read these fixtures. */
class WebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $taxonomy = $this->document(__DIR__.'/content/taxonomy.json');
            foreach ($taxonomy['categories'] as $entry) {
                $category = Category::firstOrCreate(
                    ['type' => $entry['attributes']['type'], 'name' => $entry['attributes']['name']],
                    $entry['attributes']
                );
                if ($category->wasRecentlyCreated && $entry['slug']) {
                    $this->slug($category, $entry['slug']);
                }
            }
            foreach ($taxonomy['settings'] as $setting) {
                SiteSetting::firstOrCreate(['key' => $setting['key']], $setting);
            }
            foreach (['pages' => ContentPage::class, 'services' => Service::class, 'blogs' => Blog::class] as $kind => $model) {
                $files = File::glob(__DIR__.'/content/'.$kind.'/*.json');
                sort($files);
                foreach ($files as $file) {
                    $entry = $this->document($file);
                    $owner = $kind === 'pages'
                        ? $model::firstOrCreate(['slug' => $entry['attributes']['slug']], $entry['attributes'])
                        : $model::whereHas('slug', fn ($query) => $query->where('slug', $entry['slug']))->first();
                    if ($kind !== 'pages' && ! $owner) {
                        $owner = $model::create($entry['attributes']);
                        $this->slug($owner, $entry['slug']);
                    }
                    // Reruns must not restore deleted blocks or overwrite drafts/editor changes.
                    if (! $owner->wasRecentlyCreated) {
                        continue;
                    }
                    if ($entry['seo']) {
                        $owner->seo()->create($entry['seo']);
                    }
                    foreach ($entry['images'] as $image) {
                        $owner->images()->create($image);
                    }
                    if (isset($entry['sections'])) {
                        SectionDocument::sync($owner, $entry['sections']);
                    }
                    foreach ($entry['categories'] ?? [] as $category) {
                        $related = Category::where('type', $category['type'])->where('name', $category['name'])->firstOrFail();
                        $owner->categories()->attach($related);
                    }
                }
            }
        });
    }

    private function slug(Model $owner, string $slug): void
    {
        if (Slug::where('slug', $slug)->exists()) {
            throw new \RuntimeException("The initial content slug '{$slug}' is already owned by another record.");
        }
        $owner->slug()->create(['slug' => $slug]);
    }

    private function document(string $path): array
    {
        return json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
    }
}
