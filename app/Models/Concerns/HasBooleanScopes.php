<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

trait HasBooleanScopes
{
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', true);
    }

    public function scopeInactive(Builder $query): Builder
    {
        return $query->where('status', false);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

}
