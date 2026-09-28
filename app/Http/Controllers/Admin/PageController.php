<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Page;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class PageController extends Controller
{
    public function index()
    {
        /** @var Builder $query */
        $query = Page::query();
        $query->with('slug')->latest();

        return $this->renderAdminIndex($query, [
            'title' => 'Pages',
            'routes' => [
                'create' => route('admin.pages.create'),
                'show' => fn ($item) => route('admin.pages.show', $item->id),
                'edit' => fn ($item) => route('admin.pages.edit', $item->id),
                'delete' => fn ($item) => route('admin.pages.destroy', $item->id),
            ],
            'columns' => [
                'id' => 'ID',
                'page_title' => 'Title',
                'page_type' => 'Type',
                'slug.slug' => 'Slug',
                'status' => 'Status',
            ],
            'search' => ['id', 'page_title', 'page_type', 'slug.slug'],
        ]);
    }

    public function create()
    {
        return view('admin.shared.crud', ['item' => null, 'config' => $this->formConfig(null)]);
    }

    public function store(Request $request)
    {
        $validated = $this->validateRequest($request);
        GlobalCrudService::create(Page::class, $this->payload($validated, $request));

        return redirect()->route('admin.pages.index')->with('success', 'Page created.');
    }

    public function edit(Page $page)
    {
        $item = $page->load($this->relations());

        return view('admin.shared.crud', ['item' => $item, 'config' => $this->formConfig($item)]);
    }

    public function show(Page $page)
    {
        $item = $page->load($this->relations());
        $config = $this->formConfig($item);
        $config['title'] = 'View Page';
        $config['routes']['edit'] = route('admin.pages.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, Page $page)
    {
        $validated = $this->validateRequest($request);
        GlobalCrudService::update($page, $this->payload($validated, $request));

        return redirect()->route('admin.pages.index')->with('success', 'Page updated.');
    }

    public function destroy(Page $page)
    {
        GlobalCrudService::delete($page);

        return back()->with('success', 'Page deleted.');
    }

    protected function relations(): array
    {
        return array_merge(['slug', 'seo', 'images', 'testimonials'], $this->linkedRelations());
    }

    protected function payload(array $validated, Request $request): array
    {
        return [
            'attributes' => array_merge([
                'page_title' => $validated['page_title'],
                'page_type' => Page::normalizeType($validated['page_type'] ?? null),
                'banner_title' => $validated['banner_title'] ?? null,
                'banner_description' => $validated['banner_description'] ?? null,
                'banner_short_description' => $validated['banner_short_description'] ?? null,
                'button1_name' => $validated['button1_name'] ?? null,
                'button1_link' => $validated['button1_link'] ?? null,
                'button2_name' => $validated['button2_name'] ?? null,
                'button2_link' => $validated['button2_link'] ?? null,
                'status' => (bool) $validated['status'],
                'testimonials_heading' => $validated['testimonials_heading'] ?? null,
                'testimonials_sub_heading' => $validated['testimonials_sub_heading'] ?? null,
            ], $this->linkedSectionAttributes($validated)),
            'seo' => SeoHelper::data($validated),
            'pivot' => array_merge($this->linkedSectionPivot($validated), [
                'testimonials' => $validated['testimonial_ids'] ?? [],
            ]),
            'images' => array_merge([
                'banner_desktop' => $request->banner_desktop,
            ], $this->linkedSectionImages($request)),
            'slug' => $validated['slug'] ?? null,
            'slug_source' => $validated['page_title'] ?? null,
            'slug_was_submitted' => $request->has('slug'),
        ];
    }

    protected function formConfig(?Page $item): array
    {
        return [
            'title' => $item ? 'Edit Page' : 'Add Page',
            'routes' => [
                'index' => route('admin.pages.index'),
                'store' => route('admin.pages.store'),
                'update' => $item ? route('admin.pages.update', $item->id) : null,
                'edit' => $item ? route('admin.pages.edit', $item->id) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Page Title', 'name' => 'page_title', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 6, 'value' => $item?->slug?->slug],
                ],
                [
                    ['type' => 'text', 'label' => 'Page Type', 'name' => 'page_type', 'col' => 6],
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
                ],
                [['type' => 'text', 'label' => 'Banner Title', 'name' => 'banner_title', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Banner Description', 'name' => 'banner_description', 'editor' => false, 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Banner Short Description', 'name' => 'banner_short_description', 'editor' => false, 'col' => 12]],
                [
                    ['type' => 'text', 'label' => 'Button 1 Name', 'name' => 'button1_name', 'col' => 6],
                    ['type' => 'text', 'label' => 'Button 1 Link', 'name' => 'button1_link', 'col' => 6],
                ],
                [
                    ['type' => 'text', 'label' => 'Button 2 Name', 'name' => 'button2_name', 'col' => 6],
                    ['type' => 'text', 'label' => 'Button 2 Link', 'name' => 'button2_link', 'col' => 6],
                ],
                [
                    ['type' => 'image', 'label' => 'Banner', 'name' => 'banner_desktop', 'image_key' => 'banner_desktop', 'directory' => 'managed/pages/banner_desktop', 'col' => 12],
                ],
                ...$this->linkedSectionFormRows($item),
                [
                    ['type' => 'text', 'label' => 'Testimonials Heading', 'name' => 'testimonials_heading', 'col' => 6],
                    ['type' => 'text', 'label' => 'Testimonials Sub Heading', 'name' => 'testimonials_sub_heading', 'col' => 6],
                ],
                [$this->ajaxMulti('Testimonials', 'testimonial_ids', 'testimonials', AdminSelectLabel::testimonials($item?->testimonials ?? collect()), $item?->testimonials->pluck('id')->all() ?? [])],
                ...SeoHelper::form(),
            ],
        ];
    }

    protected function validateRequest(Request $request): array
    {
        $validated = $request->validate([
            'page_title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'page_type' => 'nullable|string|max:64',
            'banner_title' => 'nullable|string|max:255',
            'banner_description' => 'nullable|string',
            'banner_short_description' => 'nullable|string',
            'button1_name' => 'nullable|string|max:255',
            'button1_link' => 'nullable|string|max:500',
            'button2_name' => 'nullable|string|max:255',
            'button2_link' => 'nullable|string|max:500',
            'status' => 'required|boolean',
            'testimonials_heading' => 'nullable|string|max:255',
            'testimonials_sub_heading' => 'nullable|string|max:255',
            'testimonial_ids' => 'nullable|array',
            'testimonial_ids.*' => 'integer|exists:reviews,id',
            ...ImageField::singleRules('banner_desktop', 4096),
            ...$this->linkedSectionImageRules(),
            ...$this->linkedSectionRules(),
            ...SeoHelper::rules(),
        ]);

        foreach ($this->linkedRelationInputNames() as $inputName) {
            $validated[$inputName] = $this->ids($validated[$inputName] ?? []);
        }

        return $validated;
    }

    protected function linkedSectionFormRows(?Page $item): array
    {
        $rows = [];

        foreach ($this->linkedSectionResources() as $resource => $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $label = "Linked {$config['label']} V{$number}";
                $prefix = "linked_{$resource}_v{$number}";
                $relation = "{$config['relation']}V{$number}";
                $idsName = "linked_{$config['singular']}_v{$number}_ids";
                $selected = $item ? $item->{$relation} : collect();

                $rows[] = [
                    ['type' => 'text', 'label' => "{$label} Heading", 'name' => "{$prefix}_heading", 'col' => $config['buttons'] ? 6 : 12],
                    ...($config['buttons'] ? [
                        ['type' => 'text', 'label' => "{$label} Button Name", 'name' => "{$prefix}_button_name", 'col' => 6],
                    ] : []),
                ];

                $rows[] = [
                    ['type' => 'textarea', 'label' => "{$label} Sub Description", 'name' => "{$prefix}_sub_description", 'editor' => false, 'col' => $config['buttons'] ? 6 : 12],
                    ...($config['buttons'] ? [
                        ['type' => 'text', 'label' => "{$label} Button URL/Path", 'name' => "{$prefix}_button_url", 'col' => 6],
                    ] : []),
                ];

                if ($config['side_image']) {
                    $rows[] = [[
                        'type' => 'image',
                        'label' => "{$label} Side Image",
                        'name' => "{$prefix}_side_image",
                        'image_key' => "{$prefix}_side_image",
                        'directory' => "managed/pages/{$prefix}_side_image",
                        'col' => 12,
                    ]];
                }

                $rows[] = [
                    $this->ajaxMulti(
                        $label,
                        $idsName,
                        $config['search'],
                        $this->selectedLabels($resource, $selected),
                        $selected->pluck('id')->all()
                    ),
                ];
            }
        }

        return $rows;
    }

    protected function linkedSectionAttributes(array $validated): array
    {
        $attributes = [];

        foreach ($this->linkedSectionResources() as $resource => $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $prefix = "linked_{$resource}_v{$number}";
                $fields = ['heading', 'sub_description'];

                if ($config['buttons']) {
                    $fields = array_merge($fields, ['button_name', 'button_url']);
                }

                foreach ($fields as $field) {
                    $key = "{$prefix}_{$field}";
                    $attributes[$key] = $validated[$key] ?? null;
                }
            }
        }

        return $attributes;
    }

    protected function linkedSectionPivot(array $validated): array
    {
        $pivot = [];

        foreach ($this->linkedSectionResources() as $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $relation = "{$config['relation']}V{$number}";
                $inputName = "linked_{$config['singular']}_v{$number}_ids";
                $pivot[$relation] = $this->linkedPayload($validated[$inputName] ?? [], "v{$number}");
            }
        }

        return $pivot;
    }

    protected function linkedSectionRules(): array
    {
        $rules = [];

        foreach ($this->linkedSectionResources() as $resource => $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $prefix = "linked_{$resource}_v{$number}";
                $idsName = "linked_{$config['singular']}_v{$number}_ids";

                $rules["{$prefix}_heading"] = 'nullable|string|max:255';
                $rules["{$prefix}_sub_description"] = 'nullable|string';

                if ($config['buttons']) {
                    $rules["{$prefix}_button_name"] = 'nullable|string|max:255';
                    $rules["{$prefix}_button_url"] = 'nullable|string|max:500';
                }

                $rules[$idsName] = 'nullable|array';
                $rules["{$idsName}.*"] = "integer|exists:{$config['table']},id";
            }
        }

        return $rules;
    }

    protected function linkedSectionImages(Request $request): array
    {
        $images = [];

        foreach ($this->linkedSectionResources() as $resource => $config) {
            if (! $config['side_image']) {
                continue;
            }

            for ($number = 1; $number <= $config['versions']; $number++) {
                $key = "linked_{$resource}_v{$number}_side_image";
                $images[$key] = $request->{$key};
            }
        }

        return $images;
    }

    protected function linkedSectionImageRules(): array
    {
        $rules = [];

        foreach ($this->linkedSectionResources() as $resource => $config) {
            if (! $config['side_image']) {
                continue;
            }

            for ($number = 1; $number <= $config['versions']; $number++) {
                $rules = array_merge(
                    $rules,
                    ImageField::singleRules("linked_{$resource}_v{$number}_side_image", 4096)
                );
            }
        }

        return $rules;
    }

    protected function linkedRelations(): array
    {
        $relations = [];

        foreach ($this->linkedSectionResources() as $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $relations[] = "{$config['relation']}V{$number}";
            }
        }

        return $relations;
    }

    protected function linkedRelationInputNames(): array
    {
        $inputNames = [];

        foreach ($this->linkedSectionResources() as $config) {
            for ($number = 1; $number <= $config['versions']; $number++) {
                $inputNames[] = "linked_{$config['singular']}_v{$number}_ids";
            }
        }

        return $inputNames;
    }

    protected function linkedSectionResources(): array
    {
        return [
            'services' => [
                'singular' => 'service',
                'label' => 'Services',
                'relation' => 'linkedServices',
                'search' => 'services',
                'table' => 'services',
                'versions' => 6,
                'buttons' => true,
                'side_image' => true,
            ],
            'locations' => [
                'singular' => 'location',
                'label' => 'Locations',
                'relation' => 'linkedLocations',
                'search' => 'locations',
                'table' => 'locations',
                'versions' => 3,
                'buttons' => true,
                'side_image' => true,
            ],
            'faqs' => [
                'singular' => 'faq',
                'label' => 'FAQs',
                'relation' => 'linkedFaqs',
                'search' => 'faqs',
                'table' => 'faqs',
                'versions' => 3,
                'buttons' => true,
                'side_image' => true,
            ],
            'blogs' => [
                'singular' => 'blog',
                'label' => 'Blogs',
                'relation' => 'linkedBlogs',
                'search' => 'blogs',
                'table' => 'blogs',
                'versions' => 2,
                'buttons' => false,
                'side_image' => true,
            ],
        ];
    }

    protected function selectedLabels(string $resource, Collection $selected): array
    {
        return match ($resource) {
            'services' => AdminSelectLabel::services($selected),
            'locations' => AdminSelectLabel::locations($selected),
            'faqs' => AdminSelectLabel::faqs($selected),
            'blogs' => AdminSelectLabel::blogs($selected),
            default => [],
        };
    }

    protected function ajaxMulti(string $label, string $name, string $resource, array $labels, array $selected): array
    {
        $field = [
            'type' => 'multi-select',
            'label' => $label,
            'name' => $name,
            'options' => [],
            'selected' => $selected,
            'selected_labels' => $labels,
            'ajax_url' => route('admin.ajax.search', $resource),
            'placeholder' => 'Search '.strtolower($label),
            'col' => 12,
        ];

        if ($resource === 'faqs') {
            $field['ajax_create_url'] = route('admin.faqs.quick-create');
        }

        return $field;
    }

    protected function ids(array $ids): array
    {
        return collect($ids)->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    protected function linkedPayload(array $ids, string $group): array
    {
        return collect($ids)
            ->mapWithKeys(fn ($id) => [(int) $id => ['link_group' => $group]])
            ->all();
    }
}
