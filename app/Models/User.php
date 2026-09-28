<?php

namespace App\Models;

use App\Traits\HasImages;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, HasImages, Notifiable;

    protected $fillable = [
        'name',
        'display_name',
        'job_title',
        'bio',
        'email',
        'password',
        'status',
        'is_root',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'boolean',
        'is_root' => 'boolean',
    ];

    public function authoredBlogs(): HasMany
    {
        return $this->hasMany(Blog::class, 'author_user_id');
    }

    public function getAccountTypeAttribute(): string
    {
        return $this->isRootAdmin() ? 'Super Admin' : 'Admin';
    }

    public function publicName(): string
    {
        return trim((string) ($this->display_name ?: $this->name)) ?: 'Content Author';
    }

    public function isRootAdmin(): bool
    {
        return (bool) $this->is_root;
    }

}
