<?php

namespace App\Console\Commands;

use App\Models\SiteSetting;
use App\Support\SchemaTemplates;
use Illuminate\Console\Command;

/**
 * Force-syncs the SEO Configurator's schema/Open-Graph template settings from
 * the code source of truth (SchemaTemplates) into the DB, then bumps the
 * frontend cache so the new JSON-LD goes live.
 *
 * Unlike SiteSettingSeeder (non-destructive, fill-if-empty), this command
 * intentionally OVERWRITES the seo template keys so the audit's fresh schema +
 * OG (Organization/Service/Local/Blog @graph, FB + LinkedIn sameAs) replace the
 * older seeded values. Run it once after deploying the SchemaTemplates change.
 */
class SeoSyncTemplates extends Command
{
    protected $signature = 'seo:sync-templates {--force : Skip the confirmation prompt}';

    protected $description = 'Overwrite the SEO Configurator schema/OG templates from SchemaTemplates and bump the frontend cache.';

    public function handle(): int
    {
        if (! $this->option('force') && ! $this->confirm('Overwrite the SEO Configurator schema/OG templates with the fresh values from SchemaTemplates?', true)) {
            $this->warn('Aborted — no changes made.');

            return self::SUCCESS;
        }

        $values = [
            'default_schema'         => SchemaTemplates::encode(SchemaTemplates::sitewide()),
            'default_og_tags'        => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['website']),
            'template_blog_og_tags'  => SchemaTemplates::encode(SchemaTemplates::ogTemplates()['article']),
            // No 'static' template — static pages (incl. home) fall through to the
            // sitewide Organization + WebSite graph.
            'template_static_schema' => null,
        ];

        foreach (SchemaTemplates::templates() as $template => $schema) {
            $values["template_{$template}_schema"] = SchemaTemplates::encode($schema);
        }

        // bulkPut writes all keys, forgets the settings cache and bumps the
        // FrontendCache version (which also pings the frontend revalidate webhook).
        SiteSetting::bulkPut($values, 'seo');

        foreach (array_keys($values) as $key) {
            $this->line('  updated '.$key.($values[$key] === null ? ' (cleared)' : ''));
        }

        $this->info('SEO templates synced from SchemaTemplates and frontend cache bumped.');

        return self::SUCCESS;
    }
}
