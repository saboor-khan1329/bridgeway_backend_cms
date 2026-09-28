<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\SiteSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteBlogApiTest extends TestCase
{
    use RefreshDatabase;

    private function createBlog(string $slug, bool $featured = false, ?string $publishedAt = null): Blog
    {
        $blog = Blog::create(['title' => $slug, 'status' => true, 'is_featured' => $featured,
            'published_at' => $publishedAt ?: now()->subDay()]);
        $blog->slug()->create(['slug' => $slug]);
        return $blog;
    }

    public function test_api_selects_featured_posts_and_paginates_remaining_cards_using_cms_settings(): void
    {
        SiteSetting::put('blog_listing_per_page', 1);
        SiteSetting::put('blog_featured_limit', 1);
        $this->createBlog('featured-first', true);
        $this->createBlog('featured-overflow', true);
        $this->createBlog('normal-post');
        $draft = $this->createBlog('draft-post');
        $draft->update(['status' => false]);
        $this->createBlog('future-post', false, now()->addDay()->toDateTimeString());
        $this->getJson('/api/frontend/blog-index?page=1')->assertOk()
            ->assertJsonPath('data.featured.0.slug', 'featured-first')
            ->assertJsonPath('data.blogs.0.slug', 'featured-overflow')
            ->assertJsonPath('data.meta.total', 2)
            ->assertJsonPath('data.meta.last_page', 2);
        $this->getJson('/api/frontend/blog-index?page=2')->assertOk()
            ->assertJsonPath('data.blogs.0.slug', 'normal-post');
    }

    public function test_recent_api_excludes_the_current_post_and_unpublished_content(): void
    {
        $this->createBlog('current-post');
        $this->createBlog('other-post');
        $draft = $this->createBlog('draft-post');
        $draft->update(['status' => false]);
        $this->getJson('/api/frontend/blogs/recent?exclude_slug=current-post&limit=5')->assertOk()
            ->assertJsonCount(1, 'data.items')->assertJsonPath('data.items.0.slug', 'other-post');
        $this->getJson('/api/frontend/blogs/recent?limit=999')->assertUnprocessable();
    }
}
