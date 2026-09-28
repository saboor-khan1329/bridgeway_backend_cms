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
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use App\Support\FrontendCache;

class Blog extends Model
{
    use HasFactory, HasBooleanScopes, HasSeo, HasSlug, HasImages;

    protected $fillable = [
        'title',
        'short_description',
        'excerpt',
        'content',
        'author',
        'author_user_id',
        'published_at',
        'status',
        'is_featured',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_featured' => 'boolean',
        'published_at' => 'datetime',
    ];

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'blogs_category');
    }

    public function authorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_user_id');
    }

    public function authorName(): ?string
    {
        if ($this->authorUser) {
            return $this->authorUser->publicName();
        }

        return $this->author ?: null;
    }

    public function getAuthorNameAttribute(): ?string
    {
        return $this->authorName();
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

    public function linkedParents(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'blog_links',
            'child_blog_id',
            'parent_blog_id'
        )->orderBy('title');
    }

    public function linkedChildren(): BelongsToMany
    {
        return $this->belongsToMany(
            self::class,
            'blog_links',
            'parent_blog_id',
            'child_blog_id'
        )->orderBy('title');
    }

    public function relatedServices(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'blog_service')
            ->orderBy('title');
    }

    public function relatedLocations(): BelongsToMany
    {
        return $this->belongsToMany(Location::class, 'blog_location')
            ->orderBy('title');
    }

    public function faqs(): MorphToMany
    {
        return $this->morphToMany(Faq::class, 'faqable')
            ->orderBy('faqs.id');
    }

    public function pages(): MorphToMany
    {
        return $this->morphToMany(Page::class, 'pageable');
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::bump());
        static::deleted(fn () => FrontendCache::bump());
    }
}
