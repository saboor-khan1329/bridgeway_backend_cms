<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Analytics row for one finder search.
 *
 * Written fire-and-forget from the UI. The visitor IP is stored only as a
 * salted hash so repeat-search rate analysis stays possible without keeping
 * personal data; rows are pruned on a retention schedule.
 */
class FinderSearch extends Model
{
    use HasFactory;

    /** How the query was resolved to an area. */
    public const MATCH_POSTCODE = 'postcode';

    public const MATCH_PLACE = 'place';

    public const MATCH_AREA_NAME = 'area_name';

    public const MATCH_NONE = 'none';

    public const MATCH_TYPES = [
        self::MATCH_POSTCODE,
        self::MATCH_PLACE,
        self::MATCH_AREA_NAME,
        self::MATCH_NONE,
    ];

    protected $fillable = [
        'query', 'postcode', 'matched_area_id', 'service_id',
        'match_type', 'results_count', 'ip_hash', 'user_agent',
    ];

    protected $casts = [
        'results_count' => 'integer',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(FinderArea::class, 'matched_area_id');
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    /** Searches that found nothing — the demand-gap report in admin. */
    public function scopeUnmatched($query)
    {
        return $query->where('match_type', self::MATCH_NONE);
    }
}
