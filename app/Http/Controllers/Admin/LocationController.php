<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Location;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class LocationController extends Controller
{
    public function index()
    {
        /** @var Builder $query */
        $query = Location::query();
        $query
            ->with(['slug', 'parent'])
            ->orderBy('title')
            ->latest();

        return $this->renderAdminIndex(
            $query,
            [
                'title' => 'Locations',
                'routes' => [
                    'create' => route('admin.locations.create'),
                    'show' => fn ($item) => route('admin.locations.show', $item->id),
                    'edit' => fn ($item) => route('admin.locations.edit', $item->id),
                    'delete' => fn ($item) => route('admin.locations.destroy', $item->id),
                ],
                'columns' => [
                    'id' => 'ID',
                    'title' => 'Title',
                    'parent.title' => 'Primary Parent',
                    'slug.slug' => 'Slug',
                    'status' => 'Status',
                    'created_at' => 'Created',
                ],
                'search' => ['id', 'title', 'parent.title', 'slug.slug'],
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
        GlobalCrudService::create(Location::class, $this->payload($validated, $request));

        return redirect()->route('admin.locations.index')->with('success', 'Location created.');
    }

    public function edit(Location $location)
    {
        $item = $location->load($this->formRelations());

        return view('admin.shared.crud', [
            'item' => $item,
            'config' => $this->formConfig($item),
        ]);
    }

    public function show(Location $location)
    {
        $item = $location->load($this->formRelations());
        $config = $this->formConfig($item);
        $config['title'] = 'View Location';
        $config['routes']['edit'] = route('admin.locations.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, Location $location)
    {
        $validated = $this->validateRequest($request, $location);
        GlobalCrudService::update($location, $this->payload($validated, $request));

        return redirect()->route('admin.locations.index')->with('success', 'Location updated.');
    }

    public function destroy(Location $location)
    {
        GlobalCrudService::delete($location);

        return back()->with('success', 'Location deleted.');
    }

    protected function formRelations(): array
    {
        return [
            'slug',
            'seo',
            'images',
            'parent',
            'linkedChildren',
            'linkedServicesV1',
            'linkedServicesV2',
            'linkedServicesV3',
            'linkedServicesV4',
            'relatedBlogs',
            'faqs',
            'testimonials',
            'section3LocationFaqs',
            'section4LocationFaqs',
        ];
    }

    protected function payload(array $validated, Request $request): array
    {
        return [
            'attributes' => [
                'parent_id' => ($validated['parent_id'] ?? null) ?: null,
                'title' => $validated['title'],
                'sub_heading' => $validated['sub_heading'] ?? null,
                'short_description' => $validated['short_description'] ?? null,
                'card_description' => $validated['card_description'] ?? null,
                'description' => $validated['description'] ?? null,
                'banner_title' => $validated['banner_title'] ?? null,
                'banner_description' => $validated['banner_description'] ?? null,
                'section_2_heading' => $validated['section_2_heading'] ?? null,
                'section_2_description' => $validated['section_2_description'] ?? null,
                'section_2_button_name' => $validated['section_2_button_name'] ?? null,
                'section_2_button_url' => $validated['section_2_button_url'] ?? null,
                'linked_services_v1_heading' => $validated['linked_services_v1_heading'] ?? null,
                'linked_services_v1_sub_description' => $validated['linked_services_v1_sub_description'] ?? null,
                'section_3_locations_faqs' => $this->sectionData($validated, 'section_3_locations_faqs'),
                'section_4_locations_faqs' => $this->sectionData($validated, 'section_4_locations_faqs'),
                'section_5_heading' => $validated['section_5_heading'] ?? null,
                'section_5_description' => $validated['section_5_description'] ?? null,
                'section_6_heading' => $validated['section_6_heading'] ?? null,
                'section_6_description' => $validated['section_6_description'] ?? null,
                'linked_child_locations_heading' => $validated['linked_child_locations_heading'] ?? null,
                'linked_child_locations_sub_description' => $validated['linked_child_locations_sub_description'] ?? null,
                'section_7_heading' => $validated['section_7_heading'] ?? null,
                'section_7_description' => $validated['section_7_description'] ?? null,
                'section_8_heading' => $validated['section_8_heading'] ?? null,
                'section_8_description' => $validated['section_8_description'] ?? null,
                'linked_services_v2_heading' => $validated['linked_services_v2_heading'] ?? null,
                'linked_services_v2_sub_description' => $validated['linked_services_v2_sub_description'] ?? null,
                'related_blogs_heading' => $validated['related_blogs_heading'] ?? null,
                'related_blogs_sub_heading' => $validated['related_blogs_sub_heading'] ?? null,
                'linked_services_v3_heading' => $validated['linked_services_v3_heading'] ?? null,
                'linked_services_v3_sub_description' => $validated['linked_services_v3_sub_description'] ?? null,
                'linked_services_v4_heading' => $validated['linked_services_v4_heading'] ?? null,
                'linked_services_v4_sub_description' => $validated['linked_services_v4_sub_description'] ?? null,
                'section_9_map_src' => $validated['section_9_map_src'] ?? null,
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'status' => (bool) $validated['status'],
                'testimonials_heading' => $validated['testimonials_heading'] ?? null,
                'testimonials_sub_heading' => $validated['testimonials_sub_heading'] ?? null,
            ],
            'seo' => SeoHelper::data($validated),
            'pivot' => [
                'linkedChildren' => $validated['child_location_ids'] ?? [],
                'linkedServicesV1' => $this->linkedServicePayload($validated['linked_service_v1_ids'] ?? [], 'v1'),
                'linkedServicesV2' => $this->linkedServicePayload($validated['linked_service_v2_ids'] ?? [], 'v2'),
                'linkedServicesV3' => $this->linkedServicePayload($validated['linked_service_v3_ids'] ?? [], 'v3'),
                'linkedServicesV4' => $this->linkedServicePayload($validated['linked_service_v4_ids'] ?? [], 'v4'),
                'relatedBlogs' => $validated['related_blog_ids'] ?? [],
                'faqs' => $validated['faq_ids'] ?? [],
                'testimonials' => $validated['testimonial_ids'] ?? [],
                'section3LocationFaqs' => $this->sectionFaqPayload($validated['section_3_location_faq_ids'] ?? [], 'section_3'),
                'section4LocationFaqs' => $this->sectionFaqPayload($validated['section_4_location_faq_ids'] ?? [], 'section_4'),
            ],
            'images' => [
                'banner_desktop' => $request->banner_desktop,
                'thumbnail' => $request->thumbnail,
                'section_2_side_image' => $request->section_2_side_image,
                'section_5_side_image' => $request->section_5_side_image,
                'section_6_side_image' => $request->section_6_side_image,
                'section_8_side_image' => $request->section_8_side_image,
            ],
            'slug' => $validated['slug'] ?? null,
            'slug_source' => $validated['title'] ?? null,
            'slug_was_submitted' => $request->has('slug'),
        ];
    }

    protected function formConfig(?Location $item): array
    {
        return [
            'title' => $item ? 'Edit Location' : 'Add Location',
            'routes' => [
                'index' => route('admin.locations.index'),
                'store' => route('admin.locations.store'),
                'update' => $item ? route('admin.locations.update', $item->id) : null,
                'edit' => $item ? route('admin.locations.edit', $item->id) : null,
            ],
            'form' => [
                [
                    ['type' => 'text', 'label' => 'Title', 'name' => 'title', 'required' => true, 'col' => 6],
                    ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 6, 'value' => $item?->slug?->slug],
                ],
                [
                    ['type' => 'select', 'label' => 'Primary Parent Location', 'name' => 'parent_id', 'options' => $this->locationOptions($item), 'col' => 4],
                    ['type' => 'select', 'label' => 'Featured', 'name' => 'is_featured', 'options' => ['1' => 'Yes', '0' => 'No'], 'col' => 4],
                    ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 4],
                ],
                [['type' => 'text', 'label' => 'Sub Heading', 'name' => 'sub_heading', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Short Description', 'name' => 'short_description', 'editor' => false, 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Card Description', 'name' => 'card_description', 'editor' => false, 'col' => 12]],

                [['type' => 'text', 'label' => 'Banner Title', 'name' => 'banner_title', 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Banner Description', 'name' => 'banner_description', 'editor' => false, 'col' => 12]],
                [
                    ['type' => 'image', 'label' => 'Banner', 'name' => 'banner_desktop', 'image_key' => 'banner_desktop', 'directory' => 'managed/locations/banner_desktop', 'col' => 6],
                    ['type' => 'image', 'label' => 'Thumbnail', 'name' => 'thumbnail', 'image_key' => 'thumbnail', 'directory' => 'managed/locations/thumbnail', 'col' => 6],
                ],
                ...$this->contentSectionFields(2, true, true),
                ...$this->linkedServiceSectionFields(1, $item?->linkedServicesV1 ?? collect()),
                ...$this->locationFaqSectionFields(3, $item?->section3LocationFaqs ?? collect(), $item),
                ...$this->locationFaqSectionFields(4, $item?->section4LocationFaqs ?? collect(), $item),
                ...$this->contentSectionFields(5, false, true),
                ...$this->contentSectionFields(6, false, true),
                [
                    ['type' => 'text', 'label' => 'Linked Child Locations Heading', 'name' => 'linked_child_locations_heading', 'col' => 12],
                ],
                [
                    ['type' => 'textarea', 'label' => 'Linked Child Locations Sub Description', 'name' => 'linked_child_locations_sub_description', 'editor' => false, 'col' => 12],
                ],
                [$this->linkedChildLocationsField($item)],
                ...$this->contentSectionFields(7, false, false),
                ...$this->contentSectionFields(8, false, true),
                ...$this->linkedServiceSectionFields(2, $item?->linkedServicesV2 ?? collect()),
                [$this->faqsField($item)],
                [
                    ['type' => 'text', 'label' => 'Related Blogs Heading', 'name' => 'related_blogs_heading', 'col' => 12],
                ],
                [
                    ['type' => 'textarea', 'label' => 'Related Blogs Sub Heading', 'name' => 'related_blogs_sub_heading', 'editor' => false, 'col' => 12],
                ],
                [$this->relatedBlogsField($item)],
                ...$this->linkedServiceSectionFields(3, $item?->linkedServicesV3 ?? collect()),
                ...$this->linkedServiceSectionFields(4, $item?->linkedServicesV4 ?? collect()),
                [['type' => 'textarea', 'label' => 'Section 9 Map Src', 'name' => 'section_9_map_src', 'editor' => false, 'col' => 12]],
                [['type' => 'textarea', 'label' => 'Description', 'name' => 'description', 'editor' => true, 'col' => 12]],
                [
                    ['type' => 'text', 'label' => 'Testimonials Heading', 'name' => 'testimonials_heading', 'col' => 6],
                    ['type' => 'text', 'label' => 'Testimonials Sub Heading', 'name' => 'testimonials_sub_heading', 'col' => 6],
                ],
                [$this->testimonialsField($item)],

                ...SeoHelper::form(),
            ],
        ];
    }

    protected function contentSectionFields(int $number, bool $withButton, bool $withImage): array
    {
        $rows = [
            [
                ['type' => 'text', 'label' => "Section {$number} Heading", 'name' => "section_{$number}_heading", 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => "Section {$number} Description", 'name' => "section_{$number}_description", 'editor' => true, 'col' => 12],
            ],
        ];

        if ($withImage) {
            $rows[] = [[
                'type' => 'image',
                'label' => "Section {$number} Image",
                'name' => "section_{$number}_side_image",
                'image_key' => "section_{$number}_side_image",
                'directory' => "managed/locations/section_{$number}_side_image",
                'col' => 12,
            ]];
        }

        if ($withButton) {
            $rows[] = [
                ['type' => 'text', 'label' => "Section {$number} Button Name", 'name' => "section_{$number}_button_name", 'col' => 6],
                ['type' => 'text', 'label' => "Section {$number} Button URL", 'name' => "section_{$number}_button_url", 'col' => 6],
            ];
        }

        return $rows;
    }

    protected function linkedServiceSectionFields(int $number, Collection $selected): array
    {
        return [
            [
                ['type' => 'text', 'label' => "Linked Services V{$number} Heading", 'name' => "linked_services_v{$number}_heading", 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => "Linked Services V{$number} Sub Description", 'name' => "linked_services_v{$number}_sub_description", 'editor' => false, 'col' => 12],
            ],
            [$this->linkedServicesField("Linked Services V{$number}", "linked_service_v{$number}_ids", $selected)],
        ];
    }

    protected function linkedServicesField(string $label, string $name, Collection $selected): array
    {
        return [
            'type' => 'multi-select',
            'label' => $label,
            'name' => $name,
            'options' => [],
            'selected' => $selected->pluck('id')->all(),
            'selected_labels' => AdminSelectLabel::services($selected),
            'ajax_url' => route('admin.ajax.search', 'services'),
            'placeholder' => 'Search linked services',
            'col' => 12,
        ];
    }

    protected function linkedChildLocationsField(?Location $item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Linked Child Locations',
            'name' => 'child_location_ids',
            'options' => [],
            'selected' => $item?->linkedChildren->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::locations($item?->linkedChildren ?? []),
            'ajax_url' => route('admin.ajax.search', 'locations'),
            'placeholder' => 'Search child locations',
            'col' => 12,
        ];
    }

    protected function locationFaqSectionFields(int $number, Collection $selected, ?Location $item): array
    {
        $key = "section_{$number}_locations_faqs";

        return [
            [
                ['type' => 'text', 'label' => "Section {$number} Locations FAQs Heading", 'name' => "{$key}[heading]", 'value' => data_get($item, "{$key}.heading"), 'col' => 6],
                ['type' => 'text', 'label' => "Section {$number} Locations FAQs Button Name", 'name' => "{$key}[button_name]", 'value' => data_get($item, "{$key}.button_name"), 'col' => 6],
            ],
            [
                ['type' => 'textarea', 'label' => "Section {$number} Locations FAQs Sub Description", 'name' => "{$key}[sub_description]", 'value' => data_get($item, "{$key}.sub_description"), 'editor' => false, 'col' => 6],
                ['type' => 'text', 'label' => "Section {$number} Locations FAQs Button URL/Path", 'name' => "{$key}[button_url]", 'value' => data_get($item, "{$key}.button_url"), 'col' => 6],
            ],
            [$this->locationFaqsField("Section {$number} Locations FAQs", "section_{$number}_location_faq_ids", $selected)],
        ];
    }

    protected function locationFaqsField(string $label, string $name, Collection $selected): array
    {
        return [
            'type' => 'multi-select',
            'label' => $label,
            'name' => $name,
            'options' => [],
            'selected' => $selected->pluck('id')->all(),
            'selected_labels' => AdminSelectLabel::faqs($selected),
            'ajax_url' => route('admin.ajax.search', 'faqs'),
            'ajax_create_url' => route('admin.faqs.quick-create'),
            'placeholder' => 'Search FAQs',
            'col' => 12,
        ];
    }

    protected function relatedBlogsField(?Location $item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Related Blogs',
            'name' => 'related_blog_ids',
            'options' => [],
            'selected' => $item?->relatedBlogs->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::blogs($item?->relatedBlogs ?? []),
            'ajax_url' => route('admin.ajax.search', 'blogs'),
            'placeholder' => 'Search blogs',
            'col' => 12,
        ];
    }

    protected function faqsField(?Location $item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'FAQs',
            'name' => 'faq_ids',
            'options' => [],
            'selected' => $item?->faqs->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::faqs($item?->faqs ?? []),
            'ajax_url' => route('admin.ajax.search', 'faqs'),
            'ajax_create_url' => route('admin.faqs.quick-create'),
            'placeholder' => 'Search FAQs',
            'col' => 12,
        ];
    }

    protected function testimonialsField(?Location $item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Testimonials',
            'name' => 'testimonial_ids',
            'options' => [],
            'selected' => $item?->testimonials->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::testimonials($item?->testimonials ?? []),
            'ajax_url' => route('admin.ajax.search', 'testimonials'),
            'placeholder' => 'Search testimonials',
            'col' => 12,
        ];
    }

    protected function validateRequest(Request $request, ?Location $location = null): array
    {
        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'parent_id' => [
                'nullable',
                'integer',
                'exists:locations,id',
                function (string $attribute, mixed $value, \Closure $fail) use ($location) {
                    if (! $location || blank($value)) {
                        return;
                    }

                    $parentId = (int) $value;

                    if ($parentId === $location->id) {
                        $fail('A location cannot be its own parent.');

                        return;
                    }

                    if (in_array($parentId, $this->descendantIds($location), true)) {
                        $fail('Select a parent location outside of the current child tree.');
                    }
                },
            ],
            'child_location_ids' => 'nullable|array',
            'child_location_ids.*' => 'integer|exists:locations,id',
            'sub_heading' => 'nullable|string|max:255',
            'short_description' => 'nullable|string',
            'card_description' => 'nullable|string',
            'description' => 'nullable|string',
            'banner_title' => 'nullable|string|max:255',
            'banner_description' => 'nullable|string',
            'section_2_heading' => 'nullable|string|max:255',
            'section_2_description' => 'nullable|string',
            'section_2_button_name' => 'nullable|string|max:255',
            'section_2_button_url' => 'nullable|string|max:500',
            'linked_services_v1_heading' => 'nullable|string|max:255',
            'linked_services_v1_sub_description' => 'nullable|string',
            'linked_service_v1_ids' => 'nullable|array',
            'linked_service_v1_ids.*' => 'integer|exists:services,id',
            'section_3_locations_faqs' => 'nullable|array',
            'section_3_locations_faqs.heading' => 'nullable|string|max:255',
            'section_3_locations_faqs.sub_description' => 'nullable|string',
            'section_3_locations_faqs.button_name' => 'nullable|string|max:255',
            'section_3_locations_faqs.button_url' => 'nullable|string|max:500',
            'section_4_locations_faqs' => 'nullable|array',
            'section_4_locations_faqs.heading' => 'nullable|string|max:255',
            'section_4_locations_faqs.sub_description' => 'nullable|string',
            'section_4_locations_faqs.button_name' => 'nullable|string|max:255',
            'section_4_locations_faqs.button_url' => 'nullable|string|max:500',
            'section_3_location_faq_ids' => 'nullable|array',
            'section_3_location_faq_ids.*' => 'integer|exists:faqs,id',
            'section_4_location_faq_ids' => 'nullable|array',
            'section_4_location_faq_ids.*' => 'integer|exists:faqs,id',
            'section_5_heading' => 'nullable|string|max:255',
            'section_5_description' => 'nullable|string',
            'section_6_heading' => 'nullable|string|max:255',
            'section_6_description' => 'nullable|string',
            'linked_child_locations_heading' => 'nullable|string|max:255',
            'linked_child_locations_sub_description' => 'nullable|string',
            'section_7_heading' => 'nullable|string|max:255',
            'section_7_description' => 'nullable|string',
            'section_8_heading' => 'nullable|string|max:255',
            'section_8_description' => 'nullable|string',
            'linked_services_v2_heading' => 'nullable|string|max:255',
            'linked_services_v2_sub_description' => 'nullable|string',
            'linked_service_v2_ids' => 'nullable|array',
            'linked_service_v2_ids.*' => 'integer|exists:services,id',
            'related_blogs_heading' => 'nullable|string|max:255',
            'related_blogs_sub_heading' => 'nullable|string',
            'linked_services_v3_heading' => 'nullable|string|max:255',
            'linked_services_v3_sub_description' => 'nullable|string',
            'linked_service_v3_ids' => 'nullable|array',
            'linked_service_v3_ids.*' => 'integer|exists:services,id',
            'linked_services_v4_heading' => 'nullable|string|max:255',
            'linked_services_v4_sub_description' => 'nullable|string',
            'linked_service_v4_ids' => 'nullable|array',
            'linked_service_v4_ids.*' => 'integer|exists:services,id',
            'section_9_map_src' => 'nullable|string|max:5000',
            'status' => 'required|boolean',
            'is_featured' => 'nullable|boolean',
            'testimonials_heading' => 'nullable|string|max:255',
            'testimonials_sub_heading' => 'nullable|string|max:255',
            'testimonial_ids' => 'nullable|array',
            'testimonial_ids.*' => 'integer|exists:reviews,id',
            ...ImageField::singleRules('banner_desktop', 4096),
            ...ImageField::singleRules('thumbnail', 2048),
            ...ImageField::singleRules('section_2_side_image', 4096),
            ...ImageField::singleRules('section_5_side_image', 4096),
            ...ImageField::singleRules('section_6_side_image', 4096),
            ...ImageField::singleRules('section_8_side_image', 4096),
            'related_blog_ids' => 'nullable|array',
            'related_blog_ids.*' => 'integer|exists:blogs,id',
            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'exists:faqs,id',
            ...SeoHelper::rules(),
        ]);

        $validated['child_location_ids'] = $this->sanitizeRelationIds($validated['child_location_ids'] ?? [], $location?->id);
        $validated['linked_service_v1_ids'] = $this->sanitizeRelationIds($validated['linked_service_v1_ids'] ?? []);
        $validated['linked_service_v2_ids'] = $this->sanitizeRelationIds($validated['linked_service_v2_ids'] ?? []);
        $validated['linked_service_v3_ids'] = $this->sanitizeRelationIds($validated['linked_service_v3_ids'] ?? []);
        $validated['linked_service_v4_ids'] = $this->sanitizeRelationIds($validated['linked_service_v4_ids'] ?? []);
        $validated['related_blog_ids'] = $this->sanitizeRelationIds($validated['related_blog_ids'] ?? []);
        $validated['section_3_location_faq_ids'] = $this->sanitizeRelationIds($validated['section_3_location_faq_ids'] ?? []);
        $validated['section_4_location_faq_ids'] = $this->sanitizeRelationIds($validated['section_4_location_faq_ids'] ?? []);

        return $validated;
    }

    protected function descendantIds(Location $location): array
    {
        $seen = [];
        $frontier = [$location->id];

        while ($frontier !== []) {
            $children = Location::query()
                ->whereIn('parent_id', $frontier)
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->all();

            $frontier = array_values(array_diff($children, $seen));

            if ($frontier === []) {
                break;
            }

            $seen = array_merge($seen, $frontier);
        }

        return $seen;
    }

    protected function locationOptions(?Location $item = null): array
    {
        $locations = Location::query()
            ->select(['id', 'parent_id', 'title'])
            ->when($item, fn ($query) => $query->where('id', '!=', $item->id))
            ->orderBy('title')
            ->get()
            ->groupBy(fn (Location $location) => (int) ($location->parent_id ?: 0));

        $options = ['' => '-- No Parent --'];

        $append = function (int $parentId, int $depth) use (&$append, &$options, $locations): void {
            foreach ($locations->get($parentId, collect()) as $location) {
                $options[$location->id] = str_repeat('  ', $depth).$location->title;
                $append($location->id, $depth + 1);
            }
        };

        $append(0, 0);

        return $options;
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

    protected function linkedServicePayload(array $ids, string $group): array
    {
        return collect($ids)
            ->mapWithKeys(fn ($id) => [(int) $id => ['link_group' => $group]])
            ->all();
    }

    protected function sectionFaqPayload(array $ids, string $section): array
    {
        return collect($ids)
            ->mapWithKeys(fn ($id) => [(int) $id => ['section_key' => $section]])
            ->all();
    }

    protected function sectionData(array $validated, string $key): ?array
    {
        $data = collect($validated[$key] ?? [])
            ->only(['heading', 'sub_description', 'button_name', 'button_url'])
            ->map(fn ($value) => is_string($value) ? trim($value) : $value)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();

        return $data === [] ? null : $data;
    }
}
