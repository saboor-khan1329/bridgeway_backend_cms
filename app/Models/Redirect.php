<?php

namespace App\Models;

use App\Models\Concerns\HasBooleanScopes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Redirect extends Model
{
    use HasFactory, HasBooleanScopes;

    protected $fillable = [
        'from_url',
        'to_url',
        'status_code',
        'status',
        'sourceable_type',
        'sourceable_id',
        'is_auto_generated',
    ];

    protected $casts = [
        'status' => 'boolean',
        'status_code' => 'integer',
        'is_auto_generated' => 'boolean',
    ];

    public function sourceable(): MorphTo
    {
        return $this->morphTo();
    }
}
