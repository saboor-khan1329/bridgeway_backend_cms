<?php

namespace App\Support;

/**
 * Single source of truth for JSON-LD schema and Open Graph templates.
 *
 * Consumed by:
 *  - SiteSettingSeeder           → seeds SEO Configurator template defaults
 *  - SeoConfiguratorFields       → presets in the configurator UI
 *  - admin/shared/seo-toolkit    → per-page schema/OG builder in every seoable form
 *
 * Placeholders are resolved server-side by SeoDefaults::applyContext() with
 * the per-page context each frontend controller provides:
 * {title} {description} {url} {image} {author} {published_at} {modified_at} {category}
 */
class SchemaTemplates
{
    public const PLACEHOLDERS = [
        '{title}', '{description}', '{url}', '{image}',
        '{author}', '{published_at}', '{modified_at}', '{category}',
    ];

    public static function siteUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/');
    }

    /** Public organisation/site name — sourced from the admin Site Settings. */
    public static function orgName(): string
    {
        return (string) (\App\Models\SiteSetting::allCached()['site_name'] ?? 'BridgeWay Digital');
    }

    /** Public social profiles published by Site Settings. */
    public static function socialProfiles(): array
    {
        $settings = \App\Models\SiteSetting::allCached();

        return array_values(array_filter([
            $settings['social_facebook'] ?? null,
            $settings['social_linkedin'] ?? null,
            $settings['social_twitter'] ?? null,
            $settings['social_instagram'] ?? null,
        ]));
    }

    /**
     * Sitewide Organization + WebSite graph (rendered on the homepage and static
     * pages; service/location/blog pages reference the Organization by @id).
     *
     * Built from admin-managed data only — name + Companies House number come
     * from Site Settings, socials are the two published profiles. Fields the
     * audit report listed but that have no admin source (founder, foundingDate,
     * postal address, phone, accreditations, knowsAbout) are intentionally
     * omitted rather than hardcoded. The admin can extend this in the SEO
     * Configurator's visual builder afterwards — this is just the seed value.
     */
    public static function sitewide(): array
    {
        $site  = self::siteUrl();
        $name  = self::orgName();

        $organization = [
            '@type' => 'Organization',
            '@id' => $site.'/#organization',
            'name' => $name,
            'url' => $site.'/',
            'logo' => [
                '@type' => 'ImageObject',
                '@id' => $site.'/#logo',
                'url' => $site.'/images/logo.svg',
                'contentUrl' => $site.'/images/logo.svg',
                'caption' => $name,
            ],
            'image' => ['@id' => $site.'/#logo'],
        ];

        $organization['areaServed'] = [
            ['@type' => 'Country', 'name' => 'United States'],
            ['@type' => 'Country', 'name' => 'United Kingdom'],
            ['@type' => 'Country', 'name' => 'United Arab Emirates'],
            ['@type' => 'Country', 'name' => 'Netherlands'],
        ];
        $organization['sameAs'] = self::socialProfiles();

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $organization,
                [
                    '@type' => 'WebSite',
                    '@id' => $site.'/#website',
                    'url' => $site.'/',
                    'name' => $name,
                    'publisher' => ['@id' => $site.'/#organization'],
                    'inLanguage' => 'en',
                ],
            ],
        ];
    }

    /**
     * Per-template JSON-LD bodies, keyed by SEO Configurator template name.
     *
     * service / sector / location / blog mirror the audit report's @graph
     * templates. Placeholders ({title} {description} {url} {image} {author}
     * {published_at} {modified_at} {category}) are resolved server-side per page
     * by SeoDefaults::applyContext. There is intentionally no 'static' template
     * so static pages (incl. the homepage) fall through to the sitewide
     * Organization + WebSite graph.
     */
    public static function templates(): array
    {
        $site = self::siteUrl();
        $org  = ['@id' => $site.'/#organization'];
        $web  = ['@id' => $site.'/#website'];

        return [
            'amazon_service' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Service',
                        '@id' => '{url}#service',
                        'name' => '{title}',
                        'serviceType' => 'Amazon Marketplace Management & Growth Services',
                        'url' => '{url}',
                        'description' => '{description}',
                        'image' => '{image}',
                        'provider' => $org,
                        'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Global'],
                        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Amazon Sellers & Brands'],
                        'category' => 'Amazon E-Commerce Services',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'mainEntity' => ['@id' => '{url}#service'],
                        'about' => ['@id' => '{url}#service'],
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'static' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'blog' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'BlogPosting',
                        '@id' => '{url}#blogposting',
                        'url' => '{url}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'image' => '{image}',
                        'author' => ['@type' => 'Person', 'name' => '{author}'],
                        'datePublished' => '{published_at}',
                        'dateModified' => '{modified_at}',
                        'articleSection' => '{category}',
                        'publisher' => $org,
                        'mainEntityOfPage' => ['@id' => '{url}#webpage'],
                        'inLanguage' => 'en',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'mainEntity' => ['@id' => '{url}#blogposting'],
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'service' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Service',
                        '@id' => '{url}#service',
                        'name' => '{title}',
                        'serviceType' => '{title}',
                        'url' => '{url}',
                        'description' => '{description}',
                        'image' => '{image}',
                        'provider' => $org,
                        'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Global'],
                        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Businesses'],
                        'category' => '{category}',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'mainEntity' => ['@id' => '{url}#service'],
                        'about' => ['@id' => '{url}#service'],
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'sector' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Service',
                        '@id' => '{url}#service',
                        'name' => '{title}',
                        'serviceType' => '{title}',
                        'url' => '{url}',
                        'description' => '{description}',
                        'image' => '{image}',
                        'provider' => $org,
                        'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Global'],
                        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Businesses'],
                        'category' => '{category}',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'mainEntity' => ['@id' => '{url}#service'],
                        'about' => ['@id' => '{url}#service'],
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'location' => [
                '@context' => 'https://schema.org',
                '@graph' => [
                    [
                        '@type' => 'Service',
                        '@id' => '{url}#service',
                        'name' => '{title}',
                        'serviceType' => '{title}',
                        'url' => '{url}',
                        'description' => '{description}',
                        'image' => '{image}',
                        'provider' => $org,
                        'areaServed' => ['@type' => 'City', 'name' => '{title}'],
                        'audience' => ['@type' => 'BusinessAudience', 'audienceType' => 'Businesses'],
                        'category' => 'Digital growth services',
                    ],
                    [
                        '@type' => 'WebPage',
                        '@id' => '{url}#webpage',
                        'url' => '{url}',
                        'name' => '{title}',
                        'headline' => '{title}',
                        'description' => '{description}',
                        'isPartOf' => $web,
                        'mainEntity' => ['@id' => '{url}#service'],
                        'about' => ['@id' => '{url}#service'],
                        'publisher' => $org,
                        'inLanguage' => 'en',
                    ],
                ],
            ],
            'category' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'isPartOf' => $web,
                'inLanguage' => 'en',
            ],
            'locations' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'isPartOf' => $web,
                'inLanguage' => 'en',
            ],
            'blogs' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                'name' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'isPartOf' => $web,
                'inLanguage' => 'en',
            ],
        ];
    }

    /**
     * Extra builder-only schema types for the per-page toolkit "Insert template"
     * picker. These are scaffolds the editor inserts and then shapes with the
     * loop/array visual builder — they are NOT seeded. Every array-typed
     * property (mainEntity, itemListElement, hasCredential, sameAs, knowsAbout,
     * @graph) ships with one or two example items so the "+ Add item" loop has
     * an obvious shape to clone.
     */
    public static function builderExtras(): array
    {
        $site = self::siteUrl();
        $name = self::orgName();
        $org  = ['@id' => $site.'/#organization'];
        $web  = ['@id' => $site.'/#website'];

        return [
            'faq_page' => [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => [
                    ['@type' => 'Question', 'name' => 'Question?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Answer.']],
                    ['@type' => 'Question', 'name' => 'Another question?', 'acceptedAnswer' => ['@type' => 'Answer', 'text' => 'Another answer.']],
                ],
            ],
            'breadcrumbs' => [
                '@context' => 'https://schema.org',
                '@type' => 'BreadcrumbList',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => $site.'/'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => '{title}', 'item' => '{url}'],
                ],
            ],
            'item_list' => [
                '@context' => 'https://schema.org',
                '@type' => 'ItemList',
                'name' => '{title}',
                'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => 'First item', 'url' => '{url}'],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => 'Second item', 'url' => '{url}'],
                ],
            ],
            'article' => [
                '@context' => 'https://schema.org',
                '@type' => 'Article',
                '@id' => '{url}#article',
                'headline' => '{title}',
                'description' => '{description}',
                'image' => '{image}',
                'url' => '{url}',
                'author' => ['@type' => 'Person', 'name' => '{author}'],
                'publisher' => $org,
                'datePublished' => '{published_at}',
                'dateModified' => '{modified_at}',
                'articleSection' => '{category}',
                'mainEntityOfPage' => ['@id' => '{url}#webpage'],
                'inLanguage' => 'en',
            ],
            'collection_page' => [
                '@context' => 'https://schema.org',
                '@type' => 'CollectionPage',
                '@id' => '{url}#webpage',
                'name' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'isPartOf' => $web,
                'inLanguage' => 'en',
            ],
            'local_business' => [
                '@context' => 'https://schema.org',
                '@type' => 'LocalBusiness',
                '@id' => $site.'/#localbusiness',
                'name' => $name,
                'url' => $site.'/',
                'image' => $site.'/images/logo.svg',
                'telephone' => '',
                'email' => '',
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '',
                    'addressLocality' => '',
                    'addressRegion' => '',
                    'postalCode' => '',
                    'addressCountry' => '',
                ],
                'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Global'],
                'sameAs' => self::socialProfiles(),
            ],
            'organization_full' => [
                '@context' => 'https://schema.org',
                '@type' => 'Organization',
                '@id' => $site.'/#organization',
                'name' => $name,
                'legalName' => '',
                'alternateName' => [$name],
                'url' => $site.'/',
                'logo' => [
                    '@type' => 'ImageObject',
                    '@id' => $site.'/#logo',
                    'url' => $site.'/images/logo.svg',
                    'contentUrl' => $site.'/images/logo.svg',
                    'caption' => $name,
                ],
                'image' => ['@id' => $site.'/#logo'],
                'foundingDate' => '',
                'founder' => ['@type' => 'Person', 'name' => ''],
                'address' => [
                    '@type' => 'PostalAddress',
                    'streetAddress' => '',
                    'addressLocality' => '',
                    'addressRegion' => '',
                    'postalCode' => '',
                    'addressCountry' => '',
                ],
                'email' => '',
                'telephone' => '',
                'areaServed' => ['@type' => 'AdministrativeArea', 'name' => 'Global'],
                'sameAs' => self::socialProfiles(),
                'hasCredential' => [
                    ['@type' => 'EducationalOccupationalCredential', 'name' => 'Professional certification'],
                ],
                'knowsAbout' => [
                    'Digital marketing',
                    'Amazon marketplace growth',
                    'Web development',
                ],
            ],
        ];
    }

    /** Open Graph JSON bodies. site_name comes from admin Site Settings. */
    public static function ogTemplates(): array
    {
        $name = self::orgName();

        return [
            'website' => [
                'site_name' => $name,
                'type' => 'website',
                'title' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'image' => '{image}',
            ],
            'article' => [
                'site_name' => $name,
                'type' => 'article',
                'title' => '{title}',
                'description' => '{description}',
                'url' => '{url}',
                'image' => '{image}',
            ],
        ];
    }

    public static function encode(array $value): string
    {
        return json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }
}
