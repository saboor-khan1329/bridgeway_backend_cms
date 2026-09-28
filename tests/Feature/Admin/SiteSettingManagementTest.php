<?php

namespace Tests\Feature\Admin;

use App\Models\SiteSetting;
use Database\Seeders\SiteSettingSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SiteSettingManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_store_multiple_contact_emails_and_phone_numbers(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $this->seed(SiteSettingSeeder::class);

        $response = $this->put(route('admin.settings.update'), [
            'site_name' => 'Bridgeway Digital',
            'site_tagline' => 'Digital growth done properly',
            'company_registration_number' => '12345678',
            'copyright_text' => 'Copyright 2026 Bridgeway Digital. All rights reserved.',
            'admin_email' => 'owner@example.com',
            'whatsapp_number' => '+44 20 0000 0000',
            'contact_email_addresses' => [
                'sales@example.com',
                'support@example.com',
                'helpdesk@example.com',
            ],
            'contact_phone_numbers' => [
                '+1 555 100 1000',
                '+1 555 200 2000',
            ],
            'contact_address' => '123 Main Street',
            'tracking_head_scripts' => '<script>window.dataLayer = window.dataLayer || [];</script>',
            'tracking_body_scripts' => '<noscript>GTM fallback</noscript>',
            'tracking_footer_scripts' => '<script>console.log("footer");</script>',
            'inquiry_spam_detection_enabled' => '1',
            'inquiry_spam_block_score' => 7,
            'inquiry_blocked_keywords' => "test\ntesting\n090078601",
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('success');

        $this->assertSame([
            'sales@example.com',
            'support@example.com',
            'helpdesk@example.com',
        ], SiteSetting::get('contact_email_addresses'));

        $this->assertSame([
            '+1 555 100 1000',
            '+1 555 200 2000',
        ], SiteSetting::get('contact_phone_numbers'));

        $this->assertSame('sales@example.com', SiteSetting::get('contact_email'));
        $this->assertSame('+1 555 100 1000', SiteSetting::get('contact_phone'));
        $this->assertSame('Copyright 2026 Bridgeway Digital. All rights reserved.', SiteSetting::get('copyright_text'));
        $this->assertNull(SiteSetting::get('company_registration_number'));
        $this->assertNull(SiteSetting::get('whatsapp_number'));
        $this->assertSame(7, (int) SiteSetting::get('inquiry_spam_block_score'));
        $this->assertStringContainsString('090078601', SiteSetting::get('inquiry_blocked_keywords'));
    }

    public function test_site_settings_reject_more_than_four_contact_emails(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $this->seed(SiteSettingSeeder::class);

        $response = $this->from(route('admin.settings.edit'))
            ->put(route('admin.settings.update'), [
                'contact_email_addresses' => [
                    'one@example.com',
                    'two@example.com',
                    'three@example.com',
                    'four@example.com',
                    'five@example.com',
                ],
            ]);

        $response->assertRedirect(route('admin.settings.edit'));
        $response->assertSessionHasErrors('contact_email_addresses');
    }

    public function test_site_settings_can_use_a_managed_logo_path(): void
    {
        Storage::fake('public');
        $this->actingAsAdmin();
        $this->seed(SiteSettingSeeder::class);

        Storage::disk('public')->put('managed/site-settings/logo/company-logo.png', 'fake-image');

        $response = $this->put(route('admin.settings.update'), [
            'logo' => [
                'path' => 'managed/site-settings/logo/company-logo.png',
                'disk' => 'public',
            ],
        ]);

        $response->assertRedirect();
        $this->assertSame('managed/site-settings/logo/company-logo.png', SiteSetting::get('logo'));
    }
}
