<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index(string $type)
    {
        $this->assertType($type);

        $columns = [
            'id' => 'ID',
            'name' => 'Name',
            'slug.slug' => 'Slug',
            'parent.name' => 'Parent',
            'depth' => 'Depth',
            'status' => 'Status',
        ];

        $search = ['id', 'name', 'slug.slug', 'parent.name'];

        if ($type === 'service') {
            $columns = [
                'id' => 'ID',
                'name' => 'Name',
                'slug.slug' => 'Slug',
                'category_type' => 'Category Type',
                'status' => 'Status',
            ];

            $search[] = 'category_type';
        }

        return $this->renderAdminIndex(
            Category::ofType($type)->with('parent', 'slug')->orderBy('depth')->orderBy('name'),
            [
                'title' => ucfirst($type).' Categories',
                'routes' => [
                    'create' => route('admin.categories.create', $type),
                    'show' => fn ($item) => route('admin.categories.show', [$type, $item->id]),
                    'edit' => fn ($item) => route('admin.categories.edit', [$type, $item->id]),
                    'delete' => fn ($item) => route('admin.categories.destroy', [$type, $item->id]),
                ],
                'columns' => $columns,
                'search' => $search,
            ]
        );
    }

    public function create(string $type)
    {
        $this->assertType($type);

        return view('admin.shared.crud', [
            'item' => null,
            'config' => $this->formConfig(null, $type),
        ]);
    }

    public function store(Request $request, string $type)
    {
        $this->assertType($type);
        $validated = $this->validateRequest($request, $type);

        GlobalCrudService::create(Category::class, $this->payload($validated, $type, $request));

        return redirect()->route('admin.categories.index', $type)->with('success', 'Category created.');
    }

    public function edit(string $type, Category $category)
    {
        $this->assertType($type);
        abort_unless($category->type === $type, 404);

        $item = $category->load(['slug', 'seo', 'images', 'parent', 'testimonials']);

        return view('admin.shared.crud', [
            'item' => $item,
            'config' => $this->formConfig($item, $type),
        ]);
    }

    public function show(string $type, Category $category)
    {
        $this->assertType($type);
        abort_unless($category->type === $type, 404);

        $item = $category->load(['slug', 'seo', 'images', 'parent', 'testimonials']);
        $config = $this->formConfig($item, $type);
        $config['title'] = 'View '.ucfirst($type).' Category';
        $config['routes']['edit'] = route('admin.categories.edit', [$type, $item->id]);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, string $type, Category $category)
    {
        $this->assertType($type);
        abort_unless($category->type === $type, 404);

        $validated = $this->validateRequest($request, $type);

        GlobalCrudService::update($category, $this->payload($validated, $type, $request));

        return redirect()->route('admin.categories.index', $type)->with('success', 'Category updated.');
    }

    public function destroy(string $type, Category $category)
    {
        $this->assertType($type);
        abort_unless($category->type === $type, 404);

        GlobalCrudService::delete($category);

        return back()->with('success', 'Category deleted.');
    }

    protected function payload(array $validated, string $type, Request $request): array
    {
        return [
            'attributes' => [
                'type' => $type,
                'category_type' => $type === 'service'
                    ? ($validated['category_type'] ?? Category::SERVICE_CATEGORY_TYPES[0])
                    : null,
                'name' => $validated['name'],
                'parent_id' => $type === 'service' ? null : (($validated['parent_id'] ?? null) ?: null),
                'short_description' => $validated['short_description'] ?? null,
                'sub_heading1' => $type === 'service' ? ($validated['sub_heading1'] ?? null) : null,
                'sub_heading_description' => $type === 'service' ? ($validated['sub_heading_description'] ?? null) : null,
                'banner_title' => $validated['banner_title'] ?? null,
                'banner_description' => $validated['banner_description'] ?? null,
                'section_cta_heading' => $validated['section_cta_heading'] ?? null,
                'section_cta_description' => $validated['section_cta_description'] ?? null,
                'section_cta_button_name' => $validated['section_cta_button_name'] ?? null,
                'section_cta_button_url' => $validated['section_cta_button_url'] ?? null,
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'status' => (bool) $validated['status'],
                'testimonials_heading' => $type === 'service' ? ($validated['testimonials_heading'] ?? null) : null,
                'testimonials_sub_heading' => $type === 'service' ? ($validated['testimonials_sub_heading'] ?? null) : null,
            ],
            'seo' => SeoHelper::data($validated),
            'pivot' => $type === 'service' ? [
                'testimonials' => $validated['testimonial_ids'] ?? [],
            ] : [],
            'images' => [
                'banner_desktop' => $request->banner_desktop,
                'section_cta_image' => $request->section_cta_image,
            ],
            'slug' => $validated['slug'] ?? null,
            'slug_source' => $validated['name'] ?? null,
            'slug_was_submitted' => $request->has('slug'),
        ];
    }

    protected function formConfig($item, string $type): array
    {
        if ($type === 'blog') {
            return [
                'title' => ($item ? 'Edit ' : 'Add ').ucfirst($type).' Category',
                'routes' => [
                    'index' => route('admin.categories.index', $type),
                    'store' => route('admin.categories.store', $type),
                    'update' => $item ? route('admin.categories.update', [$type, $item->id]) : null,
                    'edit' => $item ? route('admin.categories.edit', [$type, $item->id]) : null,
                ],
                'form' => [
                    [
                        ['type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true, 'col' => 6],
                        ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 6, 'value' => $item?->slug?->slug],
                    ],
                    [
                        ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 12],
                    ],
                ],
            ];
        }

        $parents = $type === 'service' ? [] : Category::ofType($type)
            ->when($item, fn ($query) => $query->where('id', '!=', $item->id))
            ->orderBy('depth')
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Category $category) => [$category->id => $this->parentOptionLabel($category)])
            ->prepend('-- Root --', '')
            ->all();

        $placementRow = $type === 'service'
            ? [[
                [
                    'type' => 'select',
                    'label' => 'Category Type',
                    'name' => 'category_type',
                    'options' => ['service' => 'Service', 'sector' => 'Sector'],
                    'value' => $item?->category_type ?? Category::SERVICE_CATEGORY_TYPES[0],
                    'required' => true,
                    'col' => 6,
                ],
                ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
            ]]
            : [[
                ['type' => 'select', 'label' => 'Parent Category', 'name' => 'parent_id', 'options' => $parents, 'col' => 8],
                ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 4],
            ]];

        $serviceContentRows = $type === 'service'
            ? [
                [['type' => 'textarea', 'label' => 'Sub Heading 1', 'name' => 'sub_heading1', 'editor' => false, 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Sub Heading Description', 'name' => 'sub_heading_description', 'editor' => false, 'col' => 12]],
            ]
            : [];

        $testimonialsRows = $type === 'service'
            ? [
                [
                    ['type' => 'text', 'label' => 'Testimonials Heading', 'name' => 'testimonials_heading', 'col' => 6],
                    ['type' => 'text', 'label' => 'Testimonials Sub Heading', 'name' => 'testimonials_sub_heading', 'col' => 6],
                ],
                [[
                    'type' => 'multi-select',
                    'label' => 'Testimonials',
                    'name' => 'testimonial_ids',
                    'options' => [],
                    'selected' => $item?->testimonials->pluck('id')->all() ?? [],
                    'selected_labels' => AdminSelectLabel::testimonials($item?->testimonials ?? []),
                    'ajax_url' => route('admin.ajax.search', 'testimonials'),
                    'placeholder' => 'Search testimonials',
                    'col' => 12,
                ]],
            ]
            : [];

        return [
            'title' => ($item ? 'Edit ' : 'Add ').ucfirst($type).' Category',
            'routes' => [
                'index' => route('admin.categories.index', $type),
                'store' => route('admin.categories.store', $type),
                'update' => $item ? route('admin.categories.update', [$type, $item->id]) : null,
                'edit' => $item ? route('admin.categories.edit', [$type, $item->id]) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Name', 'name' => 'name', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 6, 'value' => $item?->slug?->slug],
                ],
                ...$placementRow,
                [
                    ['type' => 'select', 'label' => 'Featured', 'name' => 'is_featured', 'options' => ['1' => 'Yes', '0' => 'No'], 'col' => 12],
                ],
                [['type' => 'textarea', 'label' => 'Short Description', 'name' => 'short_description', 'editor' => false, 'col' => 12]],
                ...$serviceContentRows,
                [['type' => 'text', 'label' => 'Banner Title', 'name' => 'banner_title', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Banner Description', 'name' => 'banner_description', 'editor' => false, 'col' => 12]],
                [
                    ['type' => 'image', 'label' => 'Banner', 'name' => 'banner_desktop', 'image_key' => 'banner_desktop', 'directory' => 'managed/categories/'.$type.'/banner_desktop', 'col' => 6],
                ],
                [['type' => 'text', 'label' => 'Section CTA Heading', 'name' => 'section_cta_heading', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Section CTA Description', 'name' => 'section_cta_description', 'editor' => false, 'col' => 12]],
                [
                    ['type' => 'image', 'label' => 'Section CTA Image', 'name' => 'section_cta_image', 'image_key' => 'section_cta_image', 'directory' => 'managed/categories/'.$type.'/section_cta_image', 'col' => 12],
                ],
                [
                    ['type' => 'text', 'label' => 'Section CTA Button Name', 'name' => 'section_cta_button_name', 'col' => 6],
                    ['type' => 'text', 'label' => 'Section CTA Button URL', 'name' => 'section_cta_button_url', 'col' => 6],
                ],
                ...$testimonialsRows,
                ...SeoHelper::form(),
            ],
        ];
    }

    protected function validateRequest(Request $request, string $type): array
    {
        return $request->validate([
            'name' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'parent_id' => $type === 'service'
                ? ['nullable']
                : [
                    'nullable',
                    Rule::exists('categories', 'id')->where(fn ($query) => $query->where('type', $type)),
                ],
            'category_type' => [
                Rule::requiredIf($type === 'service'),
                'nullable',
                Rule::in(Category::SERVICE_CATEGORY_TYPES),
            ],
            'status' => 'required|boolean',
            'is_featured' => 'nullable|boolean',
            'short_description' => 'nullable|string',
            'sub_heading1' => 'nullable|string',
            'sub_heading_description' => 'nullable|string',
            'banner_title' => 'nullable|string|max:255',
            'banner_description' => 'nullable|string',
            'section_cta_heading' => 'nullable|string|max:255',
            'section_cta_description' => 'nullable|string',
            'section_cta_button_name' => 'nullable|string|max:255',
            'section_cta_button_url' => 'nullable|string|max:500',
            'testimonials_heading' => 'nullable|string|max:255',
            'testimonials_sub_heading' => 'nullable|string|max:255',
            'testimonial_ids' => 'nullable|array',
            'testimonial_ids.*' => 'integer|exists:reviews,id',
            ...ImageField::singleRules('banner_desktop', 4096),
            ...ImageField::singleRules('section_cta_image', 4096),
            ...SeoHelper::rules(),
        ]);
    }

    protected function parentOptionLabel(Category $category): string
    {
        $label = str_repeat('  ', max(0, (int) $category->depth)).$category->name;

        if ($category->type === 'service' && $category->category_type) {
            $label .= ' ('.ucfirst((string) $category->category_type).')';
        }

        return $label;
    }

    protected function assertType(string $type): void
    {
        abort_unless(in_array($type, Category::TYPES, true), 404);
    }
}
