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

class Service extends Model
{
    use HasFactory, HasBooleanScopes, HasSeo, HasSlug, HasImages;

    protected static function booted(): void
    {
        static::saved(fn () => \App\Support\FrontendCache::bump());
        static::deleted(fn () => \App\Support\FrontendCache::bump());
    }

    protected $fillable = [
        'parent_id',
        'title',
        'short_description',
        'card_description',
        'banner_title',
        'banner_description',
        'section_2_heading',
        'section_2_description',
        'section_2_button_name',
        'section_2_button_url',
        'section_3_heading',
        'section_3_description',
        'section_3_button_name',
        'section_3_button_url',
        'section_4_heading',
        'section_4_description',
        'section_5_heading',
        'section_5_description',
        'section_5_button_name',
        'section_5_button_url',
        'section_6_heading',
        'section_6_description',
        'section_6_button_name',
        'section_6_button_url',
        'linked_services_v1_heading',
        'linked_services_v1_sub_description',
        'section_7_heading',
        'section_7_description',
        'section_7_button_name',
        'section_7_button_url',
        'section_8_heading',
        'section_8_description',
        'section_8_button_name',
        'section_8_button_url',
        'linked_services_v2_heading',
        'linked_services_v2_sub_description',
        'related_locations_heading',
        'related_locations_sub_heading',
        'section_9_heading',
        'section_9_description',
        'section_9_button_name',
        'section_9_button_url',
        'related_blogs_heading',
        'related_blogs_sub_heading',
        'linked_services_v3_heading',
        'linked_services_v3_sub_description',
        'section_3_sectors_faqs',
        'section_4_sectors_faqs',
        'status',
        'is_featured',
        'testimonials_heading',
        'testimonials_sub_heading',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'section_3_sectors_faqs' => 'array',
        'section_4_sectors_faqs' => 'array',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('title');
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

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'services_category');
    }

    public function getCategoriesListAttribute(): string
    {
        $categories = $this->relationLoaded('categories')
            ? $this->categories
            : $this->categories()->with('slug')->get();

        return $categories
            ->map(function (Category $category) {
                $label = trim((string) $category->name);
                $slug = $category->slug?->slug;

                return $slug ? "{$label} ({$slug})" : $label;
            })
            ->filter()
            ->implode(', ');
    }

    public function relatedLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'location_service')
            ->orderBy('title');
    }

    public function relatedBlogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blog_service')
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
     * steps). Covers sector pages too — they are Services with a different
     * page_type, so both templates read the same relation.
     */
    public function contentBlocks(): MorphMany
    {
        return $this->morphMany(ContentBlock::class, 'blockable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function section3SectorFaqs(): BelongsToMany
    {
        return $this->sectorFaqsBySection('section_3');
    }

    public function section4SectorFaqs(): BelongsToMany
    {
        return $this->sectorFaqsBySection('section_4');
    }

    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable');
    }

    protected function linkedServicesByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'service_linked_services',
            'service_id',
            'linked_service_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('title');
    }

    protected function sectorFaqsBySection(string $section): BelongsToMany
    {
        return $this->belongsToMany(Faq::class, 'service_sector_faqs')
            ->wherePivot('section_key', $section)
            ->withPivot('section_key')
            ->orderBy('faqs.id');
    }
}
