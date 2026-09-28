<?php

namespace App\Support;

/**
 * Presents structured CMS SEO fields in the shape Next.js consumes.
 * Dynamic endpoints never inherit the legacy security-site SEO configurator;
 * legacy endpoints retain their existing, separate SEO behavior.
 */
class FrontendSeoPresenter
{
    /**
     * @param  string  $template  SeoDefaults template key ('service', 'blog', …).
     * @param  array|null  $seo  The record's own SeoMeta, via `seoApi()`.
     * @param  array  $context  Fallback title/description/url/image.
     * @param  array  $extra  Frontend-only additions, e.g. `structuredData`.
     */
    public static function present(string $template, ?array $seo, array $context, array $extra = []): array
    {
        // Dynamic pages use their structured CMS fields, not the legacy
        // security-site template/global JSON configurator.
        $resolved = [
            'meta_title' => HtmlCleaner::plainText(data_get($seo, 'meta_title') ?: ($context['title'] ?? '')),
            'meta_description' => HtmlCleaner::plainText(data_get($seo, 'meta_description') ?: ($context['description'] ?? '')),
            'robots' => (array) data_get($seo, 'robots', []),
            'enable_schema' => (bool) data_get($seo, 'enable_schema', true),
        ];

        $canonical = (string) (data_get($seo, 'canonical_url') ?: ($context['canonical'] ?? $context['url'] ?? ''));
        $image = (string) ($context['image'] ?? '');
        $storedOpenGraph = (array) data_get($seo, 'open_graph', []);
        $storedTwitter = (array) data_get($seo, 'twitter', []);

        $presented = [
            'title' => (string) ($resolved['meta_title'] ?? ($context['title'] ?? '')),
            'description' => (string) ($resolved['meta_description'] ?? ($context['description'] ?? '')),
            'canonical' => $canonical,
            'robots' => [
                // The admin stores these as the words that go in a robots tag;
                // Next.js wants booleans.
                'index' => ($resolved['robots']['index'] ?? 'index') !== 'noindex',
                'follow' => ($resolved['robots']['follow'] ?? 'follow') !== 'nofollow',
            ],
        ];

        $ogImage = (string) ($storedOpenGraph['image'] ?? $image);
        $presented['openGraph'] = array_filter([
            'title' => $storedOpenGraph['title'] ?? $presented['title'],
            'description' => $storedOpenGraph['description'] ?? $presented['description'],
            'url' => $storedOpenGraph['url'] ?? $canonical,
            'type' => $storedOpenGraph['type'] ?? ($extra['ogType'] ?? 'website'),
            'images' => $ogImage !== '' ? [$ogImage] : [],
        ], fn ($value) => $value !== '' && $value !== []);

        $twitterImage = (string) ($storedTwitter['image'] ?? $ogImage);
        $presented['twitter'] = array_filter([
            'card' => $storedTwitter['card'] ?? 'summary_large_image',
            'title' => $storedTwitter['title'] ?? $presented['title'],
            'description' => $storedTwitter['description'] ?? $presented['description'],
            'images' => $twitterImage !== '' ? [$twitterImage] : [],
        ], fn ($value) => $value !== '' && $value !== []);

        if (isset($extra['structuredData'])) {
            $presented['structuredData'] = $extra['structuredData'];
        }

        if ($resolved['enable_schema'] ?? false) {
            $schemaGraph = self::resolveSchemaGraph($template, $seo, $context, $extra);
            if (! empty($schemaGraph)) {
                $presented['schemaGraph'] = $schemaGraph;
            }
        }

        $keywords = data_get($seo, 'keywords', $extra['keywords'] ?? null);
        if ($keywords !== null && $keywords !== '' && $keywords !== []) {
            $presented['keywords'] = is_string($keywords)
                ? array_values(array_filter(array_map('trim', explode(',', $keywords))))
                : $keywords;
        }

        return $presented;
    }

    public static function resolveSchemaGraph(string $template, ?array $seo, array $context, array $extra = []): array
    {
        // 1. Page-level custom schema override
        $pageSchema = data_get($seo, 'schema');
        if (! empty($pageSchema)) {
            $graph = self::parseSchemaIntoGraph($pageSchema, $context);
            if (! empty($graph)) {
                $faqGraph = self::extractFaqGraph($extra);
                if (! empty($faqGraph) && ! self::graphContainsType($graph, 'FAQPage')) {
                    $graph = array_merge($graph, $faqGraph);
                }
                return $graph;
            }
        }

        // 2. Global / Template-level schema from SEO Configurator
        $normalizedTemplate = str_replace('-', '_', trim($template));
        $settings = \App\Models\SiteSetting::allCached();
        $templateSchema = $settings["template_{$normalizedTemplate}_schema"] ?? null;
        $sitewideSchema = $settings['default_schema'] ?? null;
        $configuratorSchema = ! empty($templateSchema) ? $templateSchema : $sitewideSchema;

        if (! empty($configuratorSchema)) {
            $graph = self::parseSchemaIntoGraph($configuratorSchema, $context);
            if (! empty($graph)) {
                $faqGraph = self::extractFaqGraph($extra);
                if (! empty($faqGraph) && ! self::graphContainsType($graph, 'FAQPage')) {
                    $graph = array_merge($graph, $faqGraph);
                }
                return $graph;
            }
        }

        // 3. Fallback to built-in generator from $extra['schemaGraph']
        if (isset($extra['schemaGraph']) && is_array($extra['schemaGraph'])) {
            return array_values($extra['schemaGraph']);
        }

        return [];
    }

    protected static function parseSchemaIntoGraph(mixed $schema, array $context = []): array
    {
        if (is_string($schema)) {
            $schema = SeoDefaults::applyContextJson($schema, $context);
            $decoded = json_decode($schema, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $schema = $decoded;
            }
        }

        if (! is_array($schema)) {
            return [];
        }

        if (isset($schema['@graph']) && is_array($schema['@graph'])) {
            return array_values($schema['@graph']);
        }

        if (array_is_list($schema)) {
            return array_values($schema);
        }

        return [$schema];
    }

    protected static function extractFaqGraph(array $extra): array
    {
        if (empty($extra['schemaGraph']) || ! is_array($extra['schemaGraph'])) {
            return [];
        }

        return array_values(array_filter($extra['schemaGraph'], function ($node) {
            return is_array($node) && ($node['@type'] ?? '') === 'FAQPage';
        }));
    }

    protected static function graphContainsType(array $graph, string $type): bool
    {
        foreach ($graph as $node) {
            if (is_array($node) && ($node['@type'] ?? '') === $type) {
                return true;
            }
        }
        return false;
    }
}
