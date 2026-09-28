<?php

namespace Tests\Feature\Admin;

use App\Models\ContentPage;
use App\Models\Inquiry;
use App\Models\Service;
use App\Models\SiteSetting;
use App\Services\MailDispatchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NewBackendStructureTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_access_amazon_services_tab(): void
    {
        $this->actingAsAdmin();

        $page = ContentPage::create([
            'title' => 'Amazon Wholesale FBA',
            'slug' => 'amazon-fba',
            'template' => 'amazon-service',
            'status' => '1',
            'is_cms_managed' => true,
        ]);

        $response = $this->get('/admin/amazon-services');

        $response->assertOk();
        $response->assertSee('Amazon Services');
        $response->assertSee('Amazon Wholesale FBA');
    }

    public function test_admin_can_access_development_services_tab(): void
    {
        $this->actingAsAdmin();

        $this->post('/admin/development-services', [
            'title' => 'Web Development',
            'slug' => 'web-development',
            'short_description' => 'Custom full stack web development.',
            'status' => '1',
            'sections' => [[
                'id' => 'hero',
                'type' => 'serviceHero',
                'enabled' => true,
                'data' => ['heading' => 'Web Development Services'],
            ]],
        ])->assertRedirect();

        $response = $this->get('/admin/development-services');

        $response->assertOk();
        $response->assertSee('Development Services');
        $response->assertSee('Web Development');
    }

    public function test_admin_can_access_reusable_sections_tab(): void
    {
        $this->actingAsAdmin();

        $quote = ContentPage::create([
            'title' => 'Get a Free Quote',
            'slug' => 'get-a-free-quote',
            'template' => 'get-a-free-quote',
            'status' => '1',
            'is_cms_managed' => true,
        ]);

        $response = $this->get('/admin/reusable-sections');

        $response->assertOk();
        $response->assertSee('Reusable Sections');
        $response->assertSee('Get a Free Quote');
    }

    public function test_admin_can_access_forms_management(): void
    {
        $this->actingAsAdmin();

        $inquiry = Inquiry::create([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'form_name' => 'contactHero',
            'subject' => 'Quote Request',
            'message' => 'Need a project quote',
            'status' => 'new',
        ]);

        $response = $this->get('/admin/forms');

        $response->assertOk();
        $response->assertSee('Forms Management');
        $response->assertSee('Free Quote Request');
        $response->assertSee('John Doe');
        $response->assertSee('john@example.com');
    }

    public function test_admin_can_configure_form_notification_emails(): void
    {
        $this->actingAsAdmin();

        $response = $this->post('/admin/forms/settings', [
            'default_email' => 'general@example.com',
            'form_emails' => [
                'contactHero' => 'quotes@example.com',
                'sertHero' => 'amazon@example.com',
            ],
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('site_settings', [
            'key' => 'form_notification_emails',
            'value' => 'general@example.com',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => 'form_email_contactHero',
            'value' => 'quotes@example.com',
        ]);
        $this->assertDatabaseHas('site_settings', [
            'key' => 'form_email_sertHero',
            'value' => 'amazon@example.com',
        ]);

        // Verify MailDispatchService resolves them correctly
        $dispatchService = app(MailDispatchService::class);

        $quoteInquiry = new Inquiry(['form_name' => 'contactHero']);
        $amazonInquiry = new Inquiry(['form_name' => 'sertHero']);
        $genericInquiry = new Inquiry(['form_name' => 'heroNewsletter']);

        $this->assertSame(['quotes@example.com'], $dispatchService->inquiryRecipients($quoteInquiry));
        $this->assertSame(['amazon@example.com'], $dispatchService->inquiryRecipients($amazonInquiry));
        $this->assertSame(['general@example.com'], $dispatchService->inquiryRecipients($genericInquiry));
    }
}
