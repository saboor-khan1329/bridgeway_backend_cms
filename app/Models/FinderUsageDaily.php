<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One counter: how many times `event` happened on `date`.
 *
 * A daily rollup rather than one row per call — the same information for
 * graphing, at a fraction of the storage of an event-log table that a busy
 * site would otherwise accumulate forever.
 */
class FinderUsageDaily extends Model
{
    /** Every SKU this app can trigger at runtime, and what triggers it. */
    public const MAP_LOAD = 'map_load';

    public const AUTOCOMPLETE_SESSION = 'autocomplete_session';

    public const EVENTS = [
        self::MAP_LOAD => 'Map loads',
        self::AUTOCOMPLETE_SESSION => 'Address autocomplete sessions',
    ];

    protected $table = 'finder_usage_daily';

    protected $fillable = ['date', 'event', 'count'];

    protected $casts = [
        'date' => 'date',
        'count' => 'integer',
    ];
}
