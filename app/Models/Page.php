<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasImages;
use App\Traits\HasSeo;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Support\Str;

class Page extends Model
{
    use HasFactory, HasBooleanScopes, HasSeo, HasSlug, HasImages;

    public const PAGE_TYPE_MAX_LENGTH = 64;
    public const TEMPLATE_STATIC_V1 = 'static_v1';
    public const TEMPLATES = [
        'static_v1',
        'static_v2',
        'static_v3',
        'static_v4',
        'static_v5',
        'static_v6',
        'static_v7',
        'static_v8',
        'static_v9',
        'static_v10',
    ];

    protected $fillable = [
        'page_title',
        'page_type',
        'template_name',
        'last_updated_at',
        'banner_title',
        'banner_description',
        'banner_short_description',
        'button1_name',
        'button1_link',
        'button2_name',
        'button2_link',
        'linked_services_v1_heading',
        'linked_services_v1_sub_description',
        'linked_services_v1_button_name',
        'linked_services_v1_button_url',
        'linked_services_v2_heading',
        'linked_services_v2_sub_description',
        'linked_services_v2_button_name',
        'linked_services_v2_button_url',
        'linked_services_v3_heading',
        'linked_services_v3_sub_description',
        'linked_services_v3_button_name',
        'linked_services_v3_button_url',
        'linked_services_v4_heading',
        'linked_services_v4_sub_description',
        'linked_services_v4_button_name',
        'linked_services_v4_button_url',
        'linked_services_v5_heading',
        'linked_services_v5_sub_description',
        'linked_services_v5_button_name',
        'linked_services_v5_button_url',
        'linked_services_v6_heading',
        'linked_services_v6_sub_description',
        'linked_services_v6_button_name',
        'linked_services_v6_button_url',
        'linked_locations_v1_heading',
        'linked_locations_v1_sub_description',
        'linked_locations_v1_button_name',
        'linked_locations_v1_button_url',
        'linked_locations_v2_heading',
        'linked_locations_v2_sub_description',
        'linked_locations_v2_button_name',
        'linked_locations_v2_button_url',
        'linked_locations_v3_heading',
        'linked_locations_v3_sub_description',
        'linked_locations_v3_button_name',
        'linked_locations_v3_button_url',
        'linked_locations_v4_heading',
        'linked_locations_v4_sub_description',
        'linked_locations_v4_button_name',
        'linked_locations_v4_button_url',
        'linked_locations_v5_heading',
        'linked_locations_v5_sub_description',
        'linked_locations_v5_button_name',
        'linked_locations_v5_button_url',
        'linked_locations_v6_heading',
        'linked_locations_v6_sub_description',
        'linked_locations_v6_button_name',
        'linked_locations_v6_button_url',
        'linked_faqs_v1_heading',
        'linked_faqs_v1_sub_description',
        'linked_faqs_v1_button_name',
        'linked_faqs_v1_button_url',
        'linked_faqs_v2_heading',
        'linked_faqs_v2_sub_description',
        'linked_faqs_v2_button_name',
        'linked_faqs_v2_button_url',
        'linked_faqs_v3_heading',
        'linked_faqs_v3_sub_description',
        'linked_faqs_v3_button_name',
        'linked_faqs_v3_button_url',
        'linked_faqs_v4_heading',
        'linked_faqs_v4_sub_description',
        'linked_faqs_v4_button_name',
        'linked_faqs_v4_button_url',
        'linked_faqs_v5_heading',
        'linked_faqs_v5_sub_description',
        'linked_faqs_v5_button_name',
        'linked_faqs_v5_button_url',
        'linked_faqs_v6_heading',
        'linked_faqs_v6_sub_description',
        'linked_faqs_v6_button_name',
        'linked_faqs_v6_button_url',
        'linked_blogs_v1_heading',
        'linked_blogs_v1_sub_description',
        'linked_blogs_v1_button_name',
        'linked_blogs_v1_button_url',
        'linked_blogs_v2_heading',
        'linked_blogs_v2_sub_description',
        'linked_blogs_v2_button_name',
        'linked_blogs_v2_button_url',
        'linked_blogs_v3_heading',
        'linked_blogs_v3_sub_description',
        'linked_blogs_v3_button_name',
        'linked_blogs_v3_button_url',
        'linked_blogs_v4_heading',
        'linked_blogs_v4_sub_description',
        'linked_blogs_v4_button_name',
        'linked_blogs_v4_button_url',
        'linked_blogs_v5_heading',
        'linked_blogs_v5_sub_description',
        'linked_blogs_v5_button_name',
        'linked_blogs_v5_button_url',
        'linked_blogs_v6_heading',
        'linked_blogs_v6_sub_description',
        'linked_blogs_v6_button_name',
        'linked_blogs_v6_button_url',
        'page_content',
        'status',
        'testimonials_heading',
        'testimonials_sub_heading',
    ];

    protected $casts = [
        'last_updated_at' => 'datetime',
        'status' => 'boolean',
    ];

    public static function templateOptions(): array
    {
        return collect(self::TEMPLATES)
            ->mapWithKeys(fn (string $template) => [$template => strtoupper(str_replace('_', ' ', $template))])
            ->all();
    }

    public static function normalizeType(?string $type): ?string
    {
        $normalized = Str::of((string) $type)
            ->squish()
            ->lower()
            ->replace(' ', '_')
            ->replaceMatches('/[^a-z0-9_-]/', '')
            ->trim('_-')
            ->limit(self::PAGE_TYPE_MAX_LENGTH, '')
            ->toString();

        return $normalized !== '' ? $normalized : null;
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

    public function linkedServicesV5(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v5');
    }

    public function linkedServicesV6(): BelongsToMany
    {
        return $this->linkedServicesByGroup('v6');
    }

    public function linkedLocationsV1(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v1');
    }

    public function linkedLocationsV2(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v2');
    }

    public function linkedLocationsV3(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v3');
    }

    public function linkedLocationsV4(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v4');
    }

    public function linkedLocationsV5(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v5');
    }

    public function linkedLocationsV6(): BelongsToMany
    {
        return $this->linkedLocationsByGroup('v6');
    }

    public function linkedFaqsV1(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v1');
    }

    public function linkedFaqsV2(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v2');
    }

    public function linkedFaqsV3(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v3');
    }

    public function linkedFaqsV4(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v4');
    }

    public function linkedFaqsV5(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v5');
    }

    public function linkedFaqsV6(): BelongsToMany
    {
        return $this->linkedFaqsByGroup('v6');
    }

    public function linkedBlogsV1(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v1');
    }

    public function linkedBlogsV2(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v2');
    }

    public function linkedBlogsV3(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v3');
    }

    public function linkedBlogsV4(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v4');
    }

    public function linkedBlogsV5(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v5');
    }

    public function linkedBlogsV6(): BelongsToMany
    {
        return $this->linkedBlogsByGroup('v6');
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'pageable')
            ->orderBy('services.title');
    }

    public function locations(): MorphToMany
    {
        return $this->morphedByMany(Location::class, 'pageable')
            ->orderBy('locations.title');
    }

    public function blogs(): MorphToMany
    {
        return $this->morphedByMany(Blog::class, 'pageable')
            ->orderBy('blogs.title');
    }

    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'pageable')
            ->orderBy('categories.name');
    }

    protected function linkedServicesByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            Service::class,
            'page_linked_services',
            'page_id',
            'linked_service_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('title');
    }

    protected function linkedLocationsByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            Location::class,
            'page_linked_locations',
            'page_id',
            'linked_location_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('title');
    }

    protected function linkedFaqsByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            Faq::class,
            'page_linked_faqs',
            'page_id',
            'faq_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('faqs.id');
    }

    protected function linkedBlogsByGroup(string $group): BelongsToMany
    {
        return $this->belongsToMany(
            Blog::class,
            'page_linked_blogs',
            'page_id',
            'linked_blog_id'
        )
            ->wherePivot('link_group', $group)
            ->withPivot('link_group')
            ->orderBy('title');
    }
}
