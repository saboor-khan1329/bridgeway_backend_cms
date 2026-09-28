<?php

namespace App\Http\Controllers\Admin;

use App\Helpers\SeoHelper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Service;
use App\Services\GlobalCrudService;
use App\Support\AdminSelectLabel;
use App\Support\ImageField;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index()
    {
        $pageType = $this->pageType();
        $routePrefix = $this->routePrefix();

        $query = Service::query()
            ->with(['slug', 'categories.slug'])
            ->whereHas('categories', fn ($query) => $query
                ->where('type', 'service')
                ->where('category_type', $pageType))
            ->orderBy('title');

        return $this->renderAdminIndex(
            $query,
            [
                'title' => $this->pageTitlePlural(),
                'routes' => [
                    'create' => route($routePrefix.'.create'),
                    'show' => fn ($item) => route($routePrefix.'.show', $item->id),
                    'edit' => fn ($item) => route($routePrefix.'.edit', $item->id),
                    'delete' => fn ($item) => route($routePrefix.'.destroy', $item->id),
                ],
                'columns' => [
                    'id' => 'ID',
                    'title' => 'Title',
                    'categories_list' => 'Category',
                    'slug.slug' => 'Slug',
                    'is_featured' => 'Featured',
                    'status' => 'Status',
                    'created_at' => 'Created',
                ],
                'search' => ['id', 'title', 'categories.name', 'slug.slug'],
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
        GlobalCrudService::create(Service::class, $this->payload($validated, $request));

        return redirect()->route($this->routePrefix().'.index')->with('success', $this->pageTitleSingular().' created.');
    }

    public function edit(Service $service)
    {
        $item = $this->loadService($service);
        $this->assertMatchesPageType($item);

        return view('admin.shared.crud', [
            'item' => $item,
            'config' => $this->formConfig($item),
        ]);
    }

    public function show(Service $service)
    {
        $item = $this->loadService($service);
        $this->assertMatchesPageType($item);

        $config = $this->formConfig($item);
        $config['title'] = 'View '.$this->pageTitleSingular();
        $config['routes']['edit'] = route($this->routePrefix().'.edit', $item->id);

        return $this->renderAdminShow($item, $config);
    }

    public function update(Request $request, Service $service)
    {
        $this->assertMatchesPageType($service->load('categories'));

        $validated = $this->validateRequest($request, $service);
        GlobalCrudService::update($service, $this->payload($validated, $request));

        return redirect()->route($this->routePrefix().'.index')->with('success', $this->pageTitleSingular().' updated.');
    }

    public function destroy(Service $service)
    {
        $this->assertMatchesPageType($service->load('categories'));
        GlobalCrudService::delete($service);

        return back()->with('success', $this->pageTitleSingular().' deleted.');
    }

    protected function payload(array $validated, Request $request): array
    {
        $isSector = $this->pageType() === 'sector';

        return [
            'attributes' => [
                'parent_id' => null,
                'title' => $validated['title'],
                'short_description' => $validated['short_description'] ?? null,
                'card_description' => $validated['card_description'] ?? null,
                'banner_title' => $validated['banner_title'] ?? null,
                'banner_description' => $validated['banner_description'] ?? null,
                'section_2_heading' => $validated['section_2_heading'] ?? null,
                'section_2_description' => $validated['section_2_description'] ?? null,
                'section_2_button_name' => $validated['section_2_button_name'] ?? null,
                'section_2_button_url' => $validated['section_2_button_url'] ?? null,
                'section_3_heading' => $isSector ? null : ($validated['section_3_heading'] ?? null),
                'section_3_description' => $isSector ? null : ($validated['section_3_description'] ?? null),
                'section_3_button_name' => $isSector ? null : ($validated['section_3_button_name'] ?? null),
                'section_3_button_url' => $isSector ? null : ($validated['section_3_button_url'] ?? null),
                'section_4_heading' => $isSector ? null : ($validated['section_4_heading'] ?? null),
                'section_4_description' => $isSector ? null : ($validated['section_4_description'] ?? null),
                'section_5_heading' => $validated['section_5_heading'] ?? null,
                'section_5_description' => $validated['section_5_description'] ?? null,
                'section_5_button_name' => $isSector ? ($validated['section_5_button_name'] ?? null) : null,
                'section_5_button_url' => $isSector ? ($validated['section_5_button_url'] ?? null) : null,
                'section_6_heading' => $validated['section_6_heading'] ?? null,
                'section_6_description' => $validated['section_6_description'] ?? null,
                'section_6_button_name' => $isSector ? ($validated['section_6_button_name'] ?? null) : null,
                'section_6_button_url' => $isSector ? ($validated['section_6_button_url'] ?? null) : null,
                'linked_services_v1_heading' => $validated['linked_services_v1_heading'] ?? null,
                'linked_services_v1_sub_description' => $validated['linked_services_v1_sub_description'] ?? null,
                'section_7_heading' => $validated['section_7_heading'] ?? null,
                'section_7_description' => $validated['section_7_description'] ?? null,
                'section_7_button_name' => $validated['section_7_button_name'] ?? null,
                'section_7_button_url' => $validated['section_7_button_url'] ?? null,
                'section_8_heading' => $validated['section_8_heading'] ?? null,
                'section_8_description' => $validated['section_8_description'] ?? null,
                'section_8_button_name' => $validated['section_8_button_name'] ?? null,
                'section_8_button_url' => $validated['section_8_button_url'] ?? null,
                'linked_services_v2_heading' => $validated['linked_services_v2_heading'] ?? null,
                'linked_services_v2_sub_description' => $validated['linked_services_v2_sub_description'] ?? null,
                'related_locations_heading' => $validated['related_locations_heading'] ?? null,
                'related_locations_sub_heading' => $validated['related_locations_sub_heading'] ?? null,
                'section_9_heading' => $validated['section_9_heading'] ?? null,
                'section_9_description' => $validated['section_9_description'] ?? null,
                'section_9_button_name' => $validated['section_9_button_name'] ?? null,
                'section_9_button_url' => $validated['section_9_button_url'] ?? null,
                'related_blogs_heading' => $isSector ? ($validated['related_blogs_heading'] ?? null) : null,
                'related_blogs_sub_heading' => $isSector ? ($validated['related_blogs_sub_heading'] ?? null) : null,
                'linked_services_v3_heading' => null,
                'linked_services_v3_sub_description' => null,
                'section_3_sectors_faqs' => $isSector ? $this->sectionData($validated, 'section_3_sectors_faqs') : null,
                'section_4_sectors_faqs' => $isSector ? $this->sectionData($validated, 'section_4_sectors_faqs') : null,
                'is_featured' => (bool) ($validated['is_featured'] ?? false),
                'status' => (bool) $validated['status'],
                'testimonials_heading' => $validated['testimonials_heading'] ?? null,
                'testimonials_sub_heading' => $validated['testimonials_sub_heading'] ?? null,
            ],
            'seo' => SeoHelper::data($validated),
            'pivot' => [
                'linkedServicesV1' => $this->linkedServicePayload($validated['linked_service_v1_ids'] ?? [], 'v1'),
                'linkedServicesV2' => $this->linkedServicePayload($validated['linked_service_v2_ids'] ?? [], 'v2'),
                'linkedServicesV3' => [],
                'relatedLocations' => $validated['related_location_ids'] ?? [],
                'relatedBlogs' => $isSector ? ($validated['related_blog_ids'] ?? []) : [],
                'categories' => [(int) $validated['category_id']],
                'faqs' => $validated['faq_ids'] ?? [],
                'testimonials' => $validated['testimonial_ids'] ?? [],
                'section3SectorFaqs' => $isSector ? $this->sectionFaqPayload($validated['section_3_sector_faq_ids'] ?? [], 'section_3') : [],
                'section4SectorFaqs' => $isSector ? $this->sectionFaqPayload($validated['section_4_sector_faq_ids'] ?? [], 'section_4') : [],
            ],
            'images' => [
                'banner_desktop' => $request->banner_desktop,
                'thumbnail' => $request->thumbnail,
                'section_2_side_image' => $request->section_2_side_image,
                'section_3_side_image' => $request->section_3_side_image,
                'section_4_side_image' => $request->section_4_side_image,
                'section_5_side_image' => $request->section_5_side_image,
                'section_6_side_image' => $request->section_6_side_image,
                'section_7_side_image' => $request->section_7_side_image,
                'section_8_side_image' => $request->section_8_side_image,
                'section_9_side_image' => $request->section_9_side_image,
            ],
            'slug' => $validated['slug'] ?? null,
            'slug_source' => $validated['title'] ?? null,
            'slug_was_submitted' => $request->has('slug'),
        ];
    }

    protected function formConfig(?Service $item): array
    {
        return [
            'title' => ($item ? 'Edit ' : 'Add ').$this->pageTitleSingular(),
            'routes' => [
                'index' => route($this->routePrefix().'.index'),
                'store' => route($this->routePrefix().'.store'),
                'update' => $item ? route($this->routePrefix().'.update', $item->id) : null,
                'edit' => $item ? route($this->routePrefix().'.edit', $item->id) : null,
            ],
            'form' => $this->pageType() === 'sector'
                ? $this->sectorForm($item)
                : $this->serviceForm($item),
        ];
    }

    protected function serviceForm(?Service $item): array
    {
        return [
            ...$this->baseHeroFields(),
            ...$this->contentSectionFields(2, withButton: true),
            ...$this->contentSectionFields(3, withButton: true),
            ...$this->contentSectionFields(4),
            ...$this->contentSectionFields(5),
            ...$this->contentSectionFields(6),
            ...$this->linkedServiceSectionFields(1, $item?->linkedServicesV1 ?? collect()),
            ...$this->contentSectionFields(7, withButton: true),
            ...$this->contentSectionFields(8, withButton: true),
            ...$this->linkedServiceSectionFields(2, $item?->linkedServicesV2 ?? collect()),
            ...$this->relatedLocationRows($item),
            [$this->faqsField($item)],
            ...$this->testimonialsRows($item),
            ...$this->contentSectionFields(9, withButton: true, editor: false),
            ...$this->metaRows($item),
            ...SeoHelper::form(),
        ];
    }

    protected function sectorForm(?Service $item): array
    {
        return [
            ...$this->baseHeroFields(),
            ...$this->contentSectionFields(2, withButton: true),
            ...$this->sectorFaqSectionFields(3, $item),
            ...$this->sectorFaqSectionFields(4, $item),
            ...$this->contentSectionFields(5, withButton: true),
            ...$this->contentSectionFields(6, withButton: true),
            ...$this->contentSectionFields(7, withButton: true, withImage: false),
            ...$this->contentSectionFields(8, withButton: true),
            ...$this->linkedServiceSectionFields(1, $item?->linkedServicesV1 ?? collect()),
            [$this->faqsField($item)],
            ...$this->relatedBlogRows($item),
            ...$this->linkedServiceSectionFields(2, $item?->linkedServicesV2 ?? collect()),
            ...$this->relatedLocationRows($item),
            ...$this->testimonialsRows($item),
            ...$this->contentSectionFields(9, withButton: true, editor: false),
            ...$this->metaRows($item),
            ...SeoHelper::form(),
        ];
    }

    protected function testimonialsRows(?Service $item): array
    {
        return [
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
        ];
    }

    protected function baseHeroFields(): array
    {
        return [
            [
                ['type' => 'text', 'label' => 'Title', 'name' => 'title', 'required' => true, 'col' => 12],
            ],
            [
                ['type' => 'text', 'label' => 'Banner Title', 'name' => 'banner_title', 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => 'Banner Description', 'name' => 'banner_description', 'editor' => false, 'col' => 12],
            ],
            [
                ['type' => 'image', 'label' => 'Banner Image', 'name' => 'banner_desktop', 'image_key' => 'banner_desktop', 'directory' => 'managed/services/banner_desktop', 'col' => 6],
                ['type' => 'image', 'label' => 'Thumbnail Image', 'name' => 'thumbnail', 'image_key' => 'thumbnail', 'directory' => 'managed/services/thumbnail', 'col' => 6],
            ],
        ];
    }

    protected function contentSectionFields(int $number, bool $withButton = false, bool $withImage = true, bool $editor = true): array
    {
        $rows = [
            [
                ['type' => 'text', 'label' => "Section {$number} Heading", 'name' => "section_{$number}_heading", 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => "Section {$number} Description", 'name' => "section_{$number}_description", 'editor' => $editor, 'col' => 12],
            ],
        ];

        if ($withImage) {
            $rows[] = [
                [
                    'type' => 'image',
                    'label' => "Section {$number} Image",
                    'name' => "section_{$number}_side_image",
                    'image_key' => "section_{$number}_side_image",
                    'directory' => "managed/services/section_{$number}_side_image",
                    'col' => 12,
                ],
            ];
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

    protected function sectorFaqSectionFields(int $number, ?Service $item): array
    {
        $relation = $number === 3 ? 'section3SectorFaqs' : 'section4SectorFaqs';

        return [
            [
                ['type' => 'text', 'label' => "Section {$number} Sectors FAQs Heading", 'name' => "section_{$number}_sectors_faqs[heading]", 'value' => data_get($item, "section_{$number}_sectors_faqs.heading"), 'col' => 6],
                ['type' => 'text', 'label' => "Section {$number} Sectors FAQs Button Name", 'name' => "section_{$number}_sectors_faqs[button_name]", 'value' => data_get($item, "section_{$number}_sectors_faqs.button_name"), 'col' => 6],
            ],
            [
                ['type' => 'textarea', 'label' => "Section {$number} Sectors FAQs Sub Description", 'name' => "section_{$number}_sectors_faqs[sub_description]", 'value' => data_get($item, "section_{$number}_sectors_faqs.sub_description"), 'editor' => false, 'col' => 6],
                ['type' => 'text', 'label' => "Section {$number} Sectors FAQs Button URL/Path", 'name' => "section_{$number}_sectors_faqs[button_url]", 'value' => data_get($item, "section_{$number}_sectors_faqs.button_url"), 'col' => 6],
            ],
            [[
                'type' => 'multi-select',
                'label' => "Section {$number} Sectors FAQs",
                'name' => "section_{$number}_sector_faq_ids",
                'options' => [],
                'selected' => $item?->{$relation}->pluck('id')->all() ?? [],
                'selected_labels' => AdminSelectLabel::faqs($item?->{$relation} ?? []),
                'ajax_url' => route('admin.ajax.search', 'faqs'),
                'ajax_create_url' => route('admin.faqs.quick-create'),
                'placeholder' => 'Search FAQs',
                'col' => 12,
            ]],
        ];
    }

    protected function relatedLocationRows(?Service $item): array
    {
        return [
            [
                ['type' => 'text', 'label' => 'Related Locations Heading', 'name' => 'related_locations_heading', 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => 'Related Locations Sub Heading', 'name' => 'related_locations_sub_heading', 'editor' => false, 'col' => 12],
            ],
            [$this->relatedLocationsField($item)],
        ];
    }

    protected function relatedBlogRows(?Service $item): array
    {
        return [
            [
                ['type' => 'text', 'label' => 'Related Blogs Heading', 'name' => 'related_blogs_heading', 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => 'Related Blogs Sub Heading', 'name' => 'related_blogs_sub_heading', 'editor' => false, 'col' => 12],
            ],
            [$this->relatedBlogsField($item)],
        ];
    }

    protected function metaRows(?Service $item): array
    {
        return [
            [
                ['type' => 'textarea', 'label' => 'Short Description', 'name' => 'short_description', 'editor' => false, 'col' => 12],
            ],
            [
                ['type' => 'textarea', 'label' => 'Card Description', 'name' => 'card_description', 'editor' => false, 'col' => 12],
            ],
            [
                ['type' => 'text', 'label' => 'Slug', 'name' => 'slug', 'col' => 12, 'value' => $item?->slug?->slug],
            ],
            [
                ['type' => 'select', 'label' => 'Featured', 'name' => 'is_featured', 'options' => ['1' => 'Yes', '0' => 'No'], 'col' => 6],
                ['type' => 'select', 'label' => 'Status', 'name' => 'status', 'options' => ['1' => 'Active', '0' => 'Inactive'], 'required' => true, 'col' => 6],
            ],
            [[
                'type' => 'select',
                'label' => 'Category',
                'name' => 'category_id',
                'options' => [],
                'selected' => $item?->categories->first()?->id,
                'selected_label' => AdminSelectLabel::category($item?->categories->first()),
                'ajax_url' => route('admin.ajax.search', $this->pageType().'-categories'),
                'placeholder' => 'Search '.$this->pageType().' categories',
                'required' => true,
                'col' => 12,
            ]],
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
            'placeholder' => 'Search services and sectors',
            'col' => 12,
        ];
    }

    protected function relatedLocationsField(?Service $item): array
    {
        return [
            'type' => 'multi-select',
            'label' => 'Related Locations',
            'name' => 'related_location_ids',
            'options' => [],
            'selected' => $item?->relatedLocations->pluck('id')->all() ?? [],
            'selected_labels' => AdminSelectLabel::locations($item?->relatedLocations ?? []),
            'ajax_url' => route('admin.ajax.search', 'locations'),
            'placeholder' => 'Search locations',
            'col' => 12,
        ];
    }

    protected function relatedBlogsField(?Service $item): array
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

    protected function faqsField(?Service $item): array
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

    protected function validateRequest(Request $request, ?Service $service = null): array
    {
        $pageType = $this->pageType();

        $validated = $request->validate([
            'title' => 'required|string|max:191',
            'slug' => 'nullable|string|max:191',
            'linked_service_v1_ids' => 'nullable|array',
            'linked_service_v1_ids.*' => 'integer|exists:services,id',
            'linked_services_v1_heading' => 'nullable|string|max:255',
            'linked_services_v1_sub_description' => 'nullable|string',
            'linked_service_v2_ids' => 'nullable|array',
            'linked_service_v2_ids.*' => 'integer|exists:services,id',
            'linked_services_v2_heading' => 'nullable|string|max:255',
            'linked_services_v2_sub_description' => 'nullable|string',
            'short_description' => 'nullable|string',
            'card_description' => 'nullable|string',
            'banner_title' => 'nullable|string|max:255',
            'banner_description' => 'nullable|string',
            'section_2_heading' => 'nullable|string|max:255',
            'section_2_description' => 'nullable|string',
            'section_2_button_name' => 'nullable|string|max:255',
            'section_2_button_url' => 'nullable|string|max:500',
            'section_3_heading' => 'nullable|string|max:255',
            'section_3_description' => 'nullable|string',
            'section_3_button_name' => 'nullable|string|max:255',
            'section_3_button_url' => 'nullable|string|max:500',
            'section_4_heading' => 'nullable|string|max:255',
            'section_4_description' => 'nullable|string',
            'section_5_heading' => 'nullable|string|max:255',
            'section_5_description' => 'nullable|string',
            'section_5_button_name' => 'nullable|string|max:255',
            'section_5_button_url' => 'nullable|string|max:500',
            'section_6_heading' => 'nullable|string|max:255',
            'section_6_description' => 'nullable|string',
            'section_6_button_name' => 'nullable|string|max:255',
            'section_6_button_url' => 'nullable|string|max:500',
            'section_7_heading' => 'nullable|string|max:255',
            'section_7_description' => 'nullable|string',
            'section_7_button_name' => 'nullable|string|max:255',
            'section_7_button_url' => 'nullable|string|max:500',
            'section_8_heading' => 'nullable|string|max:255',
            'section_8_description' => 'nullable|string',
            'section_8_button_name' => 'nullable|string|max:255',
            'section_8_button_url' => 'nullable|string|max:500',
            'related_locations_heading' => 'nullable|string|max:255',
            'related_locations_sub_heading' => 'nullable|string',
            'section_9_heading' => 'nullable|string|max:255',
            'section_9_description' => 'nullable|string',
            'section_9_button_name' => 'nullable|string|max:255',
            'section_9_button_url' => 'nullable|string|max:500',
            'related_blogs_heading' => 'nullable|string|max:255',
            'related_blogs_sub_heading' => 'nullable|string',
            'section_3_sectors_faqs' => 'nullable|array',
            'section_3_sectors_faqs.heading' => 'nullable|string|max:255',
            'section_3_sectors_faqs.sub_description' => 'nullable|string',
            'section_3_sectors_faqs.button_name' => 'nullable|string|max:255',
            'section_3_sectors_faqs.button_url' => 'nullable|string|max:500',
            'section_4_sectors_faqs' => 'nullable|array',
            'section_4_sectors_faqs.heading' => 'nullable|string|max:255',
            'section_4_sectors_faqs.sub_description' => 'nullable|string',
            'section_4_sectors_faqs.button_name' => 'nullable|string|max:255',
            'section_4_sectors_faqs.button_url' => 'nullable|string|max:500',
            'section_3_sector_faq_ids' => 'nullable|array',
            'section_3_sector_faq_ids.*' => 'integer|exists:faqs,id',
            'section_4_sector_faq_ids' => 'nullable|array',
            'section_4_sector_faq_ids.*' => 'integer|exists:faqs,id',
            'status' => 'required|boolean',
            'is_featured' => 'nullable|boolean',
            'testimonials_heading' => 'nullable|string|max:255',
            'testimonials_sub_heading' => 'nullable|string|max:255',
            'testimonial_ids' => 'nullable|array',
            'testimonial_ids.*' => 'integer|exists:reviews,id',
            ...ImageField::singleRules('banner_desktop', 4096),
            ...ImageField::singleRules('thumbnail', 2048),
            ...ImageField::singleRules('section_2_side_image', 4096),
            ...ImageField::singleRules('section_3_side_image', 4096),
            ...ImageField::singleRules('section_4_side_image', 4096),
            ...ImageField::singleRules('section_5_side_image', 4096),
            ...ImageField::singleRules('section_6_side_image', 4096),
            ...ImageField::singleRules('section_7_side_image', 4096),
            ...ImageField::singleRules('section_8_side_image', 4096),
            ...ImageField::singleRules('section_9_side_image', 4096),
            'category_id' => [
                'required',
                'integer',
                Rule::exists('categories', 'id')->where(fn ($query) => $query
                    ->where('type', 'service')
                    ->where('category_type', $pageType)
                    ->where('status', true)),
            ],
            'related_location_ids' => 'nullable|array',
            'related_location_ids.*' => 'integer|exists:locations,id',
            'related_blog_ids' => 'nullable|array',
            'related_blog_ids.*' => 'integer|exists:blogs,id',
            'faq_ids' => 'nullable|array',
            'faq_ids.*' => 'exists:faqs,id',
            ...SeoHelper::rules(),
        ]);

        $validated['linked_service_v1_ids'] = $this->sanitizeRelationIds($validated['linked_service_v1_ids'] ?? [], $service?->id);
        $validated['linked_service_v2_ids'] = $this->sanitizeRelationIds($validated['linked_service_v2_ids'] ?? [], $service?->id);
        $validated['related_location_ids'] = $this->sanitizeRelationIds($validated['related_location_ids'] ?? []);
        $validated['related_blog_ids'] = $this->sanitizeRelationIds($validated['related_blog_ids'] ?? []);
        $validated['section_3_sector_faq_ids'] = $this->sanitizeRelationIds($validated['section_3_sector_faq_ids'] ?? []);
        $validated['section_4_sector_faq_ids'] = $this->sanitizeRelationIds($validated['section_4_sector_faq_ids'] ?? []);

        return $validated;
    }

    protected function loadService(Service $service): Service
    {
        return $service->load([
            'slug',
            'seo',
            'images',
            'categories',
            'linkedServicesV1',
            'linkedServicesV2',
            'relatedLocations',
            'relatedBlogs',
            'faqs',
            'testimonials',
            'section3SectorFaqs',
            'section4SectorFaqs',
        ]);
    }

    protected function assertMatchesPageType(Service $service): void
    {
        $matches = $service->relationLoaded('categories')
            ? $service->categories->contains(fn (Category $category) => $category->type === 'service' && $category->category_type === $this->pageType())
            : $service->categories()
                ->where('type', 'service')
                ->where('category_type', $this->pageType())
                ->exists();

        abort_unless($matches, 404);
    }

    protected function pageType(): string
    {
        return request()->routeIs('admin.sector-pages.*') ? 'sector' : 'service';
    }

    protected function routePrefix(): string
    {
        if (request()->routeIs('admin.sector-pages.*')) {
            return 'admin.sector-pages';
        }

        if (request()->routeIs('admin.services.*')) {
            return 'admin.services';
        }

        return 'admin.service-pages';
    }

    protected function pageTitleSingular(): string
    {
        return $this->pageType() === 'sector' ? 'Sector Page' : 'Service Page';
    }

    protected function pageTitlePlural(): string
    {
        return $this->pageType() === 'sector' ? 'Sector Pages' : 'Service Pages';
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
