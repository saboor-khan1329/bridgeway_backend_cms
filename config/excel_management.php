<?php

use App\Models\Blog;
use App\Models\Category;
use App\Models\ContentBlock;
use App\Models\Faq;
use App\Models\Location;
use App\Models\Page;
use App\Models\Service;

$servicePageColumns = [
    'id', 'title', 'short_description', 'card_description',
    'banner_title', 'banner_description',
    'section_2_heading', 'section_2_description', 'section_2_button_name', 'section_2_button_url',
    'section_3_heading', 'section_3_description', 'section_3_button_name', 'section_3_button_url',
    'section_4_heading', 'section_4_description',
    'section_5_heading', 'section_5_description',
    'section_6_heading', 'section_6_description',
    'linked_services_v1_heading', 'linked_services_v1_sub_description',
    'section_7_heading', 'section_7_description', 'section_7_button_name', 'section_7_button_url',
    'section_8_heading', 'section_8_description', 'section_8_button_name', 'section_8_button_url',
    'linked_services_v2_heading', 'linked_services_v2_sub_description',
    'related_locations_heading', 'related_locations_sub_heading',
    'section_9_heading', 'section_9_description', 'section_9_button_name', 'section_9_button_url',
    'status', 'is_featured',
];

$sectorPageColumns = [
    'id', 'title', 'short_description', 'card_description',
    'banner_title', 'banner_description',
    'section_2_heading', 'section_2_description', 'section_2_button_name', 'section_2_button_url',
    'section_3_sectors_faqs', 'section_4_sectors_faqs',
    'section_5_heading', 'section_5_description', 'section_5_button_name', 'section_5_button_url',
    'section_6_heading', 'section_6_description', 'section_6_button_name', 'section_6_button_url',
    'section_7_heading', 'section_7_description', 'section_7_button_name', 'section_7_button_url',
    'section_8_heading', 'section_8_description', 'section_8_button_name', 'section_8_button_url',
    'linked_services_v1_heading', 'linked_services_v1_sub_description',
    'linked_services_v2_heading', 'linked_services_v2_sub_description',
    'related_blogs_heading', 'related_blogs_sub_heading',
    'related_locations_heading', 'related_locations_sub_heading',
    'section_9_heading', 'section_9_description', 'section_9_button_name', 'section_9_button_url',
    'status', 'is_featured',
];

$servicePageRelations = [
    'category_id' => ['relation' => 'categories', 'single' => true, 'constraints' => ['type' => 'service', 'category_type' => 'service']],
    'linked_service_v1_ids' => ['relation' => 'linkedServicesV1', 'pivot' => ['link_group' => 'v1']],
    'linked_service_v2_ids' => ['relation' => 'linkedServicesV2', 'pivot' => ['link_group' => 'v2']],
    'faq_ids' => ['relation' => 'faqs'],
    'related_location_ids' => ['relation' => 'relatedLocations'],
];

$sectorPageRelations = [
    'category_id' => ['relation' => 'categories', 'single' => true, 'constraints' => ['type' => 'service', 'category_type' => 'sector']],
    'linked_service_v1_ids' => ['relation' => 'linkedServicesV1', 'pivot' => ['link_group' => 'v1']],
    'linked_service_v2_ids' => ['relation' => 'linkedServicesV2', 'pivot' => ['link_group' => 'v2']],
    'faq_ids' => ['relation' => 'faqs'],
    'section_3_sector_faq_ids' => ['relation' => 'section3SectorFaqs', 'pivot' => ['section_key' => 'section_3']],
    'section_4_sector_faq_ids' => ['relation' => 'section4SectorFaqs', 'pivot' => ['section_key' => 'section_4']],
    'related_blog_ids' => ['relation' => 'relatedBlogs'],
    'related_location_ids' => ['relation' => 'relatedLocations'],
];

return [
    'resources' => [
        'service_pages' => [
            'label' => 'Service Pages',
            'model' => Service::class,
            'where_has' => ['categories' => ['type' => 'service', 'category_type' => 'service']],
            'lookup_fields' => ['id', 'title'],
            'columns' => $servicePageColumns,
            'relations' => $servicePageRelations,
            'json_columns' => [],
            'required' => ['title', 'category_id'],
        ],
        'sector_pages' => [
            'label' => 'Sector Pages',
            'model' => Service::class,
            'where_has' => ['categories' => ['type' => 'service', 'category_type' => 'sector']],
            'lookup_fields' => ['id', 'title'],
            'columns' => $sectorPageColumns,
            'relations' => $sectorPageRelations,
            'json_columns' => ['section_3_sectors_faqs', 'section_4_sectors_faqs'],
            'required' => ['title', 'category_id'],
        ],
        'services' => [
            'label' => 'Services Legacy Alias',
            'visible' => false,
            'model' => Service::class,
            'lookup_fields' => ['id', 'title'],
            'columns' => $servicePageColumns,
            'relations' => $servicePageRelations,
            'json_columns' => [],
            'required' => ['title'],
        ],
        'locations' => [
            'label' => 'Locations',
            'model' => Location::class,
            'lookup_fields' => ['id', 'title'],
            'columns' => [
                'id', 'parent_id', 'title', 'sub_heading', 'short_description', 'card_description',
                'description', 'banner_title', 'banner_description',
                'section_2_heading', 'section_2_description', 'section_2_button_name', 'section_2_button_url',
                'linked_services_v1_heading', 'linked_services_v1_sub_description',
                'section_3_locations_faqs', 'section_4_locations_faqs',
                'section_5_heading', 'section_5_description',
                'section_6_heading', 'section_6_description',
                'linked_child_locations_heading', 'linked_child_locations_sub_description',
                'section_7_heading', 'section_7_description',
                'section_8_heading', 'section_8_description',
                'linked_services_v2_heading', 'linked_services_v2_sub_description',
                'related_blogs_heading', 'related_blogs_sub_heading',
                'linked_services_v3_heading', 'linked_services_v3_sub_description',
                'linked_services_v4_heading', 'linked_services_v4_sub_description',
                'section_9_map_src', 'status', 'is_featured',
            ],
            'relations' => [
                'child_location_ids' => ['relation' => 'linkedChildren'],
                'faq_ids' => ['relation' => 'faqs'],
                'related_blog_ids' => ['relation' => 'relatedBlogs'],
                'linked_service_v1_ids' => ['relation' => 'linkedServicesV1', 'pivot' => ['link_group' => 'v1']],
                'linked_service_v2_ids' => ['relation' => 'linkedServicesV2', 'pivot' => ['link_group' => 'v2']],
                'linked_service_v3_ids' => ['relation' => 'linkedServicesV3', 'pivot' => ['link_group' => 'v3']],
                'linked_service_v4_ids' => ['relation' => 'linkedServicesV4', 'pivot' => ['link_group' => 'v4']],
                'section_3_location_faq_ids' => ['relation' => 'section3LocationFaqs', 'pivot' => ['section_key' => 'section_3']],
                'section_4_location_faq_ids' => ['relation' => 'section4LocationFaqs', 'pivot' => ['section_key' => 'section_4']],
            ],
            'json_columns' => ['section_3_locations_faqs', 'section_4_locations_faqs'],
            'required' => ['title'],
        ],
        // The four reusable page sections (feature cards, packages, cost
        // factors, process steps) that service, sector and location pages
        // share. Bulk editing matters here more than most: the same section
        // is written across many location pages, and doing that one admin
        // form at a time is the slowest job on the site.
        //
        // `items` and `options` are JSON, so a cell holds the whole repeatable
        // list. Export a page that is already right, copy its cell, and paste
        // it down the column — that is the intended workflow, and it is why
        // both are declared under json_columns rather than flattened into
        // dozens of item_1_title columns that would break the moment someone
        // added a fifth card.
        'page_sections' => [
            'label' => 'Page Sections (4 components)',
            'model' => ContentBlock::class,
            'lookup_fields' => ['id'],
            'columns' => [
                'id',
                // Friendly aliases for blockable_type / blockable_id. Accepts
                // service | sector | location; see ContentBlock::OWNER_TYPES.
                'owner_type', 'owner_id',
                'type', 'heading', 'intro', 'options', 'items',
                'sort_order', 'is_active',
                'owner_title',
            ],
            // Read-only helper so a human can tell which page a row belongs to
            // without cross-referencing ids. Ignored on import.
            'export_only_columns' => ['owner_title'],
            // Accessors, not database columns — see ContentBlock. Declaring
            // them keeps the export's SELECT valid.
            'computed_columns' => ['owner_type', 'owner_id', 'owner_title'],
            'relations' => [],
            'json_columns' => ['options', 'items'],
            'required' => ['owner_type', 'owner_id', 'type'],
        ],
        'blogs' => [
            'label' => 'Blogs',
            'model' => Blog::class,
            'lookup_fields' => ['id', 'title'],
            'columns' => ['id', 'title', 'author', 'author_user_id', 'published_at', 'short_description', 'excerpt', 'content', 'status'],
            'relations' => [
                'category_ids' => ['relation' => 'categories'],
                'related_location_ids' => ['relation' => 'relatedLocations'],
            ],
            'required' => ['title'],
        ],
        'categories' => [
            'label' => 'Categories',
            'model' => Category::class,
            'lookup_fields' => ['id', 'name'],
            'columns' => [
                'id', 'type', 'category_type', 'name', 'short_description',
                'sub_heading1', 'sub_heading_description', 'banner_title', 'banner_description',
                'section_cta_heading', 'section_cta_description', 'section_cta_button_name',
                'section_cta_button_url', 'status', 'is_featured',
            ],
            'relations' => [
                'faq_ids' => ['relation' => 'faqs'],
            ],
            'required' => ['type', 'name'],
        ],
        'pages' => [
            'label' => 'Pages',
            'model' => Page::class,
            'lookup_fields' => ['id', 'page_title'],
            'columns' => ['id', 'page_title', 'page_type', 'template_name', 'banner_title', 'page_content', 'status'],
            'relations' => [
                'service_ids' => ['relation' => 'services'],
                'location_ids' => ['relation' => 'locations'],
                'blog_ids' => ['relation' => 'blogs'],
                'category_ids' => ['relation' => 'categories'],
                'faq_ids' => ['relation' => 'faqs'],
            ],
            'required' => ['page_title'],
        ],
        'faqs' => [
            'label' => 'FAQs',
            'model' => Faq::class,
            'lookup_fields' => ['id', 'question'],
            'columns' => ['id', 'question', 'answer', 'status'],
            'relations' => [
                'service_ids' => ['relation' => 'services'],
                'location_ids' => ['relation' => 'locations'],
                'blog_ids' => ['relation' => 'blogs'],
                'category_ids' => ['relation' => 'categories'],
                'page_ids' => ['relation' => 'pages'],
            ],
            'required' => ['question'],
        ],
    ],
];
