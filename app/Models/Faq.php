<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

class Faq extends Model
{
    use HasFactory, HasBooleanScopes;

    protected $fillable = [
        'question',
        'answer',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    public function pages(): MorphToMany
    {
        return $this->morphedByMany(Page::class, 'faqable');
    }

    public function services(): MorphToMany
    {
        return $this->morphedByMany(Service::class, 'faqable');
    }

    public function locations(): MorphToMany
    {
        return $this->morphedByMany(Location::class, 'faqable');
    }

    public function blogs(): MorphToMany
    {
        return $this->morphedByMany(Blog::class, 'faqable');
    }

    public function categories(): MorphToMany
    {
        return $this->morphedByMany(Category::class, 'faqable');
    }
}
