<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Gives stored internal links a leading slash.
 *
 * A path typed as "contact-us" is browser-relative: it resolves against
 * whatever page it is rendered on, so the same stored value becomes
 * /contact-us on the home page but /locations/contact-us on a location page.
 * Content editors type the bare slug because that is the natural thing to
 * type, so the data drifts this way on its own.
 *
 * The API already repairs this on the way out (HtmlCleaner::safeUrl), so the
 * public site is correct whether or not this command has been run. This exists
 * to fix the data itself, so the admin shows what the visitor sees and nobody
 * has to wonder which layer is doing the correcting.
 *
 *   php artisan links:normalise            # report only
 *   php artisan links:normalise --force    # write the changes
 *
 * Safe to run repeatedly and safe to run on production: a value that is
 * already absolute, external, an anchor, a query, or a mailto:/tel: link is
 * left exactly as written.
 */
class NormaliseInternalLinks extends Command
{
    protected $signature = 'links:normalise {--force : Write the changes, instead of only reporting}';

    protected $description = 'Add a leading slash to stored internal link paths (dry run unless --force)';

    /**
     * Tables whose link columns point at pages on this site.
     *
     * Deliberately an allowlist. `redirects.from_url` / `to_url` carry their
     * own matching semantics, and `inquiries.source_url` records where a
     * visitor came from — neither is a link this site renders, and rewriting
     * either would change behaviour rather than fix it.
     */
    private const TABLES = ['categories', 'locations', 'navigation_menu_items', 'pages', 'services'];

    /**
     * Link columns whose names do not follow the `_button_url` / `_link`
     * convention, listed per table.
     *
     * navigation_menu_items.href holds the header and footer menu targets. Most
     * are resolved from the linked record's slug and are already rooted, but a
     * "custom" menu item stores exactly what the admin typed, which is where a
     * bare slug can enter.
     */
    private const EXTRA_COLUMNS = [
        'navigation_menu_items' => ['href'],
    ];

    public function handle(): int
    {
        $total = 0;
        $changedRows = 0;

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $extra = self::EXTRA_COLUMNS[$table] ?? [];

            $columns = collect(Schema::getColumnListing($table))
                ->filter(fn (string $column) => str_ends_with($column, '_button_url')
                    || str_ends_with($column, '_link')
                    || $column === 'section_2_button_url'
                    || in_array($column, $extra, true))
                ->values();

            if ($columns->isEmpty()) {
                continue;
            }

            foreach ($columns as $column) {
                $rows = DB::table($table)
                    ->select('id', $column)
                    ->whereNotNull($column)
                    ->where($column, '<>', '')
                    ->get();

                $updates = [];

                foreach ($rows as $row) {
                    $current = (string) $row->{$column};
                    $fixed = $this->normalise($current);

                    if ($fixed !== null && $fixed !== $current) {
                        $updates[$row->id] = [$current, $fixed];
                    }
                }

                if ($updates === []) {
                    continue;
                }

                $total += count($updates);
                $this->line('');
                $this->line("  <options=bold>{$table}.{$column}</> — ".count($updates).' row(s)');

                foreach (array_slice($updates, 0, 3, true) as $id => [$from, $to]) {
                    $this->line("    #{$id}  {$from}  ->  {$to}");
                }

                if (count($updates) > 3) {
                    $this->line('    … and '.(count($updates) - 3).' more');
                }

                if ($this->option('force')) {
                    foreach ($updates as $id => [, $to]) {
                        DB::table($table)->where('id', $id)->update([$column => $to]);
                        $changedRows++;
                    }
                }
            }
        }

        $this->line('');

        if ($total === 0) {
            $this->info('Nothing to normalise — every stored link already has a leading slash.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn("Dry run — {$total} value(s) would change. Re-run with --force to write them.");

            return self::SUCCESS;
        }

        $this->info("Updated {$changedRows} value(s).");
        $this->line('The public API repairs these on read too, so no cache clear is required.');

        return self::SUCCESS;
    }

    /**
     * The same rule HtmlCleaner::safeUrl applies, so the stored value and the
     * API's output cannot disagree.
     *
     * Returns null when the value should not be touched at all.
     */
    private function normalise(string $href): ?string
    {
        $href = trim($href);

        if ($href === '') {
            return null;
        }

        // Already rooted, an anchor, or a query — nothing to do.
        if (str_starts_with($href, '/') || str_starts_with($href, '#') || str_starts_with($href, '?')) {
            return null;
        }

        // Anything with a scheme (http, https, mailto, tel, and anything we
        // would reject) is left for safeUrl to judge on read.
        if (parse_url($href, PHP_URL_SCHEME) !== null) {
            return null;
        }

        return '/'.ltrim($href, '/');
    }
}
