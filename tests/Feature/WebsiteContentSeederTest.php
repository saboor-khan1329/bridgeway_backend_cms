<?php

namespace Tests\Feature;

use App\Models\Blog;
use App\Models\ContentBlockField;
use App\Models\ContentPage;
use App\Models\Service;
use Database\Seeders\NavigationSeeder;
use Database\Seeders\SiteSettingSeeder;
use Database\Seeders\WebsiteContentSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebsiteContentSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_database_has_authored_content_and_reruns_preserve_editor_changes(): void
    {
        $this->seed([WebsiteContentSeeder::class, SiteSettingSeeder::class, NavigationSeeder::class]);
        $this->assertSame(12, ContentPage::where('is_cms_managed', true)->where('template', '!=', 'global')->count());
        $this->assertSame(9, Service::count());
        $this->assertSame(16, Blog::count());
        $this->assertGreaterThan(1000, ContentBlockField::count());
        foreach (ContentPage::where('template', '!=', 'global')->get() as $page) {
            $this->getJson('/api/frontend/pages/'.$page->slug)->assertOk()
                ->assertJsonPath('data.template', $page->template)
                ->assertJsonPath('data.seo.title', $page->seo->meta_title);
        }
        $this->getJson('/api/frontend/pages/home')->assertNotFound();
        $this->getJson('/api/frontend/globals/home-sections')->assertOk()
            ->assertJsonPath('data.sections.0.data.submitText', 'SEND ME A FREE PROPOSAL');
        $this->getJson('/api/frontend/site')->assertOk()
            ->assertJsonPath('data.globals.floatingCtas.offerText', 'Avail 60% OFF')
            ->assertJsonPath('data.metadata.titleTemplate', '%s | Bridgeway Digital')
            ->assertJsonCount(5, 'data.navigation.services.links')
            ->assertJsonCount(4, 'data.footer.linkGroups');
        foreach (Blog::with('slug')->get() as $blog) {
            $this->getJson('/api/frontend/blogs/'.$blog->slug->slug)->assertOk()
                ->assertJsonPath('data.title', $blog->title);
            $this->assertNotEmpty($blog->content);
            $this->assertNotEmpty($blog->images->first()?->path);
            $this->assertNotEmpty($blog->categories);
        }
        $home = ContentPage::where('slug', 'amazon-marketing-services')->firstOrFail();
        $home->update(['title' => 'Editor title', 'status' => false]);
        $home->seo->update(['meta_title' => 'Editor SEO']);
        $home->contentBlocks()->first()->delete();
        $blocks = $home->contentBlocks()->count();
        $this->seed(WebsiteContentSeeder::class);
        $this->assertSame(18, ContentPage::count());
        $this->assertSame(16, Blog::count());
        $this->assertSame(9, Service::count());
        $this->assertSame('Editor title', $home->fresh()->title);
        $this->assertFalse($home->fresh()->status);
        $this->assertSame('Editor SEO', $home->fresh()->seo->meta_title);
        $this->assertSame($blocks, $home->contentBlocks()->count());
    }
}
