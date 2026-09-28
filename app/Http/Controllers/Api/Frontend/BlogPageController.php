<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Blog;
use App\Models\Category;
use App\Models\ContentPage;
use App\Models\SiteSetting;
use App\Models\User;
use App\Support\FrontendSchemaBuilder;
use App\Support\FrontendSeoPresenter;
use App\Support\HtmlCleaner;
use App\Support\SeoDefaults;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class BlogPageController extends BaseFrontendController
{
    private const PER_PAGE = 6;

    /** Website-specific featured selection and pagination belong to the API. */
    public function websiteIndex(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query('page', 1));
        return $this->success($this->cached("website_blog_index:{$page}", function () use ($page): array {
            $perPage = max(1, min(50, (int) SiteSetting::get('blog_listing_per_page', 10)));
            $featuredLimit = max(0, min(20, (int) SiteSetting::get('blog_featured_limit', 5)));
            $featured = $featuredLimit ? $this->websiteQuery()->where('is_featured', true)->limit($featuredLimit)->get() : collect();
            $paginator = $this->websiteQuery()->whereNotIn('id', $featured->pluck('id'))->paginate($perPage, ['*'], 'page', $page);
            return [
                'blogs' => collect($paginator->items())->map(fn ($blog) => $this->formatBlogCard($blog))->values()->all(),
                'featured' => $featured->map(fn ($blog) => $this->formatBlogCard($blog))->values()->all(),
                'meta' => ['current_page' => $paginator->currentPage(), 'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(), 'total' => $paginator->total()],
            ];
        }));
    }

    public function recent(Request $request): JsonResponse
    {
        $input = $request->validate(['limit' => ['nullable', 'integer', 'min:1', 'max:20'],
            'exclude_slug' => ['nullable', 'string', 'max:191', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/']]);
        $limit = (int) ($input['limit'] ?? 5);
        $exclude = $input['exclude_slug'] ?? '';
        return $this->success($this->cached("recent_blog_cards:{$limit}:{$exclude}", function () use ($limit, $exclude): array {
            $query = $this->websiteQuery();
            if ($exclude !== '') {
                $query->whereDoesntHave('slug', fn ($slug) => $slug->where('slug', $exclude));
            }
            return ['items' => $query->limit($limit)->get()->map(fn ($blog) => $this->formatBlogCard($blog))->all()];
        }));
    }

    private function websiteQuery()
    {
        return Blog::query()->where('status', true)->whereHas('slug')
            ->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->with(['slug', 'images', 'authorUser.images',
                'categories' => fn ($query) => $query->where('status', true)->with('slug')])
            ->orderByRaw('COALESCE(published_at, created_at) desc')->orderBy('id');
    }

    /**
     * GET /api/frontend/blogs
     *
     * Blog listing with optional pagination (?page=N&per_page=N), free-text
     * search (?search=term), and category-tag filter (?tag=Industry+News).
     * Search/tag results are never cached; base pages are.
     *
     * `per_page` exists because the two consumers page differently: this API
     * pages six at a time, while the Next.js listing paginates client-side and
     * needs the whole set in one request. It is clamped to the configured
     * frontend maximum, so it cannot be used to ask for an unbounded result.
     */
    public function index(Request $request): JsonResponse
    {
        $page    = max(1, (int) $request->query('page', 1));
        $search  = trim((string) $request->query('search', ''));
        $tag     = trim((string) $request->query('tag', ''));
        $perPage = $this->resolvePerPage($request->query('per_page'));

        if ($search !== '' || $tag !== '') {
            return $this->success($this->buildListing($page, $search, $tag, $perPage));
        }

        $data = $this->cached(
            "blog_listing:page:{$page}:per:{$perPage}",
            fn () => $this->buildListing($page, '', '', $perPage)
        );

        return $this->success($data);
    }

    /**
     * Clamp a requested page size to [1, frontend.pagination.max_per_page],
     * falling back to this controller's own default when absent or unusable.
     */
    private function resolvePerPage(mixed $requested): int
    {
        if ($requested === null || $requested === '' || ! is_numeric($requested)) {
            return self::PER_PAGE;
        }

        $max = (int) config('frontend.pagination.max_per_page', 100);

        return max(1, min((int) $requested, max(1, $max)));
    }

    /**
     * GET /api/frontend/blogs/{slug}
     *
     * Full data for a single blog detail page.
     */
    public function show(string $slug): JsonResponse
    {
        // A slug that cannot be one rules itself out before touching the DB.
        if (! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            return $this->notFound('Blog post not found.');
        }

        $data = $this->cached("blog_page:{$slug}", function () use ($slug) {
            $blog = $this->findBySlug(Blog::class, $slug);

            if (! $blog) {
                return ['_missing' => true]; // 404 — no such post, and never was
            }

            if (! $blog->status || ($blog->published_at && $blog->published_at->isFuture())) {
                return ['_gone' => true]; // 410 — existed but no longer published
            }

            $blog->load([
                'slug',
                'seo',
                'images',
                'authorUser.images',
                'categories'     => fn ($q) => $q->where('status', true)->with('slug'),
                'faqs'           => fn ($q) => $q->where('status', true),
                'linkedChildren' => fn ($q) => $q->where('status', true)->with([
                    'slug',
                    'images',
                    'authorUser.images',
                    'categories' => fn ($categoryQuery) => $categoryQuery->where('status', true)->with('slug'),
                ])->where(fn ($linkedQuery) => $linkedQuery
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now())
                ),
            ]);

            return $this->formatBlog($blog);
        });

        if (is_array($data) && ! empty($data['_missing'])) {
            return $this->notFound('Blog post not found.');
        }

        if (! $data || (is_array($data) && ! empty($data['_gone']))) {
            return $this->gone('This blog post is no longer available.');
        }

        return $this->success($data);
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    private function buildListing(int $page, string $search, string $tag, int $perPage = self::PER_PAGE): array
    {
        $query = Blog::query()
            ->where('status', true)
            ->where(fn ($q) => $q
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now())
            )
            ->with([
                'slug',
                'images',
                'authorUser.images',
                'categories' => fn ($q) => $q->where('status', true)->with('slug'),
            ])
            ->orderByRaw('COALESCE(published_at, created_at) desc');

        if ($search !== '') {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('short_description', 'like', "%{$search}%")
                ->orWhere('excerpt', 'like', "%{$search}%")
            );
        }

        if ($tag !== '' && $tag !== 'All') {
            $query->whereHas('categories', fn ($q) => $q
                ->where('status', true)
                ->where('name', $tag)
            );
        }

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        $blogs = collect($paginator->items())
            ->map(fn ($b) => $this->formatBlogCard($b))
            ->values()
            ->all();

        // Featured = the editor's pick, else the most recent published blog;
        // only on base page 1. With nothing flagged — which is the default for
        // every existing row — this picks exactly what it always picked.
        $featuredBlog = null;
        if ($page === 1 && $search === '' && $tag === '') {
            $featured = Blog::query()
                ->where('status', true)
                ->where(fn ($q) => $q
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now())
                )
                ->with([
                    'slug',
                    'images',
                    'authorUser.images',
                    'categories' => fn ($q) => $q->where('status', true)->with('slug'),
                ])
                ->orderByDesc('is_featured')
                ->orderByRaw('COALESCE(published_at, created_at) desc')
                ->first();

            if ($featured) {
                $featuredBlog = $this->formatBlogCard($featured);
            }
        }

        // Tags = all active blog category names, prepended with "All"
        $tags = Category::query()
            ->where('status', true)
            ->where('type', 'blog')
            ->orderBy('name')
            ->pluck('name')
            ->prepend('All')
            ->values()
            ->all();

        return [
            'blogs'         => $blogs,
            'featured_blog' => $featuredBlog,
            'tags'          => $tags,
            'seo'           => $this->blogListingSeo(),
            'meta'          => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
            ],
        ];
    }

    private function formatBlogCard(Blog $blog): array
    {
        $slug     = $blog->slug?->slug;
        $category = $blog->relationLoaded('categories') ? $blog->categories->first() : null;

        return [
            'id'                => $blog->id,
            'title'             => $blog->title,
            'slug'              => $slug,
            'href'              => $slug ? "/blogs/{$slug}" : null,
            'short_description' => HtmlCleaner::plainText($blog->short_description),
            'excerpt'           => HtmlCleaner::plainText($blog->excerpt),
            'image'             => $this->blogImage($blog),
            'category'          => $category?->name,
            'author'            => $blog->authorName(),
            'author_profile'    => $this->formatAuthorProfile($blog->authorUser, $blog->author),
            'published_at'      => $this->displayDate($blog),
            // The machine-readable date beside the pre-formatted one. Consumers
            // that present dates in their own format read this and format it
            // themselves rather than re-parsing 'd M, Y'.
            'published_at_iso'  => $this->publishDate($blog)?->toIso8601String(),
            'created_at'        => $blog->created_at?->format('d M, Y'),
            'updated_at'        => $blog->updated_at?->format('d M, Y'),
            // Drives the listing's "Most Popular" rail, which is chosen by an
            // editor rather than by recency — unlike `featured_blog` below.
            'is_featured'       => (bool) $blog->is_featured,
        ];
    }

    private function formatBlog(Blog $blog): array
    {
        $slug     = $blog->slug?->slug;
        $category = $blog->categories->first();
        $content  = HtmlCleaner::clean($blog->content) ?? '';
        $relatedBlogs = $this->relatedBlogsFor($blog, 5);
        $recentBlogs = $this->recentBlogsFor($blog, 6);

        return [
            'id'                => $blog->id,
            'title'             => $blog->title,
            'slug'              => $slug,
            'content'           => $this->injectHeadingIds($content),
            'short_description' => HtmlCleaner::plainText($blog->short_description),
            'excerpt'           => HtmlCleaner::plainText($blog->excerpt),
            'author'            => $blog->authorName(),
            'author_profile'    => $this->formatAuthorProfile($blog->authorUser, $blog->author),
            'published_at'      => $this->displayDate($blog),
            'published_at_iso'  => $this->publishDate($blog)?->toIso8601String(),
            'created_at'        => $blog->created_at?->format('d M, Y'),
            'updated_at'        => $blog->updated_at?->format('d M, Y'),
            'image'             => $this->blogImage($blog),
            'category'          => $category?->name,
            'categories'        => $blog->categories->pluck('name')->filter()->values()->all(),
            'is_featured'       => (bool) $blog->is_featured,
            'table_of_contents' => $this->extractHeadings($content),
            'faqs'              => $this->formatFaqs($blog->faqs),
            'related_blogs'     => $relatedBlogs->map(fn ($b) => $this->formatBlogCard($b))->values()->all(),
            'recent_blogs'      => $recentBlogs->map(fn ($b) => $this->formatBlogCard($b))->values()->all(),
            'seo' => $this->presentBlogSeo($blog),
        ];
    }

    private function relatedBlogsFor(Blog $blog, int $limit): Collection
    {
        if ($blog->linkedChildren->isNotEmpty()) {
            return $blog->linkedChildren->take($limit)->values();
        }

        $categoryIds = $blog->categories->pluck('id')->filter()->values();
        $related = collect();

        if ($categoryIds->isNotEmpty()) {
            $related = $this->publishedBlogCardQuery($blog->id)
                ->whereHas('categories', fn ($query) => $query->whereIn('categories.id', $categoryIds))
                ->limit($limit)
                ->get();
        }

        if ($related->count() < $limit) {
            $extra = $this->publishedBlogCardQuery($blog->id)
                ->whereNotIn('id', $related->pluck('id')->all())
                ->limit($limit - $related->count())
                ->get();

            $related = $related->concat($extra);
        }

        return $related->values();
    }

    private function recentBlogsFor(Blog $blog, int $limit): Collection
    {
        return $this->publishedBlogCardQuery($blog->id)
            ->limit($limit)
            ->get()
            ->values();
    }

    private function publishedBlogCardQuery(int $excludeBlogId)
    {
        return Blog::query()
            ->whereKeyNot($excludeBlogId)
            ->where('status', true)
            ->where(fn ($query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now())
            )
            ->with([
                'slug',
                'images',
                'authorUser.images',
                'categories' => fn ($query) => $query->where('status', true)->with('slug'),
            ])
            ->orderByRaw('COALESCE(published_at, created_at) desc');
    }

    private function formatAuthorProfile(?User $user, ?string $legacyAuthor): array
    {
        $defaultImage = [
            'url' => (string) SiteSetting::get('blog_default_author_image', ''),
            'alt' => (string) SiteSetting::get('blog_default_author_image_alt', ''),
        ];

        if (! $user) {
            $name = trim((string) $legacyAuthor);

            return [
                'name' => $name !== '' ? $name : (string) SiteSetting::get('blog_default_author_name', ''),
                'display_name' => $name !== '' ? $name : (string) SiteSetting::get('blog_default_author_name', ''),
                'job_title' => null,
                'bio' => null,
                'image' => $defaultImage,
            ];
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'display_name' => $user->publicName(),
            'job_title' => $user->job_title,
            'bio' => HtmlCleaner::plainText($user->bio),
            'image' => $this->formatImage($user, 'avatar') ?: $defaultImage,
        ];
    }

    private function blogImage(Blog $blog): array
    {
        return $this->formatThumbnailImage($blog) ?: [
            'url' => (string) SiteSetting::get('blog_default_image', ''),
            'alt' => (string) SiteSetting::get('blog_default_image_alt', ''),
        ];
    }

    private function blogListingSeo(): array
    {
        $page = ContentPage::query()
            ->where('is_cms_managed', true)
            ->where('slug', 'blogs')
            ->with('seo')
            ->first();
        $title = $page?->title ?: 'Blogs & Posts';
        $description = $page?->meta_description ?: '';
        $url = SeoDefaults::urlFor('/blogs');

        return FrontendSeoPresenter::present('blogs', $page?->seoApi(), [
            'title' => $title,
            'description' => $description,
            'url' => $url,
            'canonical' => '/blogs',
            'image' => '',
        ], [
            'schemaGraph' => FrontendSchemaBuilder::page('blogs', $title, $description, $url),
        ]);
    }

    private function presentBlogSeo(Blog $blog): array
    {
        $slug = $blog->slug?->slug;
        $path = '/blogs/'.$slug;
        $url = SeoDefaults::urlFor($path);
        $image = $this->blogImage($blog);
        $publishedAt = $blog->published_at ?: $blog->created_at;
        $description = HtmlCleaner::plainText($blog->short_description ?: $blog->excerpt);

        return FrontendSeoPresenter::present('blog', $blog->seoApi(), [
            'title' => $blog->title,
            'description' => $description,
            'url' => $url,
            'canonical' => $path,
            'image' => (string) ($image['url'] ?? ''),
            'author' => (string) $blog->authorName(),
            'published_at' => (string) $publishedAt?->toIso8601String(),
            'modified_at' => (string) $blog->updated_at?->toIso8601String(),
        ], [
            'ogType' => 'article',
            'schemaGraph' => FrontendSchemaBuilder::blog(
                $blog->meta_title ?: $blog->title,
                $blog->meta_description ?: $description,
                $url,
                $image['url'] ?? null,
                $blog->authorName(),
                $publishedAt?->toIso8601String(),
                $blog->updated_at?->toIso8601String(),
            ),
        ]);
    }

    private function displayDate(Blog $blog): ?string
    {
        return $this->publishDate($blog)?->format('d M, Y');
    }

    /** The date a post counts as published on, falling back to its creation. */
    private function publishDate(Blog $blog)
    {
        return $blog->published_at ?: $blog->created_at;
    }

    /** Extract plain-text h2 labels from HTML content for the sidebar TOC. */
    private function extractHeadings(string $html): array
    {
        if ($html === '') {
            return [];
        }
        preg_match_all('/<h2[^>]*>(.*?)<\/h2>/is', $html, $matches);

        return array_map('strip_tags', $matches[1] ?? []);
    }

    /**
     * Inject sequential id="section-N" onto every h2 so that
     * the sidebar table-of-contents anchor links resolve correctly.
     * h3 headings are left without IDs (they are not in the TOC).
     */
    private function injectHeadingIds(string $html): string
    {
        if ($html === '') {
            return '';
        }
        $index = 0;

        return preg_replace_callback(
            '/<(h2)([^>]*)>/i',
            function (array $m) use (&$index): string {
                $attrs = preg_replace('/\s*id="[^"]*"/i', '', $m[2]);
                $id    = $index++;

                return "<{$m[1]}{$attrs} id=\"section-{$id}\">";
            },
            $html
        );
    }
}
