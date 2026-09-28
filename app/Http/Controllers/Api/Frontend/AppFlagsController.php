<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;

class AppFlagsController extends BaseFrontendController
{
    /**
     * GET /api/frontend/app-flags
     *
     * Site-wide feature flags managed from Admin → Site Settings.
     * Cached with the frontend cache, invalidated automatically on save.
     */
    public function index(): JsonResponse
    {
        $data = $this->cached('app_flags', function () {
            $settings = SiteSetting::allCached();

            return [
                'onboarding_enabled' => filter_var($settings['onboarding_enabled'] ?? '0', FILTER_VALIDATE_BOOLEAN),
            ];
        });

        return $this->success($data);
    }
}
