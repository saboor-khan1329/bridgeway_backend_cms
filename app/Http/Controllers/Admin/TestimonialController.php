<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Http\Request;

/**
 * Testimonials — curated client quotes shown via the "Trusted by Businesses
 * Like Yours" section. Backed by the existing reviews/reviewable schema
 * (App\Models\Review), scoped here to is_testimonial = true; this controller
 * never touches is_testimonial = false rows, leaving that flag free for a
 * future star-rating "Reviews" feature on the same table.
 *
 * A testimonial can be attached to any number of pages/services/locations/
 * categories from this side (bulk assignment); the same relations are also
 * editable from each of those records' own edit forms, since both sides
 * write to the same reviewable pivot.
 */
class TestimonialController extends Controller
{
    public function index()
    {
        return $this->renderAdminIndex(
            Review::query()->where('is_testimonial', true)->latest(),
            [
                'title' => 'Testimonials',
                'routes' => [
                    'create' => route('admin.testimonials.create'),
                    'show' => fn ($item) => route('admin.testimonials.show', $item->id),
                    'edit' => fn ($item) => route('admin.testimonials.edit', $item->id),
                    'delete' => fn ($item) => route('admin.testimonials.destroy', $item->id),
                ],
                'columns' => [
                    'id' => 'ID',
                    'author_name' => 'Client',
                    'title' => 'Headline',
                    'status' => 'Status',
                    'created_at' => 'Created',
                ],
                'search' => ['id', 'author_name', 'title', 'content', 'company_name'],
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

        GlobalCrudService::create(Review::class, $this->payload($validated, $request));

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial created.');
    }

    public function edit(Review $testimonial)
    {
        $item = $testimonial->load(['pages', 'services', 'locations', 'categories', 'images']);

        return view('admin.shared.crud', [
            'item' => $item,
            'config' => $this->formConfig($item),
        ]);
    }

    public function show(Review $testimonial)
    {
        $item = $testimonial->load(['pages', 'services', 'locations', 'categories', 'images']);
        $config = $this->formConfig($item);
        $config['title'] = 'View Testimonial';
        $config['routes']['edit'] = route('admin.testimonials.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, Review $testimonial)
    {
        $validated = $this->validateRequest($request);

        GlobalCrudService::update($testimonial, $this->payload($validated, $request));

        return redirect()->route('admin.testimonials.index')->with('success', 'Testimonial updated.');
    }

    public function destroy(Review $testimonial)
    {
        GlobalCrudService::delete($testimonial);

        return back()->with('success', 'Testimonial deleted.');
    }

    protected function payload(array $validated, Request $request): array
    {
        return [
            'attributes' => [
                'title' => $validated['title'] ?? null,
                'content' => $validated['content'],
                'author_name' => $validated['author_name'],
                'author_role' => $validated['author_role'] ?? null,
                'company_name' => $validated['company_name'] ?? null,
                'rating' => $validated['rating'] ?? 5,
                'status' => (bool) $validated['status'],
                'is_testimonial' => true,
            ],
            'pivot' => [
                'pages' => $validated['page_ids'] ?? [],
                'services' => $validated['service_ids'] ?? [],
                'locations' => $validated['location_ids'] ?? [],
                'categories' => $validated['category_ids'] ?? [],
            ],
            'images' => [
                'photo' => $request->photo,
            ],
        ];
    }

    protected function formConfig($item): array
    {
        return [
            'title' => $item ? 'Edit Testimonial' : 'Add Testimonial',
            'routes' => [
                'index' => route('admin.testimonials.index'),
                'store' => route('admin.testimonials.store'),
                'update' => $item ? route('admin.testimonials.update', $item->id) : null,
                'edit' => $item ? route('admin.testimonials.edit', $item->id) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Headline', 'name' => 'title', 'col' => 12],
                ],
                [
                    ['type' => 'textarea', 'label' => 'Quote', 'name' => 'content', 'editor' => false, 'required' => true, 'col' => 12],
                ],
                [
                    ['type' => 'text', 'label' => 'Client Name', 'name' => 'author_name', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Client Role', 'name' => 'author_role', 'col' => 6],
                ],
                [
                    ['type' => 'text', 'label' => 'Company Name', 'name' => 'company_name', 'col' => 6],
                    ['type' => 'select', 'label' => 'Rating', 'name' => 'rating', 'options' => ['5' => '5', '4' => '4', '3' => '3', '2' => '2', '1' => '1'], 'value' => $item?->rating ?? 5, 'col' => 6],
                ],
                [
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 12],
                ],
                [
                    ['type' => 'image', 'label' => 'Client Photo', 'name' => 'photo', 'image_key' => 'photo', 'directory' => 'managed/testimonials/photo', 'col' => 6],
                ],
                [$this->pagesField($item)],
                [$this->servicesField($item)],
                [$this->locationsField($item)],
                [$this->categoriesField($item)],
            ],
        ];
    }

    protected function pagesField($item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Show on Pages (e.g. Home)',
            'name' => 'page_ids',
            'options' => [],
            'selected' => $item?->pages->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::pages($item?->pages ?? []),
            'ajax_url' => route('admin.ajax.search', 'pages'),
            'placeholder' => 'Search pages',
            'col' => 12,
        ];
    }

    protected function servicesField($item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Show on Services / Sectors',
            'name' => 'service_ids',
            'options' => [],
            'selected' => $item?->services->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::services($item?->services ?? []),
            'ajax_url' => route('admin.ajax.search', 'services'),
            'placeholder' => 'Search services and sectors',
            'col' => 12,
        ];
    }

    protected function locationsField($item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Show on Locations',
            'name' => 'location_ids',
            'options' => [],
            'selected' => $item?->locations->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::locations($item?->locations ?? []),
            'ajax_url' => route('admin.ajax.search', 'locations'),
            'placeholder' => 'Search locations',
            'col' => 12,
        ];
    }

    protected function categoriesField($item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Show on Categories',
            'name' => 'category_ids',
            'options' => [],
            'selected' => $item?->categories->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::categories($item?->categories ?? []),
            'ajax_url' => route('admin.ajax.search', 'categories'),
            'placeholder' => 'Search categories',
            'col' => 12,
        ];
    }

    protected function validateRequest(Request $request): array
    {
        return $request->validate([
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'author_name' => 'required|string|max:191',
            'author_role' => 'nullable|string|max:191',
            'company_name' => 'nullable|string|max:191',
            'rating' => 'nullable|integer|min:1|max:5',
            'status' => 'required|boolean',
            ...ImageField::singleRules('photo', 4096),
            'page_ids' => 'nullable|array',
            'page_ids.*' => 'integer|exists:pages,id',
            'service_ids' => 'nullable|array',
            'service_ids.*' => 'integer|exists:services,id',
            'location_ids' => 'nullable|array',
            'location_ids.*' => 'integer|exists:locations,id',
            'category_ids' => 'nullable|array',
            'category_ids.*' => 'integer|exists:categories,id',
        ]);
    }
}
