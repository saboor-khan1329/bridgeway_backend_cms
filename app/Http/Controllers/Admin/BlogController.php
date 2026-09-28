<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Blog;
use App\Models\Category;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Http\Request;

class BlogController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(
            Blog::with(['slug', 'categories.slug', 'authorUser'])->latest('published_at'),
            [
                'title' => 'Blogs',
                'routes' => [
                    'create' => route('admin.blogs.create'),
                    'show' => fn ($item) => route('admin.blogs.show', $item->id),
                    'edit' => fn ($item) => route('admin.blogs.edit', $item->id),
                    'delete' => fn ($item) => route('admin.blogs.destroy', $item->id),
                ],
                'columns' => [
                    'id' => 'ID',
                    'title' => 'Title',
                    'slug.slug' => 'Slug',
                    'categories_list' => 'Categories',
                    'author_name' => 'Author',
                    'published_at' => 'Published',
                    'status' => 'Status',
                ],
                'search' => ['id', 'title', 'author', 'authorUser.name', 'authorUser.email', 'slug.slug', 'categories.name'],
            ]
        );
    }

    public function create()
    {
        return view('admin.shared.crud', [
            'item' => null,
            'config' => $this->formConfig(null),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        GlobalCrudService::create(Blog::class, $this->payload($validated, $request));

        return redirect()->route('admin.blogs.index')->with('success', 'Blog created.');
    }

    public function edit(Blog $blog)
    {
        $item = $blog->load([
            'slug',
            'seo',
            'images',
            'categories',
            'linkedChildren',
            'authorUser',
        ]);

        return view('admin.shared.crud', [
            'item' => $item,
            'config' => $this->formConfig($item),
        ]);
    }

    public function show(Blog $blog)
    {
        $item = $blog->load([
            'slug',
            'seo',
            'images',
            'categories',
            'linkedChildren',
            'authorUser',
        ]);
        $config = $this->formConfig($item);
        $config['title'] = 'View Blog';
        $config['routes']['edit'] = route('admin.blogs.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, Blog $blog)
    {
        $validated = $this->validateRequest($request, $blog);
        GlobalCrudService::update($blog, $this->payload($validated, $request));

        return redirect()->route('admin.blogs.index')->with('success', 'Blog updated.');
    }

    public function destroy(Blog $blog)
    {
        GlobalCrudService::delete($blog);

        return back()->with('success', 'Blog deleted.');
    }

    protected function payload(array $validated, Request $request): array
    {
        return [
            'attributes' => [
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'excerpt' => $validated['excerpt'] ?? null,
                'content' => $validated['content'] ?? null,
                'author_user_id' => ($validated['author_user_id'] ?? null) ?: null,
                'published_at' => $validated['published_at'] ?? null,
                'status' => (bool) $validated['status'],
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
            ],
            'seo' => SeoHelper::data($validated),
            'pivot' => [
                'linkedChildren' => $validated['related_blog_ids'] ?? [],
                'categories' => $this->validBlogCategories($validated['category_ids'] ?? []),
            ],
            'images' => [
                'cover' => $request->cover,
                'thumbnail' => $request->thumbnail,
            ],
            'slug' => $validated['slug'] ?? null,
            'slug_source' => $validated['title'] ?? null,
            'slug_was_submitted' => $request->has('slug'),
        ];
    }

    protected function validBlogCategories(array $ids): array
    {
        $valid = Category::ofType('blog')->active()->whereIn('id', $ids)->pluck('id')->all();

        return collect($ids)
            ->filter(fn ($id) => in_array((int) $id, array_map('intval', $valid), true))
            ->values()
            ->all();
    }

    protected function formConfig($item): array
    {
        return [
            'title' => $item ? 'Edit Blog' : 'Add Blog',
            'routes' => [
                'index' => route('admin.blogs.index'),
                'store' => route('admin.blogs.store'),
                'update' => $item ? route('admin.blogs.update', $item->id) : null,
                'edit' => $item ? route('admin.blogs.edit', $item->id) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Title', 'name' => 'title', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 6, 'value' => $item?->slug?->slug],
                ],
                [
                    [
                        'type' => 'select',
                        'label' => 'Content Author',
                        'name' => 'author_user_id',
                        'options' => [],
                        'selected' => $item?->author_user_id,
                        'selected_label' => AdminSelectLabel::user($item?->authorUser),
                        'ajax_url' => route('admin.ajax.search', 'users'),
                        'placeholder' => 'Search content author',
                        'col' => 6,
                    ],
                    ['type' => 'text', 'label' => 'Published At', 'name' => 'published_at', 'col' => 6, 'attr' => ['type' => 'datetime-local']],
                ],
                [
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
                    ['type' => 'select', 'label' => 'Featured', 'name' => 'is_featured', 'options' => ['1' => 'Yes', '0' => 'No'], 'col' => 6],
                ],
                [['type' => 'textarea', 'label' => 'Short Description', 'name' => 'short_description', 'editor' => false, 'col' => 12]],
                [['type' => 'text', 'label' => 'Excerpt', 'name' => 'excerpt', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Content', 'name' => 'content', 'editor' => true, 'col' => 12]],
                [
                    ['type' => 'image', 'label' => 'Card Image', 'name' => 'cover', 'image_key' => 'cover', 'directory' => 'managed/blogs/cover', 'col' => 6],
                    ['type' => 'image', 'label' => 'Banner Image', 'name' => 'thumbnail', 'image_key' => 'thumbnail', 'directory' => 'managed/blogs/thumbnail', 'col' => 6],
                ],
                [[
                    'type' => 'multi-select',
                    'label' => 'Blog Categories',
                    'name' => 'category_ids',
                    'options' => [],
                    'selected' => $item?->categories->pluck('id')->all() ?? [],
                    'selected_labels' => AdminSelectLabel::categories($item?->categories ?? []),
                    'ajax_url' => route('admin.ajax.search', 'blog-categories'),
                    'placeholder' => 'Search blog categories',
                    'col' => 12,
                ]],
                [[
                    'type' => 'multi-select',
                    'label' => 'Related Blogs',
                    'name' => 'related_blog_ids',
                    'options' => [],
                    'selected' => $item?->linkedChildren->pluck('id')->all() ?? [],
                    'selected_labels' => AdminSelectLabel::blogs($item?->linkedChildren ?? []),
                    'ajax_url' => route('admin.ajax.search', 'blogs'),
                    'placeholder' => 'Search related blogs',
                    'col' => 12,
                ]],
                ...SeoHelper::form(),
            ],
        ];
    }

    protected function validateRequest(Request $request, ?Blog $blog = null): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'author_user_id' => 'nullable|integer|exists:users,id',
            'published_at' => 'nullable|date',
            'short_description' => 'nullable|string',
            'excerpt' => 'nullable|string|max:255',
            'content' => 'nullable|string',
            'status' => 'required|boolean',
            'is_featured' => 'nullable|boolean',
            ...ImageField::singleRules('cover', 4096),
            ...ImageField::singleRules('thumbnail', 2048),
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'exists:categories,id',
            'related_blog_ids' => 'nullable|array',
            'related_blog_ids.*' => 'integer|exists:blogs,id',
            ...SeoHelper::rules(),
        ]);

        $validated['related_blog_ids'] = $this->sanitizeRelationIds(
            $validated['related_blog_ids'] ?? [],
            $blog?->id
        );

        return $validated;
    }

    protected function sanitizeRelationIds(array $ids, ?int $currentId = null): array
    {
        $sanitized = collect($ids)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();

        if ($currentId === null) {
            return $sanitized;
        }

        return array_values(array_diff($sanitized, [$currentId]));
    }
}
