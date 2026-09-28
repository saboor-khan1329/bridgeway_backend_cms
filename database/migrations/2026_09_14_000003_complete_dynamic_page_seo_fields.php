<?php

use App\Models\Blog;
use App\Models\ContentPage;
use App\Models\Service;
use App\Support\FrontendCache;
use App\Support\HtmlCleaner;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (ContentPage::where('is_cms_managed', true)->with(['seo', 'contentBlocks.fields'])->get() as $page) {
                $hero = $page->contentBlocks->first()?->cmsData() ?? [];
                $this->fill($page, '/'.$page->slug, $hero['description'] ?? $hero['detail'] ?? '', 'website');
            }
            foreach (Service::whereHas('contentBlocks', fn ($query) => $query->whereNotNull('section_key'))
                ->with(['slug', 'seo', 'contentBlocks.fields'])->get() as $page) {
                $hero = $page->contentBlocks->first()?->cmsData() ?? [];
                $this->fill($page, '/service/'.$page->slug?->slug,
                    $page->short_description ?: ($hero['description'] ?? ''), 'website');
            }
            foreach (Blog::with(['slug', 'seo'])->get() as $page) {
                $this->fill($page, '/blogs/'.$page->slug?->slug, $page->short_description ?: $page->excerpt, 'article');
            }
        });
        FrontendCache::bump();
    }

    private function fill($page, string $path, ?string $description, string $ogType): void
    {
        $seo = $page->seo;
        $title = $seo?->meta_title;
        $metaDescription = $seo?->meta_description;
        if (! $title || stripos($title, 'Intraguard') !== false) {
            $title = HtmlCleaner::plainText($page->title);
        }
        if (! $metaDescription || stripos($metaDescription, 'Intraguard') !== false) {
            $metaDescription = HtmlCleaner::plainText($description);
        }

        $defaults = [
            'meta_title' => $title,
            'meta_description' => $metaDescription,
            'canonical_url' => $path,
            'og_title' => $title,
            'og_description' => $metaDescription,
            'og_url' => $path,
            'og_type' => $ogType,
            'twitter_title' => $title,
            'twitter_description' => $metaDescription,
            'twitter_card' => 'summary_large_image',
            'robots_index' => 'index',
            'robots_follow' => 'follow',
            'enable_schema' => true,
        ];
        $fields = [];
        foreach ($defaults as $key => $value) {
            $existing = $seo?->getAttribute($key);
            if ($existing === null || $existing === ''
                || (is_string($existing) && stripos($existing, 'Intraguard') !== false)) {
                $fields[$key] = $value;
            }
        }
        if ($fields !== []) {
            $page->seo()->updateOrCreate([], $fields);
        }
    }

    public function down(): void
    {
        // Authored SEO content remains in the database during a code rollback.
    }
};
