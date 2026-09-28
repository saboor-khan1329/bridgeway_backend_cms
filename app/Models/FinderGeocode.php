<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Permanent postcode → coordinate cache.
 *
 * Every lookup the import performs is written here, so re-runs and future
 * admin-added sites resolve locally and never spend a geocoding call. Holds
 * both full postcodes and bare outcodes, plus negative results (status
 * not_found) so unresolvable postcodes are not retried forever.
 */
class FinderGeocode extends Model
{
    use HasFactory;

    public const STATUS_OK = 'ok';

    public const STATUS_NOT_FOUND = 'not_found';

    public const SOURCE_POSTCODES_IO = 'postcodes_io';

    /** Retired postcode resolved from the terminated register. */
    public const SOURCE_POSTCODES_IO_TERMINATED = 'postcodes_io_terminated';

    public const SOURCE_POSTCODES_IO_OUTCODE = 'postcodes_io_outcode';

    public const SOURCE_GOOGLE = 'google';

    protected $fillable = [
        'postcode', 'latitude', 'longitude', 'postcode_area',
        'region', 'source', 'status', 'payload',
    ];

    protected $casts = [
        'payload' => 'array',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_OK
            && $this->latitude !== null
            && $this->longitude !== null;
    }
}
