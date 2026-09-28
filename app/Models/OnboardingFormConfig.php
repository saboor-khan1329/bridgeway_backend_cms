<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OnboardingFormConfig extends Model
{
    protected $fillable = [
        'name',
        'description',
        'form_config',
        'settings',
        'is_active',
    ];

    protected $casts = [
        'form_config' => 'array',
        'settings'    => 'array',
        'is_active'   => 'boolean',
    ];

    public function submissions(): HasMany
    {
        return $this->hasMany(OnboardingSubmission::class, 'form_config_id');
    }

    public static function active(): ?self
    {
        return self::where('is_active', true)->first();
    }

    /** Returns enabled steps sorted by order, safe even if config is malformed. */
    public function getSteps(): array
    {
        $steps = $this->form_config['steps'] ?? [];
        if (! is_array($steps)) {
            return [];
        }

        return collect($steps)
            ->filter(fn ($s) => is_array($s) && ($s['is_enabled'] ?? true))
            ->sortBy('order')
            ->values()
            ->all();
    }

    public function getTitle(): string
    {
        return $this->form_config['title'] ?? $this->name;
    }

    public function getDescription(): string
    {
        return $this->form_config['description'] ?? $this->description ?? '';
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return ($this->settings ?? [])[$key] ?? $default;
    }

    /** Activate this config and deactivate all others atomically. */
    public function activate(): void
    {
        self::query()->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /** Public API representation of form configuration (safe for frontend). */
    public function toApiArray(): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->getTitle(),
            'description' => $this->getDescription(),
            'settings'    => $this->safePublicSettings(),
            'steps'       => $this->getSteps(),
        ];
    }

    private function safePublicSettings(): array
    {
        $settings = $this->settings ?? [];

        return [
            'show_progress'      => (bool) ($settings['show_progress'] ?? true),
            'show_step_list'     => (bool) ($settings['show_step_list'] ?? true),
            'draft_save_enabled' => (bool) ($settings['draft_save_enabled'] ?? true),
            'draft_save_key'     => (string) ($settings['draft_save_key'] ?? 'bridgewaydigital-onboarding-draft'),
        ];
    }
}
