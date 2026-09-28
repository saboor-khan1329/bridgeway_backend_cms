<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\ContentPage;
use App\Models\Service;
use Illuminate\Support\Collection;

class WebsiteSitemap
{
    /** @return array<string, array<int, array<string, mixed>>> */
    public function groups(): array
    {
        $groups = [
            'pages' => $this->contentPages()
                ->merge($this->rootServiceAliases())
                ->merge($this->staticPages())
                ->unique('path')
                ->sortBy(fn (array $entry) => $entry['path'] === '/' ? '' : $entry['path'])
                ->values()
                ->all(),
            'services' => $this->servicePages()->values()->all(),
            'blogs' => $this->blogPages()->values()->all(),
        ];

        return array_filter($groups, fn (array $urls) => $urls !== []);
    }

    /** @return array<int, array<string, mixed>> */
    public function flat(): array
    {
        return collect($this->groups())
            ->flatten(1)
            ->unique('path')
            ->sortBy(fn (array $entry) => $entry['path'] === '/' ? '' : $entry['path'])
            ->values()
            ->all();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function contentPages(): Collection
    {
        return ContentPage::query()
            ->whereNotIn('slug', StaticPageOwnership::SLUGS)
            ->where('is_cms_managed', true)
            ->where('template', '!=', 'global')
            ->where('status', true)
            ->whereHas('contentBlocks', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('type', FrontendSectionRegistry::TYPES))
            ->with('seo')
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->filter(fn (ContentPage $page) => $this->isIndexable($page))
            ->map(fn (ContentPage $page) => $this->entry(
                $page->slug === 'home' ? '/' : '/'.$page->slug,
                $page->updated_at,
                $page->slug === 'home' ? '1.0' : ($page->slug === 'blogs' ? '0.8' : '0.7'),
                $page->slug === 'home' ? 'daily' : 'weekly',
                ['slug' => $page->slug, 'type' => 'content_page']
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function servicePages(): Collection
    {
        return Service::query()
            ->where('status', true)
            ->whereHas('slug')
            ->whereHas('contentBlocks', fn ($query) => $query
                ->where('is_active', true)
                ->whereIn('type', FrontendSectionRegistry::TYPES))
            ->with(['slug', 'seo'])
            ->orderBy('title')
            ->get()
            ->filter(fn (Service $service) => $service->slug?->slug && $this->isIndexable($service))
            ->map(fn (Service $service) => $this->entry(
                '/service/'.$service->slug->slug,
                $service->updated_at,
                '0.8',
                'weekly',
                ['slug' => $service->slug->slug, 'type' => 'service_page']
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function rootServiceAliases(): Collection
    {
        $contentPageSlugs = ContentPage::query()
            ->where('is_cms_managed', true)
            ->pluck('slug')
            ->all();

        return $this->servicePages()
            ->reject(fn (array $entry) => in_array($entry['slug'] ?? null, array_merge($contentPageSlugs, StaticPageOwnership::SLUGS), true))
            ->map(function (array $entry): array {
                $slug = (string) $entry['slug'];
                $entry['path'] = '/'.$slug;
                $entry['type'] = 'root_service_alias';
                $entry['priority'] = '0.7';

                return $entry;
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function blogPages(): Collection
    {
        return Blog::query()
            ->where('status', true)
            ->whereHas('slug')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with(['slug', 'seo'])
            ->orderByRaw('COALESCE(published_at, created_at) desc')
            ->get()
            ->filter(fn (Blog $blog) => $blog->slug?->slug && $this->isIndexable($blog))
            ->map(fn (Blog $blog) => $this->entry(
                '/blogs/'.$blog->slug->slug,
                $blog->updated_at,
                '0.6',
                'monthly',
                ['slug' => $blog->slug->slug, 'type' => 'blog']
            ));
    }

    /** @return Collection<int, array<string, mixed>> */
    private function staticPages(): Collection
    {
        return collect(StaticPageOwnership::SLUGS)->map(fn ($slug) => $this->entry(
            $slug === 'home' ? '/' : '/'.$slug, null, $slug === 'home' ? '1.0' : '0.7',
            'monthly', ['slug' => $slug, 'type' => 'static_page']
        ));
    }

    private function isIndexable(object $model): bool
    {
        return ($model->seo?->robots_index ?? 'index') !== 'noindex';
    }

    private function entry(string $path, mixed $lastModified, string $priority, string $changefreq, array $extra = []): array
    {
        return array_merge([
            'path' => $path,
            'lastmod' => $lastModified?->toAtomString(),
            'priority' => $priority,
            'changefreq' => $changefreq,
        ], $extra);
    }
}
