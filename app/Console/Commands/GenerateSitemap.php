<?php

namespace App\Console\Commands;

use App\Support\WebsiteSitemap;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GenerateSitemap extends Command
{
    protected $signature = 'sitemap:generate {--output-dir= : Absolute path to output directory} {--force : Regenerate even if less than 48 hours have passed since the last run}';
    protected $description = 'Generate Bridgeway Digital XML sitemaps from CMS-managed frontend content.';

    private const MIN_INTERVAL_HOURS = 48;
    private const LAST_RUN_CACHE_KEY = 'bridgewaydigital:sitemap:last_generated_at';

    private string $siteUrl;

    public function handle(WebsiteSitemap $sitemap): int
    {
        if (! $this->option('force') && ! $this->dueToRun()) {
            $this->info('Skipped: less than '.self::MIN_INTERVAL_HOURS.' hours since the last generation. Use --force to override.');

            return self::SUCCESS;
        }

        $this->siteUrl = rtrim((string) config('frontend.urls.site', config('app.frontend_url', config('app.url'))), '/');
        $outputDir = rtrim(
            (string) ($this->option('output-dir') ?: config('frontend.sitemap.output_dir', base_path('../frontend/public'))),
            DIRECTORY_SEPARATOR.'/'
        );

        if (! is_dir($outputDir)) {
            $this->error("Output directory does not exist: {$outputDir}");

            return self::FAILURE;
        }

        $this->info("Generating sitemaps for {$this->siteUrl}");
        $this->info("Output: {$outputDir}");

        try {
            $groups = $sitemap->groups();

            foreach ($groups as $groupKey => $urls) {
                $file = $outputDir.DIRECTORY_SEPARATOR."sitemap-{$groupKey}.xml";
                file_put_contents($file, $this->buildUrlSetXml($urls));
                $this->line("  [ok] sitemap-{$groupKey}.xml (".count($urls).' URLs)');
            }

            file_put_contents(
                $outputDir.DIRECTORY_SEPARATOR.'sitemap.xml',
                $this->buildIndexXml(array_keys($groups))
            );
            $this->line('  [ok] sitemap.xml (index, '.count($groups).' child sitemaps)');

            $this->markGenerated();
            $this->info('Sitemap generation complete.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error("Sitemap generation failed: {$e->getMessage()}");
            report($e);

            return self::FAILURE;
        }
    }

    private function dueToRun(): bool
    {
        $lastRun = Cache::get(self::LAST_RUN_CACHE_KEY);

        return ! $lastRun || now()->diffInHours(\Illuminate\Support\Carbon::parse($lastRun)) >= self::MIN_INTERVAL_HOURS;
    }

    private function markGenerated(): void
    {
        Cache::put(self::LAST_RUN_CACHE_KEY, now()->toIso8601String(), now()->addDays(30));
    }

    /** @param array<int, string> $groupKeys */
    private function buildIndexXml(array $groupKeys): string
    {
        $lastmod = now()->toAtomString();
        $entries = implode("\n", array_map(function (string $key) use ($lastmod): string {
            $loc = htmlspecialchars("{$this->siteUrl}/sitemap-{$key}.xml", ENT_XML1 | ENT_QUOTES);

            return <<<XML
  <sitemap>
    <loc>{$loc}</loc>
    <lastmod>{$lastmod}</lastmod>
  </sitemap>
XML;
        }, $groupKeys));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$entries}
</sitemapindex>
XML;
    }

    /** @param array<int, array<string, mixed>> $urls */
    private function buildUrlSetXml(array $urls): string
    {
        $entries = implode("\n", array_map(fn (array $url): string => $this->urlEntry($url), $urls));

        return <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$entries}
</urlset>
XML;
    }

    /** @param array<string, mixed> $url */
    private function urlEntry(array $url): string
    {
        $loc = htmlspecialchars($this->siteUrl.$url['path'], ENT_XML1 | ENT_QUOTES);
        $lastmod = $url['lastmod'] ?: now()->toAtomString();
        $freq = $url['changefreq'] ?? 'weekly';
        $priority = $url['priority'] ?? '0.50';

        return <<<XML
  <url>
    <loc>{$loc}</loc>
    <lastmod>{$lastmod}</lastmod>
    <changefreq>{$freq}</changefreq>
    <priority>{$priority}</priority>
  </url>
XML;
    }
}
