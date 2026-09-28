<?php

namespace App\Support;

/** Explicit frontend routes. CMS records cannot claim these URLs. */
class StaticPageOwnership
{
    public const SLUGS = [
        'home', 'about-us', 'portfolio', 'digital-marketing-services',
        'international-seo-services', 'local-seo-services', 'technical-seo-agency',
        'video-animation-services', 'privacy-policy', 'terms-and-conditions',
    ];

    /** Only these parts of otherwise static pages are editor-managed. */
    public static function dynamicFields(string $type, array $data): array
    {
        $keys = match ($type) {
            'heroSection' => ['emailPlaceholder', 'submitText'],
            'serviceHero' => ['form'],
            'sertHero' => ['formContent'],
            'serContactForm', 'homeBlog' => array_keys($data),
            default => [],
        };

        return array_intersect_key($data, array_flip($keys));
    }
}
