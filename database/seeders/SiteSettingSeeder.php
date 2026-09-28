<?php

namespace Database\Seeders;

use App\Http\Controllers\Admin\SiteSettingController;
use App\Models\SiteSetting;
use App\Support\SchemaTemplates;
use App\Support\SeoConfiguratorFields;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;

/**
 * Seeds editable Bridgeway Digital site settings consumed by the frontend API.
 */
class SiteSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = $this->defaults();

        foreach (SiteSettingController::FIELDS as $group => $fields) {
            foreach ($fields as $field) {
                SiteSetting::firstOrCreate(
                    ['key' => $field['key']],
                    ['value' => $defaults[$field['key']] ?? null, 'group' => $group]
                );
            }
        }

        foreach (SeoConfiguratorFields::fields() as $field) {
            SiteSetting::firstOrCreate(
                ['key' => $field['key']],
                ['value' => $defaults[$field['key']] ?? null, 'group' => 'seo']
            );
        }

        $this->seedSeoTemplates();

        Cache::forget(SiteSetting::CACHE_KEY);
    }

    public function defaults(): array
    {
        return [
            'website_default_title' => 'Bridgeway Digital',
            'website_title_template' => '%s | Bridgeway Digital',
            'website_meta_description' => 'Bridgeway Digital',
            'blog_listing_per_page' => '10',
            'blog_featured_limit' => '5',
            'site_name' => 'Bridgeway Digital',
            'site_tagline' => 'World-class solutions, unparalleled growth.',
            'copyright_text' => 'Copyright 2025 bridgewaydigital. All Rights Reserved',
            'admin_email' => 'sales@bridgewaydigital.com',
            'contact_email_addresses' => '["sales@bridgewaydigital.com"]',
            'contact_phone_numbers' => '["(832) 266-0227"]',
            'contact_phone_href' => 'tel:+18322660227',
            'location_usa' => '12808 W Airport Blvd Sugar Land, TX 77478',
            'location_uk' => '582 Honeypot Lane Stanmore, HA7 1JS',
            'location_uae' => '1424A 14th Floor, Regal Tower, Dubai UAE',
            'location_nl' => 'Prof. JH Bavincklaan 7 Amstelveen',
            'country_options' => $this->countryOptions(),
            'social_facebook' => 'https://facebook.com/bridgewaydigital',
            'social_linkedin' => 'https://linkedin.com/company/bridgewaydigital',
            'social_whatsapp' => 'https://wa.me/18322660227',
            'social_twitter' => 'https://twitter.com/bridgewaydigital',
            'social_instagram' => 'https://instagram.com/bridgewaydigital',
            'default_meta_title' => 'Bridgeway Digital',
            'default_meta_description' => 'Digital marketing, Amazon growth and web development services from Bridgeway Digital.',
            'default_robots_index' => 'index',
            'default_robots_follow' => 'follow',
            'default_enable_schema' => '1',
            'default_schema' => SchemaTemplates::encode(SchemaTemplates::sitewide()),
            'default_og_tags' => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['website']),
            'template_static_meta_title' => '{title} | Bridgeway Digital',
            'template_service_meta_title' => '{title} | Bridgeway Digital',
            'template_sector_meta_title' => '{title} | Bridgeway Digital',
            'template_category_meta_title' => '{title} | Bridgeway Digital',
            'template_location_meta_title' => '{title} | Bridgeway Digital',
            'template_locations_meta_title' => '{title} | Bridgeway Digital',
            'template_blog_meta_title' => '{title} | Bridgeway Digital Blog',
            'template_blogs_meta_title' => 'Insights & News | Bridgeway Digital Blog',
            'inquiry_spam_detection_enabled' => '1',
            'inquiry_spam_block_score' => '5',
            'inquiry_blocked_keywords' => 'test,testing,090078601',
            'inquiry_field_block_rules' => "name=test,testing\nphone=090078601,123456\nmessage=casino,crypto,loan",
            'onboarding_enabled' => '0',
        ];
    }

    private function countryOptions(): string
    {
        return "United States\nUnited Kingdom\nUnited Arab Emirates\nPakistan";
    }

    private function seedSeoTemplates(): void
    {
        $fillIfEmpty = [
            'default_schema' => SchemaTemplates::encode(SchemaTemplates::sitewide()),
            'default_og_tags' => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['website']),
            'template_blog_og_tags' => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['article']),
        ];

        foreach (SchemaTemplates::templates() as $template => $schema) {
            $fillIfEmpty["template_{$template}_schema"] = SchemaTemplates::encode($schema);
        }

        foreach ($fillIfEmpty as $key => $value) {
            SiteSetting::where('key', $key)
                ->where(function ($query) {
                    $query->whereNull('value')->orWhere('value', '');
                })
                ->update(['value' => $value]);
        }
    }
}
