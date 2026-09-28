<?php

namespace Tests\Feature\Admin;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Redirect;
use App\Models\Service;
use App\Support\FrontendPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class ContentStructureManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_service_categories_as_direct_categories(): void
    {
        $this->actingAsAdmin();

        $root = Category::factory()->service()->create([
            'name' => 'Sectors',
            'category_type' => 'sector',
        ]);

        $response = $this->post(route('admin.categories.store', 'service'), [
            'name' => 'Healthcare',
            'slug' => 'healthcare',
            'parent_id' => $root->id,
            'category_type' => 'sector',
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.categories.index', 'service'));

        $created = Category::query()
            ->where('name', 'Healthcare')
            ->firstOrFail();

        $this->assertNull($created->parent_id);
        $this->assertSame(0, $created->depth);
        $this->assertSame('sector', $created->category_type);
    }

    public function test_service_category_form_hides_removed_crud_fields(): void
    {
        $this->actingAsAdmin();

        $this->assertFalse(Schema::hasColumn('categories', 'description'));
        $this->assertFalse(Schema::hasColumn('categories', 'menu_title'));
        $this->assertFalse(Schema::hasColumn('categories', 'menu_description'));

        $response = $this->get(route('admin.categories.create', 'service'));

        $response->assertOk();
        $response->assertSee('name="short_description"', false);
        $response->assertSee('name="sub_heading1"', false);
        $response->assertSee('name="sub_heading_description"', false);
        $response->assertSee('name="banner_description"', false);
        $response->assertSee('name="banner_desktop[file]"', false);
        $response->assertDontSee('name="description"', false);
        $response->assertDontSee('name="menu_title"', false);
        $response->assertDontSee('name="menu_description"', false);
        $response->assertDontSee('name="icon[file]"', false);
        $response->assertDontSee('name="review_ids[]"', false);
        $response->assertDontSee('Reviews / Testimonials');
    }

    public function test_updating_service_category_ignores_removed_crud_inputs_without_side_effects(): void
    {
        $this->actingAsAdmin();

        $category = Category::factory()->service()->create([
            'name' => 'Guard Services',
        ]);

        $response = $this->put(route('admin.categories.update', ['service', $category]), [
            'name' => 'Guard Services Updated',
            'slug' => $category->slug->slug,
            'parent_id' => '',
            'category_type' => 'service',
            'status' => 1,
            'is_featured' => 0,
            'menu_status' => 1,
            'sub_heading1' => 'Updated sub heading',
            'sub_heading_description' => 'Updated sub heading description.',
            'description' => 'Removed input should be ignored.',
            'menu_title' => 'Removed input should be ignored.',
            'menu_description' => 'Removed input should be ignored.',
            'icon' => [
                'path' => 'managed/categories/service/icon/removed-icon.png',
                'disk' => 'public',
            ],
        ]);

        $response->assertRedirect(route('admin.categories.index', 'service'));
        $response->assertSessionHasNoErrors();

        $category->refresh();

        $this->assertSame('Guard Services Updated', $category->name);
        $this->assertSame('Updated sub heading', $category->sub_heading1);
        $this->assertSame('Updated sub heading description.', $category->sub_heading_description);
        $this->assertDatabaseMissing('images', [
            'imageable_type' => $category->getMorphClass(),
            'imageable_id' => $category->id,
            'image_type' => 'icon',
        ]);
    }

    public function test_admin_can_assign_a_parent_location_directly(): void
    {
        $this->actingAsAdmin();

        $parent = Location::factory()->create([
            'title' => 'Pakistan',
        ]);

        $child = Location::factory()->create([
            'title' => 'Karachi',
        ]);

        $response = $this->put(route('admin.locations.update', $child), [
            'title' => 'Karachi',
            'slug' => $child->slug->slug,
            'parent_id' => $parent->id,
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.locations.index'));
        $this->assertDatabaseHas('locations', [
            'id' => $child->id,
            'parent_id' => $parent->id,
        ]);
    }

    public function test_admin_can_sync_location_child_links_grouped_services_sections_and_related_content(): void
    {
        $this->actingAsAdmin();

        $primaryParent = Location::factory()->create();
        $linkedParent = Location::factory()->create();
        $linkedChild = Location::factory()->create();
        $location = Location::factory()->create();
        $linkedV1 = Service::factory()->create();
        $linkedV2 = Service::factory()->create();
        $linkedV3 = Service::factory()->create();
        $linkedV4 = Service::factory()->create();
        $blog = Blog::factory()->create();
        $section3Faq = Faq::factory()->create();
        $section4Faq = Faq::factory()->create();

        $response = $this->put(route('admin.locations.update', $location), [
            'title' => $location->title,
            'slug' => $location->slug->slug,
            'parent_id' => $primaryParent->id,
            'parent_location_ids' => [$linkedParent->id],
            'child_location_ids' => [$linkedChild->id],
            'sub_heading' => 'London security coverage',
            'section_2_heading' => 'Local assessment',
            'section_2_description' => 'Assess local risk.',
            'section_2_button_name' => 'Book survey',
            'section_2_button_url' => '/locations/survey',
            'linked_services_v1_heading' => 'Local services',
            'linked_services_v1_sub_description' => 'Services available here.',
            'linked_service_v1_ids' => [$linkedV1->id],
            'section_3_locations_faqs' => [
                'heading' => 'Location FAQs',
                'sub_description' => 'Answers for this location.',
                'button_name' => 'Read FAQs',
                'button_url' => '/locations/faqs',
            ],
            'section_4_locations_faqs' => [
                'heading' => 'More Location FAQs',
                'sub_description' => 'More answers for this location.',
                'button_name' => 'Explore FAQs',
                'button_url' => '/locations/more-faqs',
            ],
            'section_3_location_faq_ids' => [$section3Faq->id],
            'section_4_location_faq_ids' => [$section4Faq->id],
            'section_5_heading' => 'Deployment',
            'section_5_description' => 'Deploy locally.',
            'section_6_heading' => 'Reporting',
            'section_6_description' => 'Report locally.',
            'linked_child_locations_heading' => 'Nearby locations',
            'linked_child_locations_sub_description' => 'Operational nearby areas.',
            'section_7_heading' => 'Support',
            'section_7_description' => 'Support this area.',
            'section_8_heading' => 'Coverage',
            'section_8_description' => 'Coverage details.',
            'linked_services_v2_heading' => 'Specialist services',
            'linked_services_v2_sub_description' => 'Specialist location services.',
            'linked_service_v2_ids' => [$linkedV2->id],
            'related_blogs_heading' => 'Location insights',
            'related_blogs_sub_heading' => 'Articles for this location.',
            'related_blog_ids' => [$blog->id],
            'linked_services_v3_heading' => 'More services',
            'linked_services_v3_sub_description' => 'More options.',
            'linked_service_v3_ids' => [$linkedV3->id],
            'linked_services_v4_heading' => 'Additional services',
            'linked_services_v4_sub_description' => 'Additional options.',
            'linked_service_v4_ids' => [$linkedV4->id],
            'section_9_map_src' => 'https://www.google.com/maps/embed?pb=test-location',
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.locations.index'));

        $this->assertDatabaseMissing('location_links', [
            'parent_location_id' => $linkedParent->id,
            'child_location_id' => $location->id,
        ]);

        $this->assertDatabaseHas('location_links', [
            'parent_location_id' => $location->id,
            'child_location_id' => $linkedChild->id,
        ]);

        $this->assertDatabaseHas('location_linked_services', [
            'location_id' => $location->id,
            'linked_service_id' => $linkedV1->id,
            'link_group' => 'v1',
        ]);

        $this->assertDatabaseHas('location_linked_services', [
            'location_id' => $location->id,
            'linked_service_id' => $linkedV2->id,
            'link_group' => 'v2',
        ]);

        $this->assertDatabaseHas('location_linked_services', [
            'location_id' => $location->id,
            'linked_service_id' => $linkedV3->id,
            'link_group' => 'v3',
        ]);

        $this->assertDatabaseHas('location_linked_services', [
            'location_id' => $location->id,
            'linked_service_id' => $linkedV4->id,
            'link_group' => 'v4',
        ]);

        $this->assertDatabaseHas('blog_location', [
            'blog_id' => $blog->id,
            'location_id' => $location->id,
        ]);

        $this->assertDatabaseHas('location_section_faqs', [
            'location_id' => $location->id,
            'faq_id' => $section3Faq->id,
            'section_key' => 'section_3',
        ]);

        $this->assertDatabaseHas('location_section_faqs', [
            'location_id' => $location->id,
            'faq_id' => $section4Faq->id,
            'section_key' => 'section_4',
        ]);

        $location->refresh();
        $this->assertSame('London security coverage', $location->sub_heading);
        $this->assertSame('Local services', $location->linked_services_v1_heading);
        $this->assertSame('Location FAQs', $location->section_3_locations_faqs['heading']);
        $this->assertSame('More Location FAQs', $location->section_4_locations_faqs['heading']);
        $this->assertSame('Nearby locations', $location->linked_child_locations_heading);
        $this->assertSame('Specialist services', $location->linked_services_v2_heading);
        $this->assertSame('Location insights', $location->related_blogs_heading);
        $this->assertSame('More services', $location->linked_services_v3_heading);
        $this->assertSame('Additional services', $location->linked_services_v4_heading);
        $this->assertSame('https://www.google.com/maps/embed?pb=test-location', $location->section_9_map_src);
    }

    public function test_location_form_hides_removed_fields_and_shows_ordered_page_fields(): void
    {
        $this->actingAsAdmin();

        $this->assertTrue(Schema::hasColumn('locations', 'sub_heading'));
        $this->assertTrue(Schema::hasColumn('locations', 'section_2_heading'));
        $this->assertTrue(Schema::hasColumn('locations', 'linked_services_v1_heading'));
        $this->assertTrue(Schema::hasColumn('locations', 'section_3_locations_faqs'));
        $this->assertTrue(Schema::hasColumn('locations', 'section_4_locations_faqs'));
        $this->assertTrue(Schema::hasColumn('locations', 'linked_child_locations_heading'));
        $this->assertTrue(Schema::hasColumn('locations', 'linked_services_v4_sub_description'));
        $this->assertTrue(Schema::hasColumn('locations', 'section_9_map_src'));
        $this->assertFalse(Schema::hasColumn('locations', 'linked_services_v5_heading'));
        $this->assertFalse(Schema::hasColumn('locations', 'linked_services_v5_sub_description'));
        $this->assertFalse(Schema::hasColumn('locations', 'linked_services_v6_heading'));
        $this->assertFalse(Schema::hasColumn('locations', 'linked_services_v6_sub_description'));
        $this->assertFalse(Schema::hasColumn('locations', 'reviews_section_heading'));
        $this->assertFalse(Schema::hasColumn('locations', 'reviews_section_sub_description'));
        $this->assertTrue(Schema::hasTable('location_linked_services'));
        $this->assertTrue(Schema::hasTable('location_section_faqs'));

        $response = $this->get(route('admin.locations.create'));

        $response->assertOk();
        $response->assertDontSee('Linked Parent Locations');
        $response->assertDontSee('Related Services');
        $response->assertDontSee('Reviews / Testimonials');
        $response->assertDontSee('Linked Services V5');
        $response->assertDontSee('Linked Services V6');
        $response->assertDontSee('Reviews Heading');
        $response->assertDontSee('Reviews Sub Description');
        $response->assertDontSee('name="reviews_section_heading"', false);
        $response->assertDontSee('name="reviews_section_sub_description"', false);
        $response->assertDontSee('name="parent_location_ids[]"', false);
        $response->assertDontSee('name="related_service_ids[]"', false);
        $response->assertDontSee('name="review_ids[]"', false);
        $response->assertSee('name="sub_heading"', false);
        $response->assertSee('name="section_2_side_image[file]"', false);
        $response->assertSee('name="section_8_side_image[file]"', false);
        $response->assertSee('name="linked_service_v1_ids[]"', false);
        $response->assertSee('name="linked_service_v4_ids[]"', false);
        $response->assertSee('name="section_3_location_faq_ids[]"', false);
        $response->assertSee('name="section_4_location_faq_ids[]"', false);
        $response->assertSee('name="section_9_map_src"', false);
        $response->assertSeeInOrder([
            'Title',
            'Slug',
            'Primary Parent Location',
            'Featured',
            'Status',
            'Sub Heading',
            'Short Description',
            'Section 2 Heading',
            'Linked Services V1 Heading',
            'Section 3 Locations FAQs Heading',
            'Section 4 Locations FAQs Heading',
            'Section 5 Heading',
            'Section 6 Heading',
            'Linked Child Locations Heading',
            'Linked Child Locations',
            'Section 7 Heading',
            'Section 8 Heading',
            'Linked Services V2 Heading',
            'FAQs',
            'Related Blogs Heading',
            'Related Blogs',
            'Linked Services V3 Heading',
            'Linked Services V4 Heading',
            'Section 9 Map Src',
            'Meta Title',
        ]);
    }

    public function test_removed_parent_service_input_is_ignored(): void
    {
        $this->actingAsAdmin();

        $parent = Service::factory()->create([
            'title' => 'Security Services',
        ]);

        $child = Service::factory()->create([
            'title' => 'Executive Protection',
        ]);
        $category = Category::factory()->service()->create();
        $category->services()->attach($child);

        $response = $this->put(route('admin.services.update', $child), [
            'title' => 'Executive Protection',
            'slug' => $child->slug->slug,
            'parent_id' => $parent->id,
            'category_id' => $category->id,
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.services.index'));
        $this->assertDatabaseHas('services', ['id' => $child->id, 'parent_id' => null]);
    }

    public function test_admin_can_sync_service_link_groups_sections_and_related_content(): void
    {
        $this->actingAsAdmin();

        $primaryParent = Service::factory()->create();
        $linkedV1 = Service::factory()->create();
        $linkedV2 = Service::factory()->create();
        $linkedV3 = Service::factory()->create();
        $service = Service::factory()->create();
        $category = Category::factory()->service()->create();
        $category->services()->attach($service);
        $location = Location::factory()->create();
        $blog = Blog::factory()->create();
        $section2Faq = Faq::factory()->create();
        $section3Faq = Faq::factory()->create();

        $response = $this->put(route('admin.services.update', $service), [
            'title' => $service->title,
            'slug' => $service->slug->slug,
            'parent_id' => $primaryParent->id,
            'category_id' => $category->id,
            'linked_services_v1_heading' => 'Popular Services',
            'linked_services_v1_sub_description' => 'Core services linked to this page.',
            'linked_service_v1_ids' => [$linkedV1->id],
            'linked_services_v2_heading' => 'Related Solutions',
            'linked_services_v2_sub_description' => 'Alternative services for deeper coverage.',
            'linked_service_v2_ids' => [$linkedV2->id],
            'linked_services_v3_heading' => 'Next Steps',
            'linked_services_v3_sub_description' => 'Services customers often review next.',
            'linked_service_v3_ids' => [$linkedV3->id],
            'section_2_heading' => 'Assess',
            'section_2_description' => 'Assess the service requirement.',
            'section_3_heading' => 'Plan',
            'section_3_description' => 'Plan the deployment.',
            'section_4_heading' => 'Deploy',
            'section_4_description' => 'Deploy the team.',
            'section_5_heading' => 'Monitor',
            'section_5_description' => 'Monitor the operation.',
            'section_6_heading' => 'Report',
            'section_6_description' => 'Report the outcome.',
            'section_7_heading' => 'Talk to an expert',
            'section_7_description' => 'Discuss this service.',
            'section_7_button_name' => 'Contact us',
            'section_7_button_url' => '/contact',
            'section_8_heading' => 'Get coverage',
            'section_8_description' => 'Start service coverage.',
            'section_8_button_name' => 'Request quote',
            'section_8_button_url' => '/quote',
            'related_locations_heading' => 'Service locations',
            'related_locations_sub_heading' => 'Areas where this service is available.',
            'related_location_ids' => [$location->id],
            'related_blog_ids' => [$blog->id],
            'section_9_heading' => 'Need support',
            'section_9_description' => 'Speak with the operations desk.',
            'section_9_button_name' => 'Call now',
            'section_9_button_url' => '/call',
            'related_blogs_heading' => 'Service insights',
            'related_blogs_sub_heading' => 'Guidance related to this service.',
            'section_3_sector_faq_ids' => [$section2Faq->id],
            'section_4_sector_faq_ids' => [$section3Faq->id],
            'section_3_sectors_faqs' => [
                'heading' => 'Sector FAQs',
                'sub_description' => 'Helpful answers for sectors.',
                'button_name' => 'Read more',
                'button_url' => '/sectors/faqs',
            ],
            'section_4_sectors_faqs' => [
                'heading' => 'More Sector FAQs',
                'sub_description' => 'More helpful answers.',
                'button_name' => 'Explore',
                'button_url' => 'https://example.test/sectors',
            ],
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.services.index'));

        $this->assertDatabaseHas('service_linked_services', [
            'service_id' => $service->id,
            'linked_service_id' => $linkedV1->id,
            'link_group' => 'v1',
        ]);

        $this->assertDatabaseHas('service_linked_services', [
            'service_id' => $service->id,
            'linked_service_id' => $linkedV2->id,
            'link_group' => 'v2',
        ]);

        $this->assertDatabaseMissing('service_linked_services', [
            'service_id' => $service->id,
            'linked_service_id' => $linkedV3->id,
            'link_group' => 'v3',
        ]);

        $this->assertDatabaseHas('location_service', [
            'location_id' => $location->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseMissing('blog_service', [
            'blog_id' => $blog->id,
            'service_id' => $service->id,
        ]);

        $this->assertDatabaseMissing('service_sector_faqs', [
            'service_id' => $service->id,
            'faq_id' => $section2Faq->id,
            'section_key' => 'section_3',
        ]);

        $this->assertDatabaseMissing('service_sector_faqs', [
            'service_id' => $service->id,
            'faq_id' => $section3Faq->id,
            'section_key' => 'section_4',
        ]);

        $service->refresh();
        $this->assertSame('Popular Services', $service->linked_services_v1_heading);
        $this->assertSame('Core services linked to this page.', $service->linked_services_v1_sub_description);
        $this->assertSame('Related Solutions', $service->linked_services_v2_heading);
        $this->assertSame('Alternative services for deeper coverage.', $service->linked_services_v2_sub_description);
        $this->assertNull($service->linked_services_v3_heading);
        $this->assertNull($service->linked_services_v3_sub_description);
        $this->assertSame('Assess', $service->section_2_heading);
        $this->assertSame('Report the outcome.', $service->section_6_description);
        $this->assertSame('Talk to an expert', $service->section_7_heading);
        $this->assertSame('/quote', $service->section_8_button_url);
        $this->assertSame('Service locations', $service->related_locations_heading);
        $this->assertSame('Need support', $service->section_9_heading);
        $this->assertNull($service->related_blogs_heading);
        $this->assertNull($service->section_3_sectors_faqs);
    }

    public function test_service_form_hides_removed_fields_and_shows_new_fields(): void
    {
        $this->actingAsAdmin();

        $this->assertFalse(Schema::hasColumn('services', 'description'));
        $this->assertFalse(Schema::hasColumn('services', 'position'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v1_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v1_sub_description'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v2_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v2_sub_description'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v3_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'linked_services_v3_sub_description'));
        $this->assertTrue(Schema::hasColumn('services', 'section_2_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'section_9_button_url'));
        $this->assertTrue(Schema::hasColumn('services', 'related_locations_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'related_blogs_sub_heading'));
        $this->assertTrue(Schema::hasColumn('services', 'section_3_sectors_faqs'));
        $this->assertTrue(Schema::hasColumn('services', 'section_4_sectors_faqs'));
        $this->assertTrue(Schema::hasTable('service_sector_faqs'));

        $response = $this->get(route('admin.services.create'));

        $response->assertOk();
        $response->assertDontSee('Linked Parent Services');
        $response->assertDontSee('Linked Child Services');
        $response->assertDontSee('name="description"', false);
        $response->assertDontSee('Reviews / Testimonials');
        $response->assertDontSee('name="review_ids[]"', false);
        $response->assertSee('Linked Services V1');
        $response->assertSee('Linked Services V2');
        $response->assertDontSee('Linked Services V3');
        $response->assertSee('name="linked_services_v1_heading"', false);
        $response->assertSee('name="linked_services_v1_sub_description"', false);
        $response->assertSee('name="linked_services_v2_heading"', false);
        $response->assertSee('name="linked_services_v2_sub_description"', false);
        $response->assertDontSee('name="linked_services_v3_heading"', false);
        $response->assertDontSee('name="linked_services_v3_sub_description"', false);
        $response->assertDontSee('name="section_3_sectors_faqs[heading]"', false);
        $response->assertDontSee('name="section_4_sectors_faqs[heading]"', false);
        $response->assertDontSee('name="section_3_sector_faq_ids[]"', false);
        $response->assertDontSee('name="section_4_sector_faq_ids[]"', false);
        $response->assertSee('name="section_2_heading"', false);
        $response->assertSee('name="section_9_button_url"', false);
        $response->assertSee('name="section_2_side_image[file]"', false);
        $response->assertSee('name="section_9_side_image[file]"', false);
        $response->assertSee('name="related_locations_heading"', false);
        $response->assertDontSee('name="related_blogs_sub_heading"', false);
    }

    public function test_admin_can_sync_blog_links_and_related_content(): void
    {
        $this->actingAsAdmin();

        $linkedParent = Blog::factory()->create();
        $linkedChild = Blog::factory()->create();
        $blog = Blog::factory()->create();
        $location = Location::factory()->create();

        $response = $this->put(route('admin.blogs.update', $blog), [
            'title' => $blog->title,
            'slug' => $blog->slug->slug,
            'excerpt' => 'Short blog excerpt.',
            'related_location_ids' => [$location->id],
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.blogs.index'));

        $this->assertDatabaseMissing('blog_links', [
            'parent_blog_id' => $linkedParent->id,
            'child_blog_id' => $blog->id,
        ]);

        $this->assertDatabaseMissing('blog_links', [
            'parent_blog_id' => $blog->id,
            'child_blog_id' => $linkedChild->id,
        ]);

        $this->assertDatabaseMissing('blog_location', [
            'blog_id' => $blog->id,
            'location_id' => $location->id,
        ]);

        $this->assertSame('Short blog excerpt.', $blog->fresh()->excerpt);
    }

    public function test_pages_admin_crud_routes_are_registered(): void
    {
        $this->actingAsAdmin();

        $this->assertTrue(Route::has('admin.pages.index'));
        $this->assertTrue(Route::has('admin.pages.store'));
        $this->get(route('admin.pages.create'))->assertOk();
    }

    public function test_page_form_hides_removed_fields_and_shows_linked_sections(): void
    {
        $this->actingAsAdmin();

        $this->assertTrue(Schema::hasColumn('pages', 'linked_services_v1_heading'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_services_v6_button_url'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_locations_v1_heading'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_locations_v3_button_url'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_faqs_v1_heading'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_faqs_v3_button_url'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_blogs_v1_heading'));
        $this->assertTrue(Schema::hasColumn('pages', 'linked_blogs_v2_sub_description'));
        $this->assertTrue(Schema::hasTable('page_linked_services'));
        $this->assertTrue(Schema::hasTable('page_linked_locations'));
        $this->assertTrue(Schema::hasTable('page_linked_faqs'));
        $this->assertTrue(Schema::hasTable('page_linked_blogs'));

        $response = $this->get(route('admin.pages.create'));

        $response->assertOk();
        $response->assertDontSee('name="template_name"', false);
        $response->assertDontSee('name="last_updated_at"', false);
        $response->assertDontSee('name="page_content"', false);
        $response->assertDontSee('name="thumbnail[file]"', false);
        $response->assertDontSee('name="service_ids[]"', false);
        $response->assertDontSee('name="location_ids[]"', false);
        $response->assertDontSee('name="blog_ids[]"', false);
        $response->assertDontSee('name="category_ids[]"', false);
        $response->assertDontSee('name="faq_ids[]"', false);
        $response->assertSee('name="linked_services_v1_heading"', false);
        $response->assertSee('name="linked_services_v6_button_url"', false);
        $response->assertSee('name="linked_service_v1_ids[]"', false);
        $response->assertSee('name="linked_services_v6_side_image[file]"', false);
        $response->assertSee('name="linked_locations_v1_heading"', false);
        $response->assertSee('name="linked_location_v3_ids[]"', false);
        $response->assertSee('name="linked_locations_v3_side_image[file]"', false);
        $response->assertDontSee('name="linked_location_v4_ids[]"', false);
        $response->assertSee('name="linked_faqs_v1_heading"', false);
        $response->assertSee('name="linked_faq_v3_ids[]"', false);
        $response->assertSee('name="linked_faqs_v3_side_image[file]"', false);
        $response->assertDontSee('name="linked_faq_v4_ids[]"', false);
        $response->assertSee('name="linked_blogs_v1_heading"', false);
        $response->assertSee('name="linked_blog_v2_ids[]"', false);
        $response->assertSee('name="linked_blogs_v2_side_image[file]"', false);
        $response->assertDontSee('name="linked_blogs_v1_button_name"', false);
        $response->assertDontSee('name="linked_blogs_v1_button_url"', false);
        $response->assertDontSee('name="linked_blogs_v2_button_name"', false);
        $response->assertDontSee('name="linked_blogs_v2_button_url"', false);
        $response->assertDontSee('name="linked_blog_v3_ids[]"', false);
        $response->assertSeeInOrder([
            'Page Title',
            'Slug',
            'Page Type',
            'Status',
            'Banner Title',
            'Button 1 Name',
            'Banner',
            'Linked Services V1 Heading',
            'Linked Services V6',
            'Linked Locations V1 Heading',
            'Linked Locations V3',
            'Linked FAQs V1 Heading',
            'Linked FAQs V3',
            'Linked Blogs V1 Heading',
            'Linked Blogs V2',
            'Meta Title',
        ]);
    }

    public function test_admin_can_sync_page_linked_sections_without_clearing_removed_fields(): void
    {
        $this->actingAsAdmin();

        $page = Page::factory()->create([
            'template_name' => 'static_v7',
            'last_updated_at' => now()->subDay(),
            'page_content' => 'Existing page content should stay untouched.',
        ]);
        $legacyService = Service::factory()->create();
        $legacyLocation = Location::factory()->create();
        $legacyBlog = Blog::factory()->create();
        $legacyFaq = Faq::factory()->create();
        $linkedService = Service::factory()->create();
        $linkedLocation = Location::factory()->create();
        $linkedFaq = Faq::factory()->create();
        $linkedBlog = Blog::factory()->create();

        $page->services()->sync([$legacyService->id]);
        $page->locations()->sync([$legacyLocation->id]);
        $page->blogs()->sync([$legacyBlog->id]);
        $page->faqs()->sync([$legacyFaq->id]);

        $response = $this->put(route('admin.pages.update', $page), [
            'page_title' => 'Operations Page',
            'slug' => $page->slug->slug,
            'page_type' => 'landing',
            'banner_title' => 'Operations',
            'banner_description' => 'Operations copy.',
            'banner_short_description' => 'Short operations copy.',
            'button1_name' => 'Call',
            'button1_link' => '/call',
            'button2_name' => 'Quote',
            'button2_link' => '/quote',
            'linked_services_v6_heading' => 'Services',
            'linked_services_v6_sub_description' => 'Service section copy.',
            'linked_services_v6_button_name' => 'View services',
            'linked_services_v6_button_url' => '/services',
            'linked_service_v6_ids' => [$linkedService->id],
            'linked_locations_v3_heading' => 'Locations',
            'linked_locations_v3_sub_description' => 'Location section copy.',
            'linked_locations_v3_button_name' => 'View locations',
            'linked_locations_v3_button_url' => '/locations',
            'linked_location_v3_ids' => [$linkedLocation->id],
            'linked_faqs_v3_heading' => 'FAQs',
            'linked_faqs_v3_sub_description' => 'FAQ section copy.',
            'linked_faqs_v3_button_name' => 'Read FAQs',
            'linked_faqs_v3_button_url' => '/faqs',
            'linked_faq_v3_ids' => [$linkedFaq->id],
            'linked_blogs_v2_heading' => 'Blogs',
            'linked_blogs_v2_sub_description' => 'Blog section copy.',
            'linked_blog_v2_ids' => [$linkedBlog->id],
            'status' => 1,
        ]);

        $response->assertRedirect(route('admin.pages.index'));
        $response->assertSessionHasNoErrors();

        $this->assertDatabaseHas('page_linked_services', [
            'page_id' => $page->id,
            'linked_service_id' => $linkedService->id,
            'link_group' => 'v6',
        ]);
        $this->assertDatabaseHas('page_linked_locations', [
            'page_id' => $page->id,
            'linked_location_id' => $linkedLocation->id,
            'link_group' => 'v3',
        ]);
        $this->assertDatabaseHas('page_linked_faqs', [
            'page_id' => $page->id,
            'faq_id' => $linkedFaq->id,
            'link_group' => 'v3',
        ]);
        $this->assertDatabaseHas('page_linked_blogs', [
            'page_id' => $page->id,
            'linked_blog_id' => $linkedBlog->id,
            'link_group' => 'v2',
        ]);
        $this->assertDatabaseHas('pageables', [
            'page_id' => $page->id,
            'pageable_type' => $legacyService->getMorphClass(),
            'pageable_id' => $legacyService->id,
        ]);
        $this->assertDatabaseHas('pageables', [
            'page_id' => $page->id,
            'pageable_type' => $legacyLocation->getMorphClass(),
            'pageable_id' => $legacyLocation->id,
        ]);
        $this->assertDatabaseHas('pageables', [
            'page_id' => $page->id,
            'pageable_type' => $legacyBlog->getMorphClass(),
            'pageable_id' => $legacyBlog->id,
        ]);
        $this->assertDatabaseHas('faqables', [
            'faq_id' => $legacyFaq->id,
            'faqable_type' => $page->getMorphClass(),
            'faqable_id' => $page->id,
        ]);

        $page->refresh();
        $this->assertSame('static_v7', $page->template_name);
        $this->assertSame('Existing page content should stay untouched.', $page->page_content);
        $this->assertNotNull($page->last_updated_at);
        $this->assertSame('Services', $page->linked_services_v6_heading);
        $this->assertSame('/locations', $page->linked_locations_v3_button_url);
        $this->assertSame('FAQs', $page->linked_faqs_v3_heading);
        $this->assertSame('Blogs', $page->linked_blogs_v2_heading);
    }

    public function test_slug_changes_create_auto_generated_redirects_to_the_latest_path(): void
    {
        $this->actingAsAdmin();

        $service = Service::factory()->create([
            'title' => 'Integrated Security',
        ]);
        $category = Category::factory()->service()->create();
        $category->services()->attach($service);

        $oldSlug = $service->slug->slug;
        $firstSlug = 'integrated-security-updated';
        $secondSlug = 'integrated-security-final';

        $this->put(route('admin.services.update', $service), [
            'title' => $service->title,
            'slug' => $firstSlug,
            'category_id' => $category->id,
            'status' => 1,
        ])->assertRedirect(route('admin.services.index'));

        $service->refresh();

        $this->put(route('admin.services.update', $service), [
            'title' => $service->title,
            'slug' => $secondSlug,
            'category_id' => $category->id,
            'status' => 1,
        ])->assertRedirect(route('admin.services.index'));

        $service->refresh();

        $this->assertDatabaseHas('redirects', [
            'from_url' => FrontendPath::forModelSlug($service, $oldSlug),
            'to_url' => FrontendPath::forModelSlug($service, $secondSlug),
            'status_code' => 301,
            'status' => 1,
            'is_auto_generated' => 1,
            'sourceable_type' => $service->getMorphClass(),
            'sourceable_id' => $service->id,
        ]);

        $this->assertDatabaseHas('redirects', [
            'from_url' => FrontendPath::forModelSlug($service, $firstSlug),
            'to_url' => FrontendPath::forModelSlug($service, $secondSlug),
            'status_code' => 301,
            'status' => 1,
            'is_auto_generated' => 1,
            'sourceable_type' => $service->getMorphClass(),
            'sourceable_id' => $service->id,
        ]);

        $this->assertSame(2, Redirect::query()->where('sourceable_id', $service->id)->count());
    }
}
