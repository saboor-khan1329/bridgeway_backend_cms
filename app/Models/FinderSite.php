<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One operational site imported from the operations CSV.
 *
 * Site names identify real clients, so these rows are admin-only — the public
 * API exposes counts through FinderArea and never a site name or exact point.
 */
class FinderSite extends Model
{
    use HasFactory;

    public const SOURCE_IMPORT = 'csv_import';

    public const SOURCE_ADMIN = 'admin';

    /** Why a row is hidden from the public map. */
    public const REASON_NON_UK = 'non_uk';

    public const REASON_INVALID_POSTCODE = 'invalid_postcode';

    public const REASON_TEST_ROW = 'test_row';

    public const REASON_UNMAPPED_SERVICE = 'unmapped_service';

    public const REASON_ADMIN = 'admin';

    protected $fillable = [
        'finder_area_id', 'service_id', 'site_name', 'postcode_raw', 'postcode',
        'outcode', 'postcode_area', 'latitude', 'longitude', 'geocode_source',
        'is_active', 'inactive_reason', 'source', 'import_fingerprint',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(FinderArea::class, 'finder_area_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Rows an admin has touched are never replaced by a forced re-import. */
    public function scopeImported($query)
    {
        return $query->where('source', self::SOURCE_IMPORT);
    }
}
