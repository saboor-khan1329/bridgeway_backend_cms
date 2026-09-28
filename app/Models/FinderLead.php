<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A quote request captured by the Service Finder popup.
 *
 * Kept separate from Inquiry because the popup collects a different shape
 * (postcode/area, start date, originating gateway, matched coverage area)
 * and carries no free-text message, which Inquiry requires.
 */
class FinderLead extends Model
{
    use HasFactory;

    public const STATUS_NEW = 'new';

    public const STATUS_CONTACTED = 'contacted';

    public const STATUS_QUOTED = 'quoted';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_SPAM = 'spam';

    public const STATUSES = [
        self::STATUS_NEW,
        self::STATUS_CONTACTED,
        self::STATUS_QUOTED,
        self::STATUS_CLOSED,
        self::STATUS_SPAM,
    ];

    /** Which gateway in the finder produced the lead (docx gateways 1-3). */
    public const ORIGIN_SEARCH = 'search';

    public const ORIGIN_RESULT_CARD = 'result_card';

    public const ORIGIN_PIN_POPUP = 'pin_popup';

    public const ORIGIN_NO_COVERAGE = 'no_coverage';

    /**
     * Gateways outside the finder itself: the package cards and the cost-factor
     * section on service, sector and location pages open the same quote dialog,
     * so their leads land in this same table and are told apart by origin.
     */
    public const ORIGIN_PACKAGE_CARD = 'package_card';

    public const ORIGIN_CONTENT_BLOCK = 'content_block';

    /** The "Your Security Starts With a Quick Quote" form on the home page. */
    public const ORIGIN_HOME_FORM = 'home_form';

    public const ORIGINS = [
        self::ORIGIN_SEARCH,
        self::ORIGIN_RESULT_CARD,
        self::ORIGIN_PIN_POPUP,
        self::ORIGIN_NO_COVERAGE,
        self::ORIGIN_PACKAGE_CARD,
        self::ORIGIN_CONTENT_BLOCK,
        self::ORIGIN_HOME_FORM,
    ];

    protected $fillable = [
        'name', 'phone', 'company', 'email', 'service_id', 'service_label',
        'finder_area_id', 'postcode_or_area', 'start_date', 'origin', 'status',
        'spam_score', 'spam_reasons', 'captcha_passed', 'meta', 'read_at',
    ];

    protected $casts = [
        'spam_reasons' => 'array',
        'meta' => 'array',
        'captcha_passed' => 'boolean',
        'spam_score' => 'integer',
        'read_at' => 'datetime',
    ];

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(FinderArea::class, 'finder_area_id');
    }

    public function scopeNew($query)
    {
        return $query->where('status', self::STATUS_NEW);
    }

    public function markAsRead(): void
    {
        if ($this->read_at === null) {
            $this->forceFill(['read_at' => now()])->save();
        }
    }
}
