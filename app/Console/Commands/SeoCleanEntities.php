<?php

namespace App\Console\Commands;

use App\Models\SeoMeta;
use App\Support\FrontendCache;
use Illuminate\Console\Command;

/**
 * Removes stale / invalid per-page schema & Open-Graph from seo_metas so those
 * pages inherit the fresh SEO Configurator templates instead.
 *
 * SAFE: only clears values that are NOT valid JSON (left-over demo/junk such as
 * lorem-ipsum). Any hand-authored, valid JSON schema/OG is preserved. Updates
 * only the schema / og_tags columns — no rows are deleted.
 */
class SeoCleanEntities extends Command
{
    protected $signature = 'seo:clean-entities {--dry-run : Preview without writing}';

    protected $description = 'Clear stale/invalid (non-JSON) per-page schema & OG from seo_metas so they inherit the SEO Configurator templates.';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $cleared = 0;

        SeoMeta::query()
            ->where(function ($q) {
                $q->where(fn ($w) => $w->whereNotNull('schema')->where('schema', '!=', ''))
                  ->orWhere(fn ($w) => $w->whereNotNull('og_tags')->where('og_tags', '!=', ''));
            })
            ->chunkById(200, function ($rows) use (&$cleared, $dryRun) {
                foreach ($rows as $meta) {
                    $bad = [];
                    foreach (['schema', 'og_tags'] as $field) {
                        $value = $meta->{$field};
                        if ($value === null || trim((string) $value) === '' || is_array($value)) {
                            continue; // empty or already a valid decoded array
                        }
                        json_decode((string) $value, true);
                        if (json_last_error() !== JSON_ERROR_NONE) {
                            $bad[] = $field;
                        }
                    }

                    if ($bad === []) {
                        continue;
                    }

                    $this->line(sprintf(
                        '  seo_meta #%d (%s#%s) — clearing %s',
                        $meta->id,
                        class_basename((string) $meta->seoable_type),
                        $meta->seoable_id,
                        implode(' + ', $bad)
                    ));

                    if (! $dryRun) {
                        foreach ($bad as $field) {
                            $meta->{$field} = null;
                        }
                        $meta->saveQuietly();
                    }
                    $cleared++;
                }
            });

        if (! $dryRun && $cleared > 0) {
            FrontendCache::bump();
        }

        $this->info(($dryRun ? 'DRY RUN — ' : '')."{$cleared} row(s) ".($dryRun ? 'would be cleared.' : 'cleared.'));

        return self::SUCCESS;
    }
}
