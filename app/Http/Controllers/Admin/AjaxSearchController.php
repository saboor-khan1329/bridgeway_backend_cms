<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Category;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Review;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AjaxSearchController extends Controller
{
    public function __invoke(Request $request, string $resource): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));
        $limit = min(50, max(5, (int) $request->query('limit', 25)));

        return response()->json([
            'results' => $this->resolve($resource, $term, $limit),
        ]);
    }

    protected function resolve(string $resource, string $term, int $limit): array
    {
        return match ($resource) {
            'faqs' => $this->results(Faq::query(), 'question', $term, $limit, function (Faq $f) {
                $question = Str::limit(trim((string) $f->question), 70);
                $answer   = Str::limit(trim(strip_tags((string) $f->answer)), 70);
                return $answer !== '' ? $question.' — '.$answer : $question;
            }),
            'services' => $this->results(Service::query()->with('categories'), 'title', $term, $limit, function (Service $service) {
                $types = $service->categories
                    ->where('type', 'service')
                    ->pluck('category_type')
                    ->filter()
                    ->unique()
                    ->map(fn ($type) => ucfirst((string) $type))
                    ->implode(', ');

                return $types ? "{$service->title} [{$types}]" : $service->title;
            }),
            'locations' => $this->results(Location::query(), 'title', $term, $limit, fn ($l) => $l->title),
            'blogs' => $this->results(Blog::query(), 'title', $term, $limit, fn ($b) => $b->title),
            'pages' => $this->results(Page::query(), 'page_title', $term, $limit, fn (Page $page) => $page->page_title.' ['.($page->page_type ?? 'page').']'),
            'testimonials' => $this->results(Review::query()->where('is_testimonial', true), 'author_name', $term, $limit, function (Review $review) {
                $name = Str::limit(trim((string) $review->author_name), 40);
                $snippet = Str::limit(trim(strip_tags((string) $review->content)), 60);
                return $snippet !== '' ? "{$name} — {$snippet}" : $name;
            }),
            'users' => $this->results(User::query(), 'name', $term, $limit, fn (User $u) => $u->publicName().' <'.$u->email.'>'),
            'categories' =>$this->results(Category::query(), 'name', $term, $limit, function (Category $category) {
                $suffix = $category->type;

                if ($category->type === 'service' && $category->category_type) {
                    $suffix .= ':'.$category->category_type;
                }

                return $category->name.' ['.$suffix.']';
            }),
            'service-categories' => $this->serviceCategoryResults('service', $term, $limit),
            'sector-categories' => $this->serviceCategoryResults('sector', $term, $limit),
            'service-leaf-categories' => $this->leafCategoryResults('service', $term, $limit),
            'location-leaf-categories' => $this->leafCategoryResults('location', $term, $limit),
            'blog-leaf-categories' => $this->leafCategoryResults('blog', $term, $limit),
            'blog-categories' => $this->categoryResults('blog', $term, $limit),
            // All active service/sector categories — used by navigation item link selector
            'nav-categories' => $this->navCategoryResults($term, $limit),
            default => [],
        };
    }

    protected function results(Builder $query, string $col, string $term, int $limit, \Closure $label): array
    {
        $operator = $query->getModel()->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return $query
            ->when(
                $query->getModel()->getConnection()->getSchemaBuilder()->hasColumn($query->getModel()->getTable(), 'status'),
                fn ($q) => method_exists($query->getModel(), 'scopeActive') ? $q->active() : $q
            )
            ->when($term !== '', fn ($q) => $q->where($col, $operator, '%'.$term.'%'))
            ->orderBy($col)
            ->limit($limit)
            ->get()
            ->map(fn ($item) => ['id' => $item->id, 'text' => $label($item)])
            ->all();
    }

    protected function leafCategoryResults(string $type, string $term, int $limit): array
    {
        $operator = (new Category)->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Category::ofType($type)
            ->active()
            ->leaf()
            ->when($term !== '', fn ($q) => $q->where('name', $operator, '%'.$term.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (Category $category) {
                $label = $category->name;

                if ($category->type === 'service' && $category->category_type) {
                    $label .= ' ('.ucfirst((string) $category->category_type).')';
                }

                return ['id' => $category->id, 'text' => $label];
            })
            ->all();
    }

    protected function categoryResults(string $type, string $term, int $limit): array
    {
        $operator = (new Category)->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Category::ofType($type)
            ->active()
            ->when($term !== '', fn ($q) => $q->where('name', $operator, '%'.$term.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Category $category) => ['id' => $category->id, 'text' => $category->name])
            ->all();
    }

    /**
     * Returns all active service-type categories (both 'service' and 'sector' subtypes)
     * for use in navigation item linked-content selectors.
     */
    protected function navCategoryResults(string $term, int $limit): array
    {
        $operator = (new Category)->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Category::ofType('service')
            ->active()
            ->whereIn('category_type', Category::SERVICE_CATEGORY_TYPES)
            ->when($term !== '', fn ($q) => $q->where('name', $operator, '%'.$term.'%'))
            ->orderBy('category_type') // 'sector' before 'service' alphabetically — or keep consistent
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(function (Category $category) {
                $type = ucfirst((string) ($category->category_type ?? 'service'));
                return ['id' => $category->id, 'text' => $category->name." [{$type}]"];
            })
            ->all();
    }

    protected function serviceCategoryResults(string $categoryType, string $term, int $limit): array
    {
        $operator = (new Category)->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';

        return Category::ofType('service')
            ->active()
            ->where('category_type', $categoryType)
            ->when($term !== '', fn ($q) => $q->where('name', $operator, '%'.$term.'%'))
            ->orderBy('name')
            ->limit($limit)
            ->get()
            ->map(fn (Category $category) => ['id' => $category->id, 'text' => $category->name])
            ->all();
    }

}
