<?php

use App\Support\FrontendCache;
use App\Support\SchemaTemplates;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->cleanSiteSettings();
            $this->removeUnusedLegacyOnboardingTemplate();
        });

        Cache::forget('site_settings.all');
        FrontendCache::bump();
    }

    public function down(): void
    {
        // Legacy project configuration is intentionally not restored.
    }

    private function cleanSiteSettings(): void
    {
        if (! Schema::hasTable('site_settings')) {
            return;
        }

        $defaults = [
            'site_name' => 'Bridgeway Digital',
            'website_default_title' => 'Bridgeway Digital',
            'website_title_template' => '%s | Bridgeway Digital',
            'website_meta_description' => 'Bridgeway Digital',
            'default_meta_title' => 'Bridgeway Digital',
            'default_meta_description' => 'Digital marketing, Amazon growth and web development services from Bridgeway Digital.',
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
            'template_blog_og_tags' => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['article']),
            'onboarding_enabled' => '0',
        ];

        foreach (SchemaTemplates::templates() as $template => $schema) {
            $defaults["template_{$template}_schema"] = SchemaTemplates::encode($schema);
        }

        foreach ($defaults as $key => $value) {
            DB::table('site_settings')
                ->where('key', $key)
                ->where(function ($query): void {
                    $query->whereRaw('LOWER(value) LIKE ?', ['%intraguard%'])
                        ->orWhereRaw('LOWER(value) LIKE ?', ['%p01-api.sntserver.cloud%'])
                        ->orWhereRaw('LOWER(value) LIKE ?', ['%project01%']);
                })
                ->update(['value' => $value, 'updated_at' => now()]);
        }

        DB::table('site_settings')
            ->where(function ($query): void {
                $query->whereRaw('LOWER(value) LIKE ?', ['%intraguard%'])
                    ->orWhereRaw('LOWER(value) LIKE ?', ['%p01-api.sntserver.cloud%'])
                    ->orWhereRaw('LOWER(value) LIKE ?', ['%project01%']);
            })
            ->orderBy('id')
            ->chunkById(100, function ($settings): void {
                foreach ($settings as $setting) {
                    DB::table('site_settings')
                        ->where('id', $setting->id)
                        ->update([
                            'value' => $this->genericBridgewayReplacement((string) $setting->value),
                            'updated_at' => now(),
                        ]);
                }
            });
    }

    private function removeUnusedLegacyOnboardingTemplate(): void
    {
        if (! Schema::hasTable('onboarding_form_configs') || ! Schema::hasTable('onboarding_submissions')) {
            return;
        }

        DB::table('onboarding_form_configs')
            ->whereRaw('LOWER(name) LIKE ?', ['%intraguard%'])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('onboarding_submissions')
                    ->whereColumn('onboarding_submissions.form_config_id', 'onboarding_form_configs.id');
            })
            ->delete();
    }

    private function genericBridgewayReplacement(string $value): string
    {
        return str_replace(
            [
                'IntraGuard Limited',
                'Intraguard Limited',
                'IntraGuard',
                'Intraguard',
                'intraguard',
                'https://p01-api.sntserver.cloud',
                'http://p01-api.sntserver.cloud',
                'project01',
            ],
            [
                'Bridgeway Digital',
                'Bridgeway Digital',
                'Bridgeway Digital',
                'Bridgeway Digital',
                'bridgewaydigital',
                'https://bridgewaydigital.com',
                'https://bridgewaydigital.com',
                'bridgewaydigital',
            ],
            $value
        );
    }
};
