<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A city-level coverage area on the Service Finder map.
 *
 * Areas aggregate imported sites by postcode-area prefix, so the public map
 * shows "Birmingham — 41 sites" rather than naming individual client sites.
 */
class FinderArea extends Model
{
    use HasFactory;

    public const SOURCE_IMPORT = 'import';

    public const SOURCE_ADMIN = 'admin';

    protected $fillable = [
        'name', 'slug', 'region', 'intro', 'postcode_areas', 'latitude', 'longitude',
        'centroid_is_locked', 'featured_service_id', 'location_id',
        'active_sites_count', 'is_active', 'sort_order', 'source',
    ];

    protected $casts = [
        'postcode_areas' => 'array',
        'centroid_is_locked' => 'boolean',
        'is_active' => 'boolean',
        'active_sites_count' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function sites(): HasMany
    {
        return $this->hasMany(FinderSite::class);
    }

    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Service::class, 'finder_area_service')
            ->withPivot([
                'description', 'related_service_ids', 'is_featured', 'is_active',
                'source', 'sites_count', 'sort_order',
            ])
            ->withTimestamps()
            ->orderByPivot('sort_order')
            ->orderByPivot('sites_count', 'desc');
    }

    /** Coverage rows an operator has published, for the public payload. */
    public function activeServices(): BelongsToMany
    {
        return $this->services()->wherePivot('is_active', true);
    }

    public function featuredService(): BelongsTo
    {
        return $this->belongsTo(Service::class, 'featured_service_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(FinderLead::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** True once the area can actually be drawn on the map. */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }
}
