<?php

namespace App\Helpers;

use Closure;

class SeoHelper
{
    /* ===============================
     | VALIDATION RULES
     =============================== */
    public static function rules(): array
    {
        return [
            'meta_title'        => 'nullable|string|max:255',
            'meta_description'  => 'nullable|string',
            'seo_content'       => 'nullable|string',
            'schema'            => ['nullable', 'string', self::jsonRule('Schema (JSON-LD)')],

            // NEW FIELDS
            'microdata'         => 'nullable|string',
            'enable_schema'     => 'nullable|boolean',
            'enable_microdata'  => 'nullable|boolean',

            'robots_index'      => 'nullable|in:index,noindex',
            'robots_follow'     => 'nullable|in:follow,nofollow',

            'og_tags'           => ['nullable', 'string', self::jsonRule('Open Graph JSON')],
            'canonical_url'      => 'nullable|string|max:500',
            'keywords'           => 'nullable|string|max:2000',
            'og_title'           => 'nullable|string|max:255',
            'og_description'     => 'nullable|string|max:1000',
            'og_type'            => 'nullable|in:website,article',
            'og_url'             => 'nullable|string|max:500',
            'og_image'           => 'nullable|string|max:1000',
            'twitter_card'       => 'nullable|in:summary,summary_large_image',
            'twitter_title'      => 'nullable|string|max:255',
            'twitter_description'=> 'nullable|string|max:1000',
            'twitter_image'      => 'nullable|string|max:1000',
        ];
    }

    /**
     * Validates a field as JSON when filled — broken schema/OG JSON can never
     * silently reach the frontend (it would be dropped at render time anyway).
     */
    protected static function jsonRule(string $label): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($label): void {
            if (! is_string($value) || trim($value) === '') {
                return;
            }

            json_decode($value, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $fail("{$label} must be valid JSON: ".json_last_error_msg());
            }
        };
    }

    /* ===============================
     | FORMAT DATA FOR SERVICE
     =============================== */
    public static function data(array $validated): array
    {
        $data = [
            'meta_title'        => $validated['meta_title'] ?? null,
            'meta_description'  => $validated['meta_description'] ?? null,
            'seo_content'       => $validated['seo_content'] ?? null,
            'microdata'         => $validated['microdata'] ?? null,
            'enable_schema'     => $validated['enable_schema'] ?? true,
            'enable_microdata'  => $validated['enable_microdata'] ?? false,
            'robots_index'      => $validated['robots_index'] ?? 'index',
            'robots_follow'     => $validated['robots_follow'] ?? 'follow',
            'canonical_url'     => $validated['canonical_url'] ?? null,
            'keywords'          => $validated['keywords'] ?? null,
            'og_title'          => $validated['og_title'] ?? null,
            'og_description'    => $validated['og_description'] ?? null,
            'og_type'           => $validated['og_type'] ?? null,
            'og_url'            => $validated['og_url'] ?? null,
            'og_image'          => $validated['og_image'] ?? null,
            'twitter_card'      => $validated['twitter_card'] ?? null,
            'twitter_title'     => $validated['twitter_title'] ?? null,
            'twitter_description' => $validated['twitter_description'] ?? null,
            'twitter_image'     => $validated['twitter_image'] ?? null,
        ];

        // Kept for legacy models whose existing admin forms still expose them.
        // The BridgeWay page and blog editors use the structured fields below.
        foreach (['schema', 'og_tags'] as $legacyField) {
            if (array_key_exists($legacyField, $validated)) {
                $data[$legacyField] = $validated[$legacyField];
            }
        }

        return $data;
    }

    /* ===============================
     | FORM CONFIG (ADMIN UI)
     =============================== */
    public static function form(): array
    {
        return [

            [['type' => 'text', 'label' => 'Meta Title', 'name' => 'meta_title', 'col' => 12]],

            [['type' => 'textarea', 'label' => 'Meta Description', 'name' => 'meta_description', 'editor' => false, 'col' => 12]],

            [['type' => 'text', 'label' => 'Canonical URL', 'name' => 'canonical_url', 'col' => 12]],

            [['type' => 'textarea', 'label' => 'Keywords', 'name' => 'keywords', 'editor' => false, 'col' => 12]],

            [
                [
                    'type' => 'select',
                    'label' => 'Enable Schema',
                    'name' => 'enable_schema',
                    'options' => ['1' => 'Yes', '0' => 'No'],
                    'col' => 6,
                ],
                ['type' => 'select', 'label' => 'Open Graph Type', 'name' => 'og_type', 'options' => ['website' => 'Website', 'article' => 'Article'], 'col' => 6],
            ],

            [
                [
                    'type' => 'select',
                    'label' => 'Robots Index',
                    'name' => 'robots_index',
                    'options' => ['index' => 'Index', 'noindex' => 'No Index'],
                    'col' => 6,
                ],
                [
                    'type' => 'select',
                    'label' => 'Robots Follow',
                    'name' => 'robots_follow',
                    'options' => ['follow' => 'Follow', 'nofollow' => 'No Follow'],
                    'col' => 6,
                ],
            ],

            [['type' => 'text', 'label' => 'Open Graph Title', 'name' => 'og_title', 'col' => 12]],
            [['type' => 'textarea', 'label' => 'Open Graph Description', 'name' => 'og_description', 'editor' => false, 'col' => 12]],
            [['type' => 'text', 'label' => 'Open Graph URL', 'name' => 'og_url', 'col' => 6], ['type' => 'text', 'label' => 'Open Graph Image URL', 'name' => 'og_image', 'col' => 6]],
            [['type' => 'select', 'label' => 'Twitter Card', 'name' => 'twitter_card', 'options' => ['summary_large_image' => 'Large image', 'summary' => 'Summary'], 'col' => 6], ['type' => 'text', 'label' => 'Twitter Title', 'name' => 'twitter_title', 'col' => 6]],
            [['type' => 'textarea', 'label' => 'Twitter Description', 'name' => 'twitter_description', 'editor' => false, 'col' => 12]],
            [['type' => 'text', 'label' => 'Twitter Image URL', 'name' => 'twitter_image', 'col' => 12]],
        ];
    }
}
