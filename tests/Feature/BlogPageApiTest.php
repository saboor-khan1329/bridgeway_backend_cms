<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /api/frontend/blogs — the listing and the slug-addressed detail page.
 *
 * Run:
 *   php artisan test --filter BlogPageApiTest
 */
class BlogPageApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['frontend.cache.enabled' => false]);
    }

    private function createBlog(string $slug, array $attributes = []): Blog
    {
        $blog = Blog::factory()->create(array_merge([
            'title' => 'Amazon Brand Registry vs No Registry',
            'short_description' => 'Compare Amazon Brand Registry vs no registry.',
            'excerpt' => 'Compare Amazon Brand Registry vs no registry.',
            'author' => 'BWD Admin',
            'published_at' => now()->subDay(),
            'status' => true,
        ], $attributes));

        $blog->slug()->delete();
        $blog->slug()->create(['slug' => $slug]);

        return $blog->fresh();
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/blogs/{slug}
    // ─────────────────────────────────────────────────────────────

    public function test_blog_detail_returns_200_with_expected_structure(): void
    {
        $this->createBlog('first-one');

        $this->getJson('/api/frontend/blogs/first-one')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'title',
                    'slug',
                    'content',
                    'short_description',
                    'excerpt',
                    'author',
                    'author_profile',
                    'published_at',
                    'published_at_iso',
                    'image',
                    'category',
                    'is_featured',
                    'table_of_contents',
                    'faqs',
                    'related_blogs',
                    'recent_blogs',
                    'seo',
                ],
            ])
            ->assertJson([
                'success' => true,
                'data' => [
                    'slug' => 'first-one',
                    'title' => 'Amazon Brand Registry vs No Registry',
                ],
            ]);
    }

    public function test_blog_detail_returns_a_machine_readable_date(): void
    {
        $this->createBlog('dated-post', ['published_at' => '2026-03-30 00:00:00']);

        $data = $this->getJson('/api/frontend/blogs/dated-post')->json('data');

        // The frontend formats dates itself and reads the ISO value to do it.
        $this->assertSame('30 Mar, 2026', $data['published_at']);
        $this->assertStringStartsWith('2026-03-30T', $data['published_at_iso']);
    }

    public function test_blog_detail_reports_whether_the_post_is_featured(): void
    {
        $this->createBlog('featured-post', ['is_featured' => true]);
        $this->createBlog('ordinary-post', ['is_featured' => false]);

        $this->getJson('/api/frontend/blogs/featured-post')
            ->assertJsonPath('data.is_featured', true);
        $this->getJson('/api/frontend/blogs/ordinary-post')
            ->assertJsonPath('data.is_featured', false);
    }

    public function test_blog_detail_excludes_itself_from_recent_posts(): void
    {
        $this->createBlog('current-post');
        $this->createBlog('other-post', ['title' => 'Another post']);

        $recent = $this->getJson('/api/frontend/blogs/current-post')->json('data.recent_blogs');

        $this->assertNotContains('current-post', array_column($recent, 'slug'));
    }

    // ─── Missing, invalid and unpublished ────────────────────────────────────

    public function test_unknown_slug_returns_404(): void
    {
        $this->getJson('/api/frontend/blogs/no-such-post')
            ->assertStatus(404)
            ->assertJson(['success' => false]);
    }

    public function test_malformed_slug_returns_404(): void
    {
        foreach (['Bad_Slug', 'UPPERCASE', 'trailing-', 'sp ace'] as $slug) {
            $this->getJson('/api/frontend/blogs/'.urlencode($slug))
                ->assertStatus(404);
        }
    }

    public function test_unpublished_blog_returns_410(): void
    {
        $this->createBlog('unpublished-post', ['status' => false]);

        $this->getJson('/api/frontend/blogs/unpublished-post')
            ->assertStatus(410)
            ->assertJson(['success' => false]);
    }

    public function test_future_dated_blog_returns_410(): void
    {
        $this->createBlog('scheduled-post', ['published_at' => now()->addWeek()]);

        $this->getJson('/api/frontend/blogs/scheduled-post')
            ->assertStatus(410);
    }

    // ─────────────────────────────────────────────────────────────
    // GET /api/frontend/blogs
    // ─────────────────────────────────────────────────────────────

    public function test_listing_returns_200_with_expected_structure(): void
    {
        $this->createBlog('listed-post');

        $this->getJson('/api/frontend/blogs')
            ->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'blogs' => [['id', 'title', 'slug', 'href', 'image', 'is_featured', 'published_at_iso']],
                    'featured_blog',
                    'tags',
                    'seo',
                    'meta' => ['current_page', 'last_page', 'per_page', 'total'],
                ],
            ]);
    }

    public function test_listing_defaults_to_six_per_page(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->createBlog("post-{$i}", ['title' => "Post {$i}"]);
        }

        $this->getJson('/api/frontend/blogs')
            ->assertJsonPath('data.meta.per_page', 6)
            ->assertJsonCount(6, 'data.blogs');
    }

    public function test_listing_honours_a_requested_page_size(): void
    {
        for ($i = 1; $i <= 8; $i++) {
            $this->createBlog("sized-{$i}", ['title' => "Sized {$i}"]);
        }

        // The Next.js listing paginates client-side and asks for the full set.
        $this->getJson('/api/frontend/blogs?per_page=100')
            ->assertJsonPath('data.meta.per_page', 100)
            ->assertJsonCount(8, 'data.blogs');
    }

    public function test_listing_clamps_an_oversized_page_size(): void
    {
        config(['frontend.pagination.max_per_page' => 25]);
        $this->createBlog('clamped-post');

        $this->getJson('/api/frontend/blogs?per_page=5000')
            ->assertJsonPath('data.meta.per_page', 25);
    }

    public function test_listing_ignores_a_nonsense_page_size(): void
    {
        $this->createBlog('fallback-post');

        $this->getJson('/api/frontend/blogs?per_page=abc')
            ->assertJsonPath('data.meta.per_page', 6);
    }

    public function test_listing_excludes_unpublished_and_future_posts(): void
    {
        $this->createBlog('live-post');
        $this->createBlog('draft-post', ['status' => false]);
        $this->createBlog('scheduled-post', ['published_at' => now()->addWeek()]);

        $slugs = array_column(
            $this->getJson('/api/frontend/blogs?per_page=100')->json('data.blogs'),
            'slug'
        );

        $this->assertContains('live-post', $slugs);
        $this->assertNotContains('draft-post', $slugs);
        $this->assertNotContains('scheduled-post', $slugs);
    }

    public function test_listing_prefers_a_featured_post_for_the_hero(): void
    {
        $this->createBlog('older-featured', [
            'title' => 'Older but featured',
            'is_featured' => true,
            'published_at' => now()->subMonth(),
        ]);
        $this->createBlog('newest-post', [
            'title' => 'Newest',
            'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/frontend/blogs')
            ->assertJsonPath('data.featured_blog.slug', 'older-featured');
    }

    public function test_listing_falls_back_to_the_newest_post_when_none_is_featured(): void
    {
        $this->createBlog('older-post', ['published_at' => now()->subMonth()]);
        $this->createBlog('newest-post', [
            'title' => 'Newest',
            'published_at' => now()->subHour(),
        ]);

        $this->getJson('/api/frontend/blogs')
            ->assertJsonPath('data.featured_blog.slug', 'newest-post');
    }

    public function test_listing_returns_category_tags_with_all_first(): void
    {
        $blog = $this->createBlog('categorised-post');
        $category = Category::factory()->create(['type' => 'blog', 'name' => 'Amazon', 'status' => true]);
        $blog->categories()->attach($category);

        $tags = $this->getJson('/api/frontend/blogs')->json('data.tags');

        $this->assertSame('All', $tags[0]);
        $this->assertContains('Amazon', $tags);
    }

    public function test_listing_is_empty_but_valid_when_there_are_no_posts(): void
    {
        $this->getJson('/api/frontend/blogs')
            ->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => ['blogs' => [], 'featured_blog' => null],
            ]);
    }
}
