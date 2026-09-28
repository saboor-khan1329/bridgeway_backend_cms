<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class NavigationMenuItem extends Model
{
    // ── Link type constants ───────────────────────────────────────────────────

    public const LINK_TYPE_CUSTOM   = 'custom';
    public const LINK_TYPE_SERVICE  = 'service';
    public const LINK_TYPE_CATEGORY = 'category';
    public const LINK_TYPE_LOCATION = 'location';
    public const LINK_TYPE_BLOG     = 'blog';
    public const LINK_TYPE_PAGE     = 'page';

    public const LINK_TYPES = [
        self::LINK_TYPE_CUSTOM,
        self::LINK_TYPE_SERVICE,
        self::LINK_TYPE_CATEGORY,
        self::LINK_TYPE_LOCATION,
        self::LINK_TYPE_BLOG,
        self::LINK_TYPE_PAGE,
    ];

    /** Maps link_type → Eloquent model class */
    public const LINKABLE_MODELS = [
        self::LINK_TYPE_SERVICE  => Service::class,
        self::LINK_TYPE_CATEGORY => Category::class,
        self::LINK_TYPE_LOCATION => Location::class,
        self::LINK_TYPE_BLOG     => Blog::class,
        self::LINK_TYPE_PAGE     => Page::class,
    ];

    // ──────────────────────────────────────────────────────────────────────────

    protected $fillable = [
        'menu_id', 'parent_id', 'item_type',
        'link_type', 'linkable_type', 'linkable_id',
        'title', 'href', 'target', 'meta', 'sort_order', 'status',
    ];

    protected $casts = [
        'meta'       => 'array',
        'status'     => 'boolean',
        'sort_order' => 'integer',
    ];

    // ── Relationships ─────────────────────────────────────────────────────────

    public function menu(): BelongsTo
    {
        return $this->belongsTo(NavigationMenu::class, 'menu_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** Polymorphic link to a content model (Service, Category, Location, Blog) */
    public function linkable(): MorphTo
    {
        return $this->morphTo();
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $key, $default);
    }

    public function isCustomLink(): bool
    {
        return ($this->link_type ?? self::LINK_TYPE_CUSTOM) === self::LINK_TYPE_CUSTOM;
    }

    /**
     * Returns the model class that should be linked for this item's link_type.
     * Returns null for 'custom' type.
     */
    public static function modelClassForType(string $linkType): ?string
    {
        return self::LINKABLE_MODELS[$linkType] ?? null;
    }

    // ── Model events ─────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::bump());
        static::deleted(fn () => FrontendCache::bump());

        static::deleting(function (self $item) {
            $item->children()->each(fn ($child) => $child->delete());
        });
    }
}
