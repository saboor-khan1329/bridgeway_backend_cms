<?php

namespace App\Support;

use App\Models\SiteSetting;

class SeoDefaults
{
    public static function for(string $template, ?array $seo, array $context = []): array
    {
        $settings = SiteSetting::allCached();
        $template = self::normalizeTemplate($template);
        $title = self::applyContext(self::firstFilled([
            data_get($seo, 'meta_title'),
            self::setting($settings, "template_{$template}_meta_title"),
            self::setting($settings, 'default_meta_title'),
            data_get($context, 'title'),
        ]), $context);
        $description = self::applyContext(self::firstFilled([
            data_get($seo, 'meta_description'),
            self::setting($settings, "template_{$template}_meta_description"),
            self::setting($settings, 'default_meta_description'),
            data_get($context, 'description'),
        ]), $context);
        // Resolve schema and remember WHICH cascade level supplied it, so the
        // client can prefer its rich built-in builders over the generic
        // sitewide fallback while always honouring page/template overrides.
        [$schema, $schemaSource] = self::firstFilledWithSource([
            'page'     => data_get($seo, 'schema'),
            'template' => self::setting($settings, "template_{$template}_schema"),
            'sitewide' => self::setting($settings, 'default_schema'),
        ]);
        // schema + og_tags are JSON strings — substitute placeholders with
        // JSON-escaped values so a quote/backslash/newline in a title or
        // description can never break the surrounding JSON-LD.
        $schema = self::applyContextJson($schema, $context);
        $ogTags = self::applyContextJson(self::firstFilled([
            data_get($seo, 'og_tags'),
            self::setting($settings, "template_{$template}_og_tags"),
            self::setting($settings, 'default_og_tags'),
        ]), $context);
        $robotsIndex = self::firstFilled([
            data_get($seo, 'robots.index'),
            self::setting($settings, "template_{$template}_robots_index"),
            self::setting($settings, 'default_robots_index'),
            'index',
        ]);
        $robotsFollow = self::firstFilled([
            data_get($seo, 'robots.follow'),
            self::setting($settings, "template_{$template}_robots_follow"),
            self::setting($settings, 'default_robots_follow'),
            'follow',
        ]);
        $enableSchema = (bool) data_get($seo, 'enable_schema', true);

        if (data_get($seo, 'schema') === null && $schema !== null) {
            $enableSchema = filter_var(
                self::setting($settings, "template_{$template}_enable_schema", self::setting($settings, 'default_enable_schema', '1')),
                FILTER_VALIDATE_BOOLEAN
            );
        }

        return [
            'meta_title' => HtmlCleaner::plainText($title),
            'meta_description' => HtmlCleaner::plainText($description),
            'seo_content' => data_get($seo, 'seo_content'),
            'schema' => self::decodeJsonIfPossible($schema),
            'schema_source' => $schemaSource,
            'microdata' => data_get($seo, 'microdata'),
            'enable_schema' => $enableSchema,
            'enable_microdata' => (bool) data_get($seo, 'enable_microdata', false),
            'robots' => [
                'index' => in_array($robotsIndex, ['index', 'noindex'], true) ? $robotsIndex : 'index',
                'follow' => in_array($robotsFollow, ['follow', 'nofollow'], true) ? $robotsFollow : 'follow',
            ],
            'og_tags' => $ogTags,
        ];
    }

    protected static function normalizeTemplate(string $template): string
    {
        return str_replace('-', '_', trim($template));
    }

    protected static function setting(array $settings, string $key, mixed $default = null): mixed
    {
        return $settings[$key] ?? $default;
    }

    /** Absolute frontend URL for a path — for {url} placeholder contexts. */
    public static function urlFor(?string $path): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');

        return $base.('/'.ltrim((string) $path, '/'));
    }

    /** Like firstFilled, but returns [value, sourceKey]. */
    protected static function firstFilledWithSource(array $values): array
    {
        foreach ($values as $source => $value) {
            if (is_string($value) && trim($value) !== '') {
                return [$value, $source];
            }

            if (is_array($value) && $value !== []) {
                return [$value, $source];
            }
        }

        return [null, null];
    }

    protected static function firstFilled(array $values): mixed
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return $value;
            }

            if (is_array($value) && $value !== []) {
                return $value;
            }
        }

        return null;
    }

    public static function applyContext(mixed $value, array $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $replacements = collect($context)
            ->mapWithKeys(fn ($contextValue, string $key) => [
                '{'.$key.'}' => is_scalar($contextValue) ? (string) $contextValue : '',
            ])
            ->all();

        return strtr($value, $replacements);
    }

    /**
     * Like applyContext, but JSON-escapes each replacement so values containing
     * quotes/backslashes/newlines stay valid inside a JSON string literal.
     * Used for schema/og_tags (JSON strings) only — not plain meta text.
     */
    public static function applyContextJson(mixed $value, array $context): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $replacements = collect($context)
            ->mapWithKeys(function ($contextValue, string $key) {
                $string = is_scalar($contextValue) ? (string) $contextValue : '';
                // json_encode wraps the string in quotes and escapes its
                // contents; strip exactly the wrapping quotes to get an
                // embeddable, already-escaped fragment.
                $encoded = json_encode($string, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

                return ['{'.$key.'}' => substr($encoded, 1, -1)];
            })
            ->all();

        return strtr($value, $replacements);
    }

    protected static function decodeJsonIfPossible(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $decoded = json_decode($value, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
    }
}
