<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Support\ContentFieldStore;

/**
 * An admin-authored section on a service, sector or location page.
 *
 * Four components share this one model. What separates them is `type`, and
 * `type` alone decides which frontend component renders the row and which
 * fields the admin form shows — see TYPES below, which is the single source
 * of truth for both. Adding a component means adding an entry there.
 *
 * Nothing renders until a content editor fills one in: the API omits blocks
 * with no usable items, so every page behaves exactly as it did before this
 * feature existed until someone deliberately adds content.
 */
class ContentBlock extends Model
{
    use HasFactory;

    private ?array $pendingCmsData = null;

    public const TYPE_FEATURE_CARDS = 'feature_cards';
    public const TYPE_PACKAGES = 'packages';
    public const TYPE_COST_FACTORS = 'cost_factors';
    public const TYPE_PROCESS_STEPS = 'process_steps';

    /**
     * Section background tints.
     *
     * Empty means white, which is the default and by far the commonest — the
     * site alternates tinted and plain panels, so a tint is a deliberate
     * choice made in relation to the sections either side, never a default.
     * The values are the site's existing utility classes, so these sections
     * tint exactly like every other section rather than inventing a parallel
     * set of colours.
     */
    public const BACKGROUNDS = [
        '' => 'White (default)',
        'off-white' => 'Off white',
        'light-red' => 'Light red',
    ];

    /**
     * The component registry.
     *
     * `fields` describes one repeatable item; the admin editor builds its
     * inputs from this and the API validates against it, so the two can never
     * drift apart. `options` describes the non-repeatable extras.
     *
     * Field types: text | textarea | image | list (a simple array of strings).
     */
    public const TYPES = [
        self::TYPE_FEATURE_CARDS => [
            'label' => 'Feature Cards',
            'help' => 'A scrollable row of image cards — an image, a title and a short paragraph each. Used for "What Your Installation Can Include".',
            'min_items' => 1,
            'max_items' => 12,
            'fields' => [
                'image' => ['type' => 'image', 'label' => 'Card image', 'required' => false],
                'title' => ['type' => 'text', 'label' => 'Card title', 'required' => true, 'max' => 160],
                'description' => ['type' => 'textarea', 'label' => 'Card text', 'required' => false, 'max' => 600],
            ],
            'options' => [
                'background' => [
                    'type' => 'select', 'label' => 'Section background',
                    'help' => 'White unless you pick a tint. Match the sections above and below so two tinted panels never sit together.',
                    'default' => '',
                    'choices' => self::BACKGROUNDS,
                ],
            ],
        ],

        self::TYPE_PACKAGES => [
            'label' => 'Packages / Tiers',
            'help' => 'Side-by-side package cards, each with a tick list and a quote button. The button opens the quote form with this package pre-filled.',
            'min_items' => 1,
            'max_items' => 6,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Package name', 'required' => true, 'max' => 160],
                'description' => ['type' => 'textarea', 'label' => 'Who it suits', 'required' => false, 'max' => 600],
                'features' => ['type' => 'list', 'label' => 'What is included (one per line)', 'required' => false, 'max_items' => 20],
            ],
            'options' => [
                'cta_label' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Get an Accurate Quote', 'max' => 60],
                'background' => [
                    'type' => 'select', 'label' => 'Section background',
                    'help' => 'White unless you pick a tint. Match the sections above and below so two tinted panels never sit together.',
                    'default' => '',
                    'choices' => self::BACKGROUNDS,
                ],
            ],
        ],

        self::TYPE_COST_FACTORS => [
            'label' => 'Cost Factors Table',
            'help' => 'A two-column table of what affects a quote, with an intro and a call-to-action button beside it.',
            'min_items' => 1,
            'max_items' => 20,
            'fields' => [
                'factor' => ['type' => 'text', 'label' => 'Factor', 'required' => true, 'max' => 160],
                'detail' => ['type' => 'textarea', 'label' => 'What can affect the quote', 'required' => false, 'max' => 600],
            ],
            'options' => [
                'head_left' => ['type' => 'text', 'label' => 'Left column heading', 'default' => 'Cost Factor', 'max' => 60],
                'head_right' => ['type' => 'text', 'label' => 'Right column heading', 'default' => 'What Can Affect the Quote', 'max' => 60],
                'cta_label' => ['type' => 'text', 'label' => 'Button text', 'default' => 'Get an Accurate Quote', 'max' => 60],
                'background' => [
                    'type' => 'select', 'label' => 'Section background',
                    'help' => 'White unless you pick a tint. Match the sections above and below so two tinted panels never sit together.',
                    'default' => '',
                    'choices' => self::BACKGROUNDS,
                ],
            ],
        ],

        self::TYPE_PROCESS_STEPS => [
            'label' => 'Numbered Process Steps',
            'help' => 'A numbered grid explaining how the work is carried out, step by step. Numbering follows the order below automatically.',
            'min_items' => 1,
            'max_items' => 12,
            'fields' => [
                'title' => ['type' => 'text', 'label' => 'Step title', 'required' => true, 'max' => 160],
                'description' => ['type' => 'textarea', 'label' => 'Step text', 'required' => false, 'max' => 600],
            ],
            'options' => [
                'background' => [
                    'type' => 'select', 'label' => 'Section background',
                    'help' => 'White unless you pick a tint. Match the sections above and below so two tinted panels never sit together.',
                    'default' => '',
                    'choices' => self::BACKGROUNDS,
                ],
            ],
        ],
    ];

    protected $fillable = [
        'blockable_type',
        'blockable_id',
        'type',
        'section_key',
        'heading',
        'intro',
        'items',
        'options',
        'sort_order',
        'is_active',
        // Virtual compatibility input. It is normalized into
        // content_block_fields after save; no JSON column is used.
        'data',
        // Spreadsheet-friendly aliases for the polymorphic pair above. See
        // the accessor pair at the foot of this class.
        'owner_type',
        'owner_id',
    ];

    /**
     * The page types a section can belong to, as an editor names them.
     *
     * Service and sector pages are the same model underneath — a sector is a
     * Service whose category is of type 'sector' — so both map to the same
     * class and are told apart by that category, not by a separate table.
     */
    public const OWNER_TYPES = [
        'service' => Service::class,
        'sector' => Service::class,
        'location' => Location::class,
        'page' => ContentPage::class,
    ];

    protected $casts = [
        'items' => 'array',
        'options' => 'array',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function blockable(): MorphTo
    {
        return $this->morphTo();
    }

    public function fields(): HasMany
    {
        return $this->hasMany(ContentBlockField::class)->orderBy('sort_order')->orderBy('id');
    }

    /** The nested frontend shape reconstructed from typed CMS field rows. */
    public function cmsData(): array
    {
        return ContentFieldStore::read($this);
    }

    public function getDataAttribute(): array
    {
        return $this->cmsData();
    }

    public function setDataAttribute(mixed $value): void
    {
        $this->pendingCmsData = is_array($value) ? $value : [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeOfType($query, string $type)
    {
        return $query->where('type', $type);
    }

    /** Human label for this block's component, for admin listings. */
    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    /**
     * Whether this block has anything worth rendering.
     *
     * A heading on its own is not content — the components are built around
     * their items, and a lone heading would draw an empty section. This is
     * the check the API uses before including a block at all.
     *
     * A page section carries one nested payload in `data` rather than a list
     * of rows, so a filled `data` counts as content on its own. The four
     * original components never populate `data`, so their behaviour here is
     * exactly what it was.
     */
    public function hasContent(): bool
    {
        return count($this->usableItems()) > 0 || $this->hasData();
    }

    /** Whether `data` holds a non-empty payload. */
    public function hasData(): bool
    {
        return $this->cmsData() !== [];
    }

    /**
     * Items with the blank rows dropped.
     *
     * The admin editor always posts a fixed number of rows so an editor can
     * fill in the next one without adding it first; the empty ones are the
     * normal case, not a fault, and are simply not content.
     */
    public function usableItems(): array
    {
        $required = collect(self::TYPES[$this->type]['fields'] ?? [])
            ->filter(fn ($field) => $field['required'] ?? false)
            ->keys()
            ->all();

        return collect($this->items ?? [])
            ->filter(function ($item) use ($required) {
                if (! is_array($item)) {
                    return false;
                }

                // A row counts when every field marked required has a value.
                // With no required fields, any non-empty value will do.
                if ($required) {
                    foreach ($required as $key) {
                        if (trim((string) ($item[$key] ?? '')) === '') {
                            return false;
                        }
                    }

                    return true;
                }

                return collect($item)->contains(fn ($value) => is_array($value)
                    ? count($value) > 0
                    : trim((string) $value) !== '');
            })
            ->values()
            ->all();
    }

    /**
     * `owner_type` / `owner_id` — the polymorphic pair, in words.
     *
     * `blockable_type` holds a PHP class name, which is meaningless in a
     * spreadsheet and dangerous to type by hand. These two let the Excel
     * import and export speak in 'service' / 'sector' / 'location' instead,
     * while the columns underneath stay exactly as they were.
     */
    public function getOwnerTypeAttribute(): ?string
    {
        if ($this->blockable_type === Location::class) {
            return 'location';
        }

        if ($this->blockable_type !== Service::class) {
            return null;
        }

        // Both service and sector pages are Services; the category is the
        // only thing that separates them.
        $isSector = Service::query()
            ->whereKey($this->blockable_id)
            ->whereHas('categories', fn ($query) => $query
                ->where('type', 'service')
                ->where('category_type', 'sector'))
            ->exists();

        return $isSector ? 'sector' : 'service';
    }

    public function setOwnerTypeAttribute(?string $value): void
    {
        $key = strtolower(trim((string) $value));

        if (isset(self::OWNER_TYPES[$key])) {
            $this->attributes['blockable_type'] = self::OWNER_TYPES[$key];
        }
    }

    public function getOwnerIdAttribute(): ?int
    {
        return $this->blockable_id;
    }

    public function setOwnerIdAttribute(mixed $value): void
    {
        $this->attributes['blockable_id'] = $value === null || $value === '' ? null : (int) $value;
    }

    /**
     * The owner page's title, for export only — it gives a human reading the
     * spreadsheet something to recognise. Never imported: the title is the
     * page's own field, and editing it here would silently do nothing.
     */
    public function getOwnerTitleAttribute(): ?string
    {
        if (! $this->blockable_type || ! $this->blockable_id) {
            return null;
        }

        return $this->blockable_type::query()->whereKey($this->blockable_id)->value('title');
    }

    protected static function booted(): void
    {
        static::saved(function (self $block): void {
            if ($block->pendingCmsData !== null) {
                ContentFieldStore::replace($block, $block->pendingCmsData);
                $block->pendingCmsData = null;
                $block->unsetRelation('fields');
            }

            FrontendCache::bump();
        });
        static::deleted(fn () => FrontendCache::bump());
        static::deleting(fn (self $block) => $block->fields()->delete());
    }
}
