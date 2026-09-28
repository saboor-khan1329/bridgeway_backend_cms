<?php

namespace App\Support;

class SeoConfiguratorFields
{
    public const TEMPLATES = [
        'amazon_service' => 'Amazon Services',
        'service' => 'Development Services',
        'static' => 'Static & Content Pages',
        'blog' => 'Blog Detail',
        'blogs' => 'Blogs Listing',
    ];

    public static function fields(): array
    {
        $fields = [
            ['key' => 'default_meta_title', 'label' => 'Fallback Meta Title', 'type' => 'text', 'section' => 'global', 'col' => 6],
            ['key' => 'default_meta_description', 'label' => 'Fallback Meta Description', 'type' => 'textarea', 'section' => 'global', 'col' => 6],
            ['key' => 'default_robots_index', 'label' => 'Robots Index', 'type' => 'select', 'section' => 'global', 'options' => ['index' => 'Index', 'noindex' => 'No Index'], 'col' => 4],
            ['key' => 'default_robots_follow', 'label' => 'Robots Follow', 'type' => 'select', 'section' => 'global', 'options' => ['follow' => 'Follow', 'nofollow' => 'No Follow'], 'col' => 4],
            ['key' => 'default_enable_schema', 'label' => 'Schema Output', 'type' => 'select', 'section' => 'global', 'options' => ['1' => 'Enabled', '0' => 'Disabled'], 'col' => 4],
            ['key' => 'default_schema', 'label' => 'Sitewide Schema JSON-LD', 'type' => 'json', 'section' => 'global', 'col' => 12, 'max' => 30000],
            ['key' => 'default_og_tags', 'label' => 'Sitewide Open Graph JSON', 'type' => 'json', 'section' => 'global', 'col' => 12, 'max' => 30000],
        ];

        foreach (self::TEMPLATES as $template => $label) {
            $fields[] = ['key' => "template_{$template}_meta_title", 'label' => "{$label} Meta Title Template", 'type' => 'text', 'section' => $template, 'col' => 6];
            $fields[] = ['key' => "template_{$template}_meta_description", 'label' => "{$label} Meta Description Template", 'type' => 'textarea', 'section' => $template, 'col' => 6];
            $fields[] = ['key' => "template_{$template}_robots_index", 'label' => "{$label} Robots Index", 'type' => 'select', 'section' => $template, 'options' => ['' => 'Inherit sitewide', 'index' => 'Index', 'noindex' => 'No Index'], 'col' => 4];
            $fields[] = ['key' => "template_{$template}_robots_follow", 'label' => "{$label} Robots Follow", 'type' => 'select', 'section' => $template, 'options' => ['' => 'Inherit sitewide', 'follow' => 'Follow', 'nofollow' => 'No Follow'], 'col' => 4];
            $fields[] = ['key' => "template_{$template}_enable_schema", 'label' => "{$label} Schema Output", 'type' => 'select', 'section' => $template, 'options' => ['' => 'Inherit sitewide', '1' => 'Enabled', '0' => 'Disabled'], 'col' => 4];
            $fields[] = ['key' => "template_{$template}_schema", 'label' => "{$label} Schema JSON-LD", 'type' => 'json', 'section' => $template, 'col' => 12, 'max' => 30000];
            $fields[] = ['key' => "template_{$template}_og_tags", 'label' => "{$label} Open Graph JSON", 'type' => 'json', 'section' => $template, 'col' => 12, 'max' => 30000];
        }

        return $fields;
    }

    public static function grouped(): array
    {
        return collect(self::fields())
            ->groupBy('section')
            ->all();
    }

    /**
     * Presets for the configurator UI — derived from SchemaTemplates so the
     * seeder, the per-page builder and these presets never drift apart.
     */
    public static function presets(): array
    {
        $presets = [
            'sitewide_org' => [
                'label' => 'Sitewide Organization + WebSite',
                'target' => 'default_schema',
                'value' => SchemaTemplates::sitewide(),
            ],
        ];

        foreach (SchemaTemplates::templates() as $template => $value) {
            $label = self::TEMPLATES[$template] ?? ucfirst($template);
            $presets["{$template}_schema"] = [
                'label' => "{$label} Schema",
                'target' => "template_{$template}_schema",
                'value' => $value,
            ];
        }

        $presets['website_og'] = [
            'label' => 'Website OG Tags',
            'target' => 'default_og_tags',
            'value' => SchemaTemplates::ogTemplates()['website'],
        ];
        $presets['article_og'] = [
            'label' => 'Article OG Tags',
            'target' => 'template_blog_og_tags',
            'value' => SchemaTemplates::ogTemplates()['article'],
        ];

        return $presets;
    }
}
