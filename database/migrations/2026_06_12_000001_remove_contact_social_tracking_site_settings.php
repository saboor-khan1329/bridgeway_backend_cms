<?php

use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Contact info, social links and tracking IDs moved to the frontend env
 * config (client/.env → company.config.js). These site_settings rows are no
 * longer read anywhere, so remove them. Scoped delete by key — every other
 * setting (SEO templates, spam rules, branding, site name…) is untouched.
 */
return new class extends Migration
{
    private const REMOVED_KEYS = [
        // Contact
        'contact_email_addresses',
        'contact_phone_numbers',
        'contact_email',
        'contact_phone',
        'contact_address',
        'whatsapp_number',
        // Social
        'facebook_url',
        'instagram_url',
        'twitter_url',
        'linkedin_url',
        'youtube_url',
        // Tracking
        'gtm_id',
        'google_analytics_id',
        'tracking_head_scripts',
        'tracking_body_scripts',
        'tracking_footer_scripts',
    ];

    public function up(): void
    {
        DB::table('site_settings')->whereIn('key', self::REMOVED_KEYS)->delete();

        Cache::forget('site_settings.all');
        FrontendCache::bump();
    }

    public function down(): void
    {
        // Values now live in the frontend env config; nothing to restore here.
        // Rows are recreated empty by the admin form if the fields ever return.
    }
};
