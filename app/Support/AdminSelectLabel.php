<?php

namespace App\Support;

use App\Models\Blog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class AdminSelectLabel
{
    public static function category(?Category $category): string
    {
        if (! $category) {
            return '';
        }

        $label = trim((string) $category->name) ?: 'Category #'.$category->id;

        if ($category->type === 'service' && $category->category_type) {
            $label .= ' ('.ucfirst((string) $category->category_type).')';
        }

        return $label;
    }

    public static function categories(iterable $categories): array
    {
        return self::mapById($categories, fn (Category $category) => self::category($category));
    }

    public static function service(?Service $service): string
    {
        if (! $service) {
            return '';
        }

        $label = trim((string) $service->title) ?: 'Service #'.$service->id;
        $categories = $service->relationLoaded('categories')
            ? $service->categories
            : $service->categories()->get();
        $types = $categories
            ->where('type', 'service')
            ->pluck('category_type')
            ->filter()
            ->unique()
            ->map(fn ($type) => ucfirst((string) $type))
            ->implode(', ');

        return $types ? "{$label} [{$types}]" : $label;
    }

    public static function services(iterable $services): array
    {
        return self::mapById($services, fn (Service $service) => self::service($service));
    }

    public static function location(?Location $location): string
    {
        if (! $location) {
            return '';
        }
        return trim((string) $location->title) ?: 'Location #'.$location->id;
    }

    public static function locations(iterable $locations): array
    {
        return self::mapById($locations, fn (Location $location) => self::location($location));
    }

    public static function blog(?Blog $blog): string
    {
        if (! $blog) {
            return '';
        }
        return trim((string) $blog->title) ?: 'Blog #'.$blog->id;
    }

    public static function blogs(iterable $blogs): array
    {
        return self::mapById($blogs, fn (Blog $blog) => self::blog($blog));
    }

    public static function faq(?Faq $faq): string
    {
        if (! $faq) {
            return '';
        }
        $question = trim((string) $faq->question);
        if ($question === '') {
            return 'FAQ #'.$faq->id;
        }
        $answerSnippet = Str::limit(trim(strip_tags((string) $faq->answer)), 70);
        return $answerSnippet !== ''
            ? Str::limit($question, 70).' — '.$answerSnippet
            : Str::limit($question, 90);
    }

    public static function faqs(iterable $faqs): array
    {
        return self::mapById($faqs, fn (Faq $faq) => self::faq($faq));
    }

    public static function page(?Page $page): string
    {
        if (! $page) {
            return '';
        }
        $title = trim((string) $page->page_title) ?: 'Page #'.$page->id;
        return $title.' ['.($page->page_type ?: 'page').']';
    }

    public static function pages(iterable $pages): array
    {
        return self::mapById($pages, fn (Page $page) => self::page($page));
    }

    public static function testimonial(?Review $review): string
    {
        if (! $review) {
            return '';
        }
        $label = trim((string) $review->author_name) ?: 'Testimonial #'.$review->id;
        $snippet = Str::limit(trim(strip_tags((string) $review->content)), 60);
        return $snippet !== '' ? "{$label} — {$snippet}" : $label;
    }

    public static function testimonials(iterable $testimonials): array
    {
        return self::mapById($testimonials, fn (Review $review) => self::testimonial($review));
    }

    public static function user(?User $user): string
    {
        if (! $user) {
            return '';
        }

        return $user->publicName().' <'.$user->email.'>';
    }

    public static function users(iterable $users): array
    {
        return self::mapById($users, fn (User $user) => self::user($user));
    }

    private static function mapById(iterable $models, callable $label): array
    {
        return collect($models)
            ->filter()
            ->mapWithKeys(fn ($model) => [(string) Arr::get($model, 'id') => (string) $label($model)])
            ->toArray();
    }
}
