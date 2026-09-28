<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Page;
use App\Models\Service;
use App\Models\Location;
use Illuminate\Database\Eloquent\Model;

class FrontendPath
{
    public static function forModel(?Model $model): ?string
    {
        if (! $model) {
            return null;
        }

        return self::forModelSlug($model, (string) data_get($model, 'slug.slug', ''));
    }

    public static function forModelSlug(?Model $model, string $slug): ?string
    {
        if (! $model) {
            return null;
        }

        $slug = trim($slug);

        if ($slug === '') {
            return null;
        }

        return match (true) {
            $model instanceof Page => $model->page_type === 'home' ? '/' : '/'.$slug,
            $model instanceof Service => self::forService($model, $slug),
            $model instanceof Location => '/locations/'.$slug,
            $model instanceof Blog => '/blogs/'.$slug,
            $model instanceof Category => self::forCategory($model, $slug),
            default => null,
        };
    }

    protected static function forCategory(Category $category, string $slug): ?string
    {
        return match ($category->type) {
            'service' => '/'.$slug,
            'location' => '/locations/category/'.$slug,
            'blog' => '/blogs?tag='.$slug,
            default => null,
        };
    }

    protected static function forService(Service $service, string $slug): string
    {
        $category = $service->categories()
            ->where('type', 'service')
            ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES)
            ->with('slug')
            ->first();

        if ($category?->category_type === 'sector') {
            return '/sectors/'.$slug;
        }

        return $category?->slug?->slug
            ? '/'.$category->slug->slug.'/'.$slug
            : '/services/'.$slug;
    }
}
