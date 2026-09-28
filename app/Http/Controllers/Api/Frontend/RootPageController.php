<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\ContentPage;
use App\Support\StaticPageOwnership;
use Illuminate\Http\JsonResponse;

/** Resolve existing menu root URLs without duplicating service content records. */
class RootPageController extends BaseFrontendController
{
    public function __construct(
        private ContentPageController $pages,
        private ServicePageContentController $services,
    ) {}

    public function show(string $slug): JsonResponse
    {
        if (in_array($slug, StaticPageOwnership::SLUGS, true)) return $this->notFound();
        if (ContentPage::where('is_cms_managed', true)->where('slug', $slug)->exists()) {
            // A draft root page must stay unavailable even if a service shares its slug.
            return $this->pages->show($slug);
        }
        return $this->services->show($slug);
    }

    public function slugs(): JsonResponse
    {
        $pages = $this->pages->slugs()->getData(true)['data']['items'];
        $services = $this->services->slugs()->getData(true)['data']['items'];
        $ownedRootSlugs = ContentPage::where('is_cms_managed', true)->pluck('slug')->all();
        $services = array_diff($services, $ownedRootSlugs);
        return $this->success(['items' => array_values(array_diff(array_unique(array_merge($pages, $services)), StaticPageOwnership::SLUGS))]);
    }
}
