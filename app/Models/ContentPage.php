<?php

namespace App\Models;

use App\Traits\HasImages;
use App\Traits\HasSeo;
use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * A section-driven content page on the Bridgeway frontend.
 *
 * The body is not stored here. It is an ordered list of ContentBlock rows, the
 * same mechanism service pages use, so a page is composed from components
 * rather than filled into a fixed template. What this record holds is the
 * page's identity: its slug, the frontend template that renders it, its
 * publication state, and the metadata block.
 *
 * Note the plain `slug` column instead of the HasSlug morph the other content
 * models use — see the migration for why: this frontend has two URL namespaces
 * and the shared slugs table can only represent one.
 *
 * @property string $slug
 * @property string $title
 * @property string $template
 * @property array|null $seo
 * @property bool $status
 */
class ContentPage extends Model
{
    use HasFactory, HasImages, HasSeo;

    protected $fillable = [
        'slug',
        'title',
        'template',
        'is_cms_managed',
        'status',
        'sort_order',
    ];

    protected $casts = [
        'status' => 'boolean',
        'is_cms_managed' => 'boolean',
        'sort_order' => 'integer',
    ];

    public function contentBlocks(): MorphMany
    {
        return $this->morphMany(ContentBlock::class, 'blockable')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', true);
    }

    /**
     * Sections go when the page does.
     *
     * A morph has no database-level cascade, so without this a deleted page
     * would leave its sections behind as rows pointing at nothing.
     */
    protected static function booted(): void
    {
        static::saved(fn () => FrontendCache::bump());
        static::deleted(fn () => FrontendCache::bump());
        static::deleting(function (self $page) {
            $page->contentBlocks()->delete();
        });
    }
}
