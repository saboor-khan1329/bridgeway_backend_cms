<?php

namespace App\Http\Controllers\Api\Frontend;

use App\Models\Redirect;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RedirectLookupController extends BaseFrontendController
{
    /**
     * GET /api/frontend/redirect?path=/old-path
     */
    public function show(Request $request): JsonResponse
    {
        $path = $this->normalizePath((string) $request->query('path', ''));

        if ($path === '/') {
            return $this->success(['redirect' => null]);
        }

        $redirect = Redirect::query()
            ->active()
            ->where('from_url', $path)
            ->orderByDesc('id')
            ->first();

        if (! $redirect) {
            return $this->success(['redirect' => null]);
        }

        return $this->success([
            'redirect' => [
                'from_url' => $redirect->from_url,
                'to_url' => $redirect->to_url,
                'status_code' => $redirect->status_code,
            ],
        ]);
    }

    private function normalizePath(string $path): string
    {
        $parsed = parse_url(trim($path), PHP_URL_PATH);
        $path = '/'.ltrim((string) $parsed, '/');

        return rtrim($path, '/') ?: '/';
    }
}
