<?php

namespace App\Support;

class SeoFormatter
{
    public static function format($seo): ?array
    {
        if (! $seo) {
            return null;
        }

        $schema = data_get($seo, 'schema');

        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            $schema = json_last_error() === JSON_ERROR_NONE ? $decoded : $schema;
        }

        return [
            'meta_title' => HtmlCleaner::plainText(data_get($seo, 'meta_title')),
            'meta_description' => HtmlCleaner::plainText(data_get($seo, 'meta_description')),
            'seo_content' => HtmlCleaner::clean(data_get($seo, 'seo_content')),
            'schema' => $schema,
            'microdata' => data_get($seo, 'microdata'),
            'enable_schema' => (bool) data_get($seo, 'enable_schema'),
            'enable_microdata' => (bool) data_get($seo, 'enable_microdata'),
            'robots' => [
                'index' => data_get($seo, 'robots_index', 'index'),
                'follow' => data_get($seo, 'robots_follow', 'follow'),
            ],
            'og_tags' => data_get($seo, 'og_tags'),
            'canonical_url' => data_get($seo, 'canonical_url'),
            'keywords' => data_get($seo, 'keywords'),
            'open_graph' => array_filter([
                'title' => data_get($seo, 'og_title'),
                'description' => data_get($seo, 'og_description'),
                'type' => data_get($seo, 'og_type'),
                'url' => data_get($seo, 'og_url'),
                'image' => data_get($seo, 'og_image'),
            ], fn ($value) => $value !== null && $value !== ''),
            'twitter' => array_filter([
                'card' => data_get($seo, 'twitter_card'),
                'title' => data_get($seo, 'twitter_title'),
                'description' => data_get($seo, 'twitter_description'),
                'image' => data_get($seo, 'twitter_image'),
            ], fn ($value) => $value !== null && $value !== ''),
        ];
    }
}
