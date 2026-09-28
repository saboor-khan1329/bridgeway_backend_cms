<?php

namespace App\Console\Commands;

use App\Support\FrontendCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Replaces em dashes (—), en dashes (–) and hyphens (-) with a single space in
 * READABLE CONTENT only, so cleaned copy flows into the frontend API payloads
 * and search/AI extraction.
 *
 * SAFETY — a dash is NEVER changed when it is functional:
 *   • HTML markup      — tags / attributes / class / style / href / src are left byte-for-byte.
 *   • URLs & paths     — http(s)://…, /slug-paths, e-mails are protected inside prose and JSON.
 *   • Structured data  — JSON is cleaned IN PLACE (formatting preserved); keys and
 *                        url / image / @id / sameAs / telephone values are skipped.
 *   • Slugs & assets   — those columns are never in scope (see EXCLUDE_* below).
 *
 * Run `--dry-run` first: it reports every match with a precise changed-span diff and
 * writes nothing. The real run is chunked, transactional, idempotent, and bumps the
 * frontend cache at the end.
 */
class StripContentDashes extends Command
{
    protected $signature = 'content:strip-dashes {--dry-run : Preview matches, write nothing} {--samples=6 : Sample rows to show per column in dry-run}';

    protected $description = 'Replace em/en dashes and hyphens with a space in readable content only (never URLs, markup, slugs or asset paths).';

    /** Content tables that feed the public frontend. Nothing else is ever touched. */
    private const TABLES = [
        'blogs', 'categories', 'services', 'locations', 'pages', 'faqs', 'reviews',
        'reviewables', 'menu_items', 'menus', 'navigation_menus', 'navigation_menu_items',
        'images', 'onboarding_form_configs', 'seo_metas', 'site_settings',
    ];

    /** Column-name suffixes that mark a non-content (functional) column → skipped. */
    private const EXCLUDE_SUFFIX = ['_url', '_uri', '_link', '_src', '_path', '_id', '_at', '_type', '_key', '_group', '_token', '_slug'];

    /** Exact column names that are never content → skipped. */
    private const EXCLUDE_EXACT = [
        'id', 'slug', 'href', 'path', 'disk', 'source', 'status', 'key', 'group', 'placement',
        'location', 'target', 'uuid', 'token', 'email', 'password', 'ip', 'ip_address',
        'user_agent', 'mime_type', 'filename', 'resource', 'mode', 'columns', 'filters',
        'metadata', 'payload', 'recipients', 'reference_number', 'robots_index', 'robots_follow',
        'custom_url', 'from_url', 'to_url', 'source_url',
    ];

    /** Postgres data_types we treat as text-bearing. */
    private const TEXT_TYPES = ['character varying', 'varchar', 'text', 'character', 'bpchar', 'json', 'jsonb'];

    /** Every dash-like glyph we collapse (unicode hyphen … em dash … + ASCII hyphen). */
    private const DASH = '\x{2010}\x{2011}\x{2012}\x{2013}\x{2014}\x{2015}\-';

    /** JSON keys whose string value is a URL / id / enum / phone → never cleaned. */
    private const JSON_SKIP_KEYS = [
        'url', 'image', 'logo', 'contenturl', 'thumbnailurl', 'thumbnail', 'sameas', '@id',
        '@type', '@context', 'id', 'type', 'target', 'identifier', 'telephone', 'faxnumber',
        'sku', 'gtin', 'email', 'key', 'slug', 'href', 'src', 'inlanguage', 'encodingformat',
        'contenttype', 'currency', 'pricecurrency',
    ];

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $samples = max(0, (int) $this->option('samples'));
        $totalRows = 0;

        foreach (self::TABLES as $table) {
            $cols = $this->contentColumns($table);
            if ($cols === []) {
                continue;
            }

            $this->line("<fg=cyan>{$table}</> → ".implode(', ', $cols));

            $changedInTable = 0;
            $shown = [];

            DB::table($table)
                ->select(array_merge(['id'], $cols))
                ->orderBy('id')
                ->chunkById(200, function ($rows) use ($table, $cols, $dryRun, $samples, &$changedInTable, &$shown, &$totalRows) {
                    foreach ($rows as $row) {
                        $updates = [];
                        foreach ($cols as $col) {
                            $orig = $row->{$col};
                            if ($orig === null || $orig === '') {
                                continue;
                            }
                            $new = $this->cleanColumnValue((string) $orig);
                            if ($new !== (string) $orig) {
                                $updates[$col] = $new;
                                if ($samples > 0 && ($shown[$col] ?? 0) < $samples) {
                                    $shown[$col] = ($shown[$col] ?? 0) + 1;
                                    $this->line("    <fg=yellow>{$table}#{$row->id}.{$col}</>");
                                    $this->line('      '.$this->diff((string) $orig, $new));
                                }
                            }
                        }
                        if ($updates !== []) {
                            $changedInTable++;
                            $totalRows++;
                            if (! $dryRun) {
                                DB::table($table)->where('id', $row->id)->update($updates);
                            }
                        }
                    }
                }, 'id');

            if ($changedInTable > 0) {
                $this->line("  <fg=green>{$changedInTable}</> row(s) ".($dryRun ? 'would change.' : 'updated.'));
            }
        }

        if (! $dryRun && $totalRows > 0) {
            FrontendCache::bump();
            $this->line('Frontend cache bumped + revalidate pinged.');
        }

        $this->info(($dryRun ? 'DRY RUN — ' : '')."{$totalRows} row(s) ".($dryRun ? 'would be updated.' : 'updated.'));

        return self::SUCCESS;
    }

    /** Content columns for a table: text-bearing, minus every excluded column. */
    private function contentColumns(string $table): array
    {
        $rows = DB::select(
            'select column_name, data_type from information_schema.columns where table_schema = ? and table_name = ?',
            ['public', $table]
        );

        $out = [];
        foreach ($rows as $r) {
            if (! in_array(strtolower($r->data_type), self::TEXT_TYPES, true)) {
                continue;
            }
            if ($this->isExcludedColumn($r->column_name)) {
                continue;
            }
            $out[] = $r->column_name;
        }

        return $out;
    }

    private function isExcludedColumn(string $name): bool
    {
        $n = strtolower($name);
        if (in_array($n, self::EXCLUDE_EXACT, true)) {
            return true;
        }
        foreach (self::EXCLUDE_SUFFIX as $suffix) {
            if (str_ends_with($n, $suffix)) {
                return true;
            }
        }
        return false;
    }

    /** Route a raw column value: JSON → in-place value clean, HTML → text-nodes, else → prose. */
    private function cleanColumnValue(string $raw): string
    {
        if (! $this->hasDash($raw)) {
            return $raw;
        }

        // JSON columns: clean string VALUES in place so the exact formatting
        // (indentation, spacing, key order) is preserved — only dash characters change.
        $decoded = json_decode($raw, true);
        if (is_array($decoded) && json_last_error() === JSON_ERROR_NONE) {
            return $this->cleanJsonPreserving($raw);
        }

        return $this->cleanScalar($raw);
    }

    /**
     * Walk a raw JSON string and clean only string VALUES (never keys, never the
     * value of a technical/URL key), leaving all structural whitespace untouched.
     * A string is a key iff the next non-space char after it is ':'.
     */
    private function cleanJsonPreserving(string $raw): string
    {
        $len = strlen($raw);
        $out = '';
        $i = 0;
        $currentKey = null;

        while ($i < $len) {
            $ch = $raw[$i];
            if ($ch !== '"') {
                $out .= $ch;
                $i++;
                continue;
            }

            // Capture the full string literal (honouring \" escapes).
            $j = $i + 1;
            while ($j < $len) {
                if ($raw[$j] === '\\') { $j += 2; continue; }
                if ($raw[$j] === '"') { break; }
                $j++;
            }
            if ($j >= $len) { // malformed — bail without corrupting
                $out .= substr($raw, $i);
                break;
            }

            $inner = substr($raw, $i + 1, $j - $i - 1);

            // Look ahead: a key is immediately followed by ':'.
            $k = $j + 1;
            while ($k < $len && ctype_space($raw[$k])) { $k++; }
            $isKey = ($k < $len && $raw[$k] === ':');

            if ($isKey) {
                $currentKey = $inner;
                $out .= '"'.$inner.'"';
            } else {
                $clean = ($currentKey !== null && $this->isTechnicalJsonKey($currentKey))
                    ? $inner
                    : $this->cleanScalar($inner);
                $out .= '"'.$clean.'"';
            }
            $i = $j + 1;
        }

        return $out;
    }

    private function isTechnicalJsonKey(string $key): bool
    {
        $k = strtolower($key);
        if (in_array($k, self::JSON_SKIP_KEYS, true)) {
            return true;
        }
        foreach (['url', 'uri', 'src', 'href', 'id', 'slug', 'path'] as $needle) {
            if (str_ends_with($k, $needle)) {
                return true;
            }
        }
        return false;
    }

    /** Clean one string value: HTML-aware if it contains markup, else prose. */
    private function cleanScalar(string $v): string
    {
        if ($v === '' || ! $this->hasDash($v)) {
            return $v;
        }
        if (preg_match('/<[a-z!\/]/i', $v)) {
            return $this->cleanHtml($v);
        }
        return $this->cleanText($v);
    }

    private function hasDash(string $v): bool
    {
        return (bool) preg_match('/['.self::DASH.']|&(mdash|ndash|#8212|#8211|#x2014|#x2013|shy|#173);/iu', $v);
    }

    /**
     * Clean visible text: dash(es)+adjacent horizontal spaces → one space.
     * URLs / paths / e-mails inside the text are protected byte-for-byte.
     */
    private function cleanText(string $text): string
    {
        // Normalise dash entities to a real em dash so the collapse rule catches them.
        $text = preg_replace('/&(mdash|#8212|#x2014|ndash|#8211|#x2013);/i', "\u{2014}", $text);
        $text = preg_replace('/&(shy|#173);/i', '', $text); // soft hyphen → remove

        // Protect functional tokens (bounded so they never swallow following JSON/content).
        $protected = [];
        $text = preg_replace_callback(
            '~(?:https?://[^\s"\'<>]+|www\.[^\s"\'<>]+|/[^\s"\'<>]*|[^\s"\'<>@]+@[^\s"\'<>]+\.[^\s"\'<>]+)~u',
            function ($m) use (&$protected) {
                $token = "\x00".count($protected)."\x00";
                $protected[$token] = $m[0];
                return $token;
            },
            $text
        );

        $text = preg_replace('/\h*['.self::DASH.']+\h*/u', ' ', $text);

        if ($protected !== []) {
            $text = strtr($text, $protected);
        }

        return $text;
    }

    /**
     * Clean an HTML string: split into tags vs text, only clean the text between
     * tags, and never inside script/style/pre/code. Tags (and their href/src/
     * class/style attributes) are preserved exactly.
     */
    private function cleanHtml(string $html): string
    {
        $skipDepth = 0;

        return preg_replace_callback('/<[^>]+>|[^<]+/u', function ($m) use (&$skipDepth) {
            $seg = $m[0];

            if ($seg !== '' && $seg[0] === '<') {
                if (preg_match('/^<\s*(\/?)\s*([a-zA-Z0-9]+)/', $seg, $t)) {
                    $name = strtolower($t[2]);
                    if (in_array($name, ['script', 'style', 'pre', 'code'], true)
                        && ! str_ends_with(rtrim($seg), '/>')) {
                        $skipDepth += ($t[1] === '/') ? -1 : 1;
                        $skipDepth = max(0, $skipDepth);
                    }
                }
                return $seg; // tag verbatim
            }

            return $skipDepth > 0 ? $seg : $this->cleanText($seg);
        }, $html);
    }

    /** Show the exact changed span (red = before, green = after) with context. */
    private function diff(string $a, string $b): string
    {
        $ca = mb_str_split($a);
        $cb = mb_str_split($b);
        $na = count($ca);
        $nb = count($cb);

        $p = 0;
        while ($p < $na && $p < $nb && $ca[$p] === $cb[$p]) { $p++; }
        $sa = $na - 1;
        $sb = $nb - 1;
        while ($sa >= $p && $sb >= $p && $ca[$sa] === $cb[$sb]) { $sa--; $sb--; }

        $ctx = 32;
        $cap = fn (string $s) => mb_strlen($s) > 90 ? mb_substr($s, 0, 87).'…' : $s;
        $pre  = implode('', array_slice($ca, max(0, $p - $ctx), min($ctx, $p)));
        $post = implode('', array_slice($ca, $sa + 1, $ctx));
        $midA = $cap(implode('', array_slice($ca, $p, $sa - $p + 1)));
        $midB = $cap(implode('', array_slice($cb, $p, $sb - $p + 1)));

        return "…{$pre}<fg=red>[{$midA}]</>{$post}…\n         → …{$pre}<fg=green>[{$midB}]</>{$post}…";
    }
}
