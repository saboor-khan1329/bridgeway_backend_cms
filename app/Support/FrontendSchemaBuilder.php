<?php

namespace App\Support;

use App\Models\SiteSetting;

/** Builds JSON-LD exclusively from CMS-managed page and section fields. */
class FrontendSchemaBuilder
{
    public static function page(string $template, string $title, string $description, string $url, array $sections = [], ?string $image = null): array
    {
        $graph = [];
        $isService = in_array($template, ['amazon-service', 'service', 'service-two', 'inner-service'], true);

        if ($isService) {
            $graph[] = self::service($title, $description, $url, $image);
        } else {
            $graph[] = self::webPage($title, $description, $url, $image);
        }

        $faqs = self::faqs($sections);
        if ($faqs !== []) {
            $graph[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                '@id' => $url.'#faq',
                'mainEntity' => array_map(fn (array $faq) => [
                    '@type' => 'Question',
                    'name' => HtmlCleaner::plainText($faq['question']),
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => HtmlCleaner::plainText($faq['answer']),
                    ],
                ], $faqs),
            ];
        }

        $graph[] = self::breadcrumbs($title, $url);

        return $graph;
    }

    public static function blog(string $title, string $description, string $url, ?string $image, ?string $author, ?string $publishedAt, ?string $modifiedAt): array
    {
        $article = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'BlogPosting',
            '@id' => $url.'#article',
            'headline' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'datePublished' => $publishedAt,
            'dateModified' => $modifiedAt,
            'author' => $author ? ['@type' => 'Person', 'name' => $author] : null,
            'publisher' => self::organization(),
        ], fn ($value) => $value !== null && $value !== '');

        return [$article, self::breadcrumbs($title, $url)];
    }

    private static function service(string $title, string $description, string $url, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Service',
            '@id' => $url.'#service',
            'name' => $title,
            'serviceType' => $title,
            'description' => $description,
            'url' => $url,
            'image' => $image,
            'provider' => self::organization(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function webPage(string $title, string $description, string $url, ?string $image): array
    {
        return array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => $url.'#webpage',
            'name' => $title,
            'description' => $description,
            'url' => $url,
            'primaryImageOfPage' => $image,
            'publisher' => self::organization(),
        ], fn ($value) => $value !== null && $value !== '');
    }

    private static function breadcrumbs(string $title, string $url): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            '@id' => $url.'#breadcrumb',
            'itemListElement' => [
                ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => self::siteUrl()],
                ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $url],
            ],
        ];
    }

    private static function organization(): array
    {
        return [
            '@type' => 'Organization',
            '@id' => self::siteUrl().'#organization',
            'name' => (string) SiteSetting::get('site_name', ''),
            'url' => self::siteUrl(),
        ];
    }

    private static function siteUrl(): string
    {
        return rtrim((string) config('app.frontend_url', config('app.url')), '/').'/';
    }

    private static function faqs(array $sections): array
    {
        foreach ($sections as $section) {
            if (($section['type'] ?? null) !== 'serFaq') {
                continue;
            }

            $items = data_get($section, 'data.faqData', []);
            return collect(is_array($items) ? $items : [])
                ->filter(fn ($item) => is_array($item) && ! empty($item['question']) && ! empty($item['answer']))
                ->values()->all();
        }

        return [];
    }
}
