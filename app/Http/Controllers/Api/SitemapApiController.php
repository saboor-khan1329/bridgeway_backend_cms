<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Frontend\BaseFrontendController;
use App\Support\WebsiteSitemap;
use Illuminate\Http\JsonResponse;

class SitemapApiController extends BaseFrontendController
{
    /**
     * GET /api/sitemap
     *
     * Returns all frontend URLs owned by the Bridgeway CMS/content layer.
     * Cache is version-keyed through FrontendCache and invalidated on content save.
     */
    public function index(WebsiteSitemap $sitemap): JsonResponse
    {
        $data = $this->cached('sitemap_full', fn () => [
            'items' => $sitemap->flat(),
            'groups' => $sitemap->groups(),
        ]);

        return $this->success($data);
    }
}
