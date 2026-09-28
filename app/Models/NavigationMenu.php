<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NavigationMenu extends Model
{
    protected $fillable = ['name', 'location', 'meta', 'status'];

    protected $casts = [
        'meta'   => 'array',
        'status' => 'boolean',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class, 'menu_id');
    }

    public function rootItems(): HasMany
    {
        return $this->hasMany(NavigationMenuItem::class, 'menu_id')
            ->whereNull('parent_id')
            ->orderBy('sort_order');
    }

    public static function forLocation(string $location): self
    {
        return static::firstOrCreate(
            ['location' => $location],
            ['name' => ucfirst($location).' Navigation', 'meta' => [], 'status' => true]
        );
    }

    public function getMeta(string $key, mixed $default = null): mixed
    {
        return data_get($this->meta ?? [], $key, $default);
    }

    public function putMeta(string $key, mixed $value): void
    {
        $meta       = $this->meta ?? [];
        $meta[$key] = $value;
        $this->meta = $meta;
    }

    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::bump());
        static::deleted(fn () => FrontendCache::bump());
    }
}
