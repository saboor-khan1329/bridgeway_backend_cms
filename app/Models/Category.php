<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use App\Services\CategoryTreeService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasImages;
use App\Traits\HasSeo;
use App\Traits\HasSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Category extends Model
{
    use HasFactory, HasBooleanScopes, HasSeo, HasSlug, HasImages;

    public const TYPES = ['service', 'location', 'blog'];
    public const SERVICE_CATEGORY_TYPES = ['service', 'sector'];

    protected $fillable = [
        'type',
        'category_type',
        'name',
        'parent_id',
        'depth',
        'short_description',
        'sub_heading1',
        'sub_heading_description',
        'banner_title',
        'banner_description',
        'section_cta_heading',
        'section_cta_description',
        'section_cta_button_name',
        'section_cta_button_url',
        'is_featured',
        'status',
        'testimonials_heading',
        'testimonials_sub_heading',
    ];

    protected $casts = [
        'is_featured' => 'boolean',
        'status' => 'boolean',
        'depth' => 'integer',
    ];

    public function scopeOfType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('name');
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'services_category');
    }

    public function locations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'locations_category');
    }

    public function blogs(): BelongsToMany
    {
        return $this->belongsToMany(Blog::class, 'blogs_category');
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

    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable');
    }

    public function scopeLeaf(Builder $query): Builder
    {
        return $query->whereDoesntHave('children');
    }

    public function isRoot(): bool
    {
        return is_null($this->parent_id);
    }

    public function isLeaf(): bool
    {
        return $this->children()->count() === 0;
    }

    protected static function booted(): void
    {
        static::created(function (self $category) {
            CategoryTreeService::attach($category);
        });

        static::updated(function (self $category) {
            if ($category->wasChanged('parent_id')) {
                CategoryTreeService::rebuild($category);
            }
        });
    }
}
