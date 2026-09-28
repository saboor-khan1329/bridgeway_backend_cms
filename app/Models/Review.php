<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use App\Traits\HasImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Review extends Model
{
    use HasFactory, HasBooleanScopes, HasImages;

    protected $fillable = [
        'author_name',
        'author_role',
        'company_name',
        'title',
        'content',
        'rating',
        'status',
        'is_testimonial',
    ];

    protected $casts = [
        'rating' => 'integer',
        'status' => 'boolean',
        'is_testimonial' => 'boolean',
    ];

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'reviewable')
            ->orderBy('pages.id');
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'reviewable')
            ->orderBy('services.title');
    }

    public function locations(): MorphToMany
    {
        return $this->morphedByMany(Location::class, 'reviewable')
            ->orderBy('locations.title');
    }

    public function blogs(): MorphToMany
    {
        return $this->morphedByMany(Blog::class, 'reviewable')
            ->orderBy('blogs.title');
    }

    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'reviewable')
            ->orderBy('categories.name');
    }
}
