<?php

use App\Models\ContentBlock;
use App\Models\Page;
use App\Models\Slug;
use Illuminate\Database\Migrations\Migration;

/**
 * Removes the IntraGuard demo pages this CMS shipped with.
 *
 * The app was reused for Bridgeway Digital, and `pages` still held eleven rows
 * of the previous site's content — "Accreditations & Certifications",
 * "Screening & Vetting Application", a locations index. None of it is reachable
 * from the Bridgeway frontend, which never calls the static-pages endpoint.
 *
 * The reason they cannot simply be left in place is `slugs.slug`, which is
 * unique across every model: those rows hold `home`, `about-us` and `blogs`,
 * three of the URLs the Bridgeway frontend's own pages need. That uniqueness is
 * doing its job — one site cannot have two definitions of /about-us — so the
 * fix is to remove the dead definition rather than route around the constraint.
 *
 * Deliberately narrow: it deletes only rows matching the known IntraGuard page
 * types, and only those carrying no sections, so a page anyone has since built
 * on is left alone. The `pages` table itself stays — Blog, Category, Faq,
 * Location, Review, Service and the navigation items all still morph to it.
 */
return new class extends Migration
{
    /**
     * The pages seeded by StaticPageSeeder, by page_type.
     */
    private const LEGACY_PAGE_TYPES = [
        'home',
        'about-us',
        'about-accreditations',
        'about-history',
        'about-values',
        'blogs',
        'company-policies',
        'contact-us',
        'locations',
        'onboarding',
        'terms-conditions',
    ];

    public function up(): void
    {
        $pages = Page::query()
            ->whereIn('page_type', self::LEGACY_PAGE_TYPES)
            ->get();

        foreach ($pages as $page) {
            $hasSections = ContentBlock::query()
                ->where('blockable_type', Page::class)
                ->where('blockable_id', $page->getKey())
                ->exists();

            if ($hasSections) {
                continue;
            }

            Slug::query()
                ->where('sluggable_type', Page::class)
                ->where('sluggable_id', $page->getKey())
                ->delete();

            $page->delete();
        }
    }

    /**
     * Not reversible. The rows were seed content, and StaticPageSeeder — the
     * thing that created them — is removed in the same change, so there is
     * nothing to restore them from and nothing that wants them back.
     */
    public function down(): void
    {
        //
    }
};
