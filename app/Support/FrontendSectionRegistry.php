<?php

namespace App\Support;

/**
 * The section components the Next.js frontend can render.
 *
 * This mirrors the frontend's own registry at
 * `frontend/src/components/page-template/sectionRegistry.js`, which maps each
 * of these keys to a React component. A section whose type is absent from both
 * is dead weight: the frontend logs a warning and renders nothing for it.
 *
 * Keeping the list here lets the API drop unrenderable sections before they
 * reach the browser, and gives the admin layer a fixed set to validate a
 * section's `type` against. The two lists are matched by a test
 * (FrontendSectionRegistryTest), so adding a component to one and forgetting
 * the other fails the suite rather than silently shipping a blank section.
 *
 * The names are the frontend's, in the frontend's camelCase, on purpose — a
 * translation table between two spellings of the same fixed list would be one
 * more thing to keep in step for no gain.
 */
class FrontendSectionRegistry
{
    /**
     * Every renderable section type.
     *
     * Ordered as the frontend registry orders them (alphabetically) so the two
     * files can be diffed against each other directly.
     */
    public const TYPES = [
        'aboutCta',
        'aboutHero',
        'aboutLogosSlider',
        'aboutOurValue',
        'aboutSolution',
        'aboutWhyChoose',
        'award',
        'benefits',
        'blogsHero',
        'businessGrowth',
        'contactHero',
        'cta',
        'ctaOne',
        'developmentCost',
        'experience',
        'expertTeam',
        'featureComparison',
        'floatingCtas',
        'goals',
        'heroSection',
        'homeBlog',
        'madeTheChoice',
        'marketing',
        'ourHistory',
        'partnerLogo',
        'performCard',
        'policyContent',
        'portHero',
        'portTabs',
        'realWorldTicker',
        'revenueCalculate',
        'serCall',
        'serContactForm',
        'serCta',
        'serCtaThree',
        'serCtaTwo',
        'serFaq',
        'serLogo',
        'serPrice',
        'serTable',
        'serWhyBusiness',
        'serviceHero',
        'sertCard',
        'sertContent',
        'sertCta',
        'sertCtaThree',
        'sertCtaTwo',
        'sertHero',
        'sertPackages',
        'sertPpcAccordion',
        'sertSlider',
        'sertSolutions',
        'sertTicker',
        'sertTwoColumn',
        'subService',
        'successGraph',
        'tableOfContent',
        'technologies',
        'termsContent',
        'vaCard',
        'vaHero',
        'vaMedia',
        'vaProcess',
        'vaWork',
        'weBelieve',
        'whyBusinesses',
        'whyTrust',
        'yourTeam',
    ];

    public static function supports(?string $type): bool
    {
        return $type !== null && in_array($type, self::TYPES, true);
    }

    /** For `Rule::in()` in the admin validation layer. */
    public static function types(): array
    {
        return self::TYPES;
    }
}
