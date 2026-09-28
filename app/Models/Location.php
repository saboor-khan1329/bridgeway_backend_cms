<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasImages;
use App\Traits\HasSeo;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Location extends Model
{
    use HasFactory, HasBooleanScopes, HasSeo, HasSlug, HasImages;

    protected $fillable = [
        'parent_id',
        'title',
        'sub_heading',
        'short_description',
        'card_description',
        'description',
        'banner_title',
        'banner_description',
        'section_2_heading',
        'section_2_description',
        'section_2_button_name',
        'section_2_button_url',
        'linked_services_v1_heading',
        'linked_services_v1_sub_description',
        'section_3_locations_faqs',
        'section_4_locations_faqs',
        'section_5_heading',
        'section_5_description',
        'section_6_heading',
        'section_6_description',
        'linked_child_locations_heading',
        'linked_child_locations_sub_description',
        'section_7_heading',
        'section_7_description',
        'section_8_heading',
        'section_8_description',
        'linked_services_v2_heading',
        'linked_services_v2_sub_description',
        'related_blogs_heading',
        'related_blogs_sub_heading',
        'linked_services_v3_heading',
        'linked_services_v3_sub_description',
        'linked_services_v4_heading',
        'linked_services_v4_sub_description',
        'section_9_map_src',
        'status',
        'is_featured',
        'testimonials_heading',
        'testimonials_sub_heading',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'section_3_locations_faqs' => 'array',
        'section_4_locations_faqs' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
    }

    public function linkedParents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'location_links',
            'child_location_id',
            'parent_location_id'
        )->orderBy('title');
    }

    public function linkedChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'location_links',
            'parent_location_id',
            'child_location_id'
        )->orderBy('title');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'locations_category');
    }

    public function relatedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'location_service')
            ->orderBy('title');
    }

    public function linkedServicesV1(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v1');
    }

    public function linkedServicesV2(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v2');
    }

    public function linkedServicesV3(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v3');
    }

    public function linkedServicesV4(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v4');
    }

    public function relatedBlogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_location')
            ->orderBy('title');
    }

    public function faqs(): MorphToMany
    {
        return $this->morphToMany(Faq::class, 'faqable')
            ->orderBy('faqs.id');
    }

    public function testimonials(): MorphToMany
    {
        return $this->morphToMany(Review::class, 'reviewable')
            ->orderBy('reviews.id');
    }

    /**
     * Admin-authored sections (feature cards, packages, cost factors, process
     * steps) — the same four components the service and sector templates use.
     */
    public function contentBlocks(): MorphMany
    {
        return $this->morphMany(ContentBlock::class, 'blockable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function section3LocationFaqs(): BelongsToMany
    {
        return $this->locationFaqsBySection('section_3');
    }

    public function section4LocationFaqs(): BelongsToMany
    {
        return $this->locationFaqsBySection('section_4');
    }

    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable');
    }

    protected function linkedServicesByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'location_linked_services',
            'location_id',
            'linked_service_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('title');
    }

    protected function locationFaqsBySection(string $section): BelongsToMany
    {
        return $this->belongsToMany(Faq::class, 'location_section_faqs')
            ->wherePivot('section_key', $section)
            ->withPivot('section_key')
            ->orderBy('faqs.id');
    }
}
