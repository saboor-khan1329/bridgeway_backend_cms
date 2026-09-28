<?php

namespace App\Models;

use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class SiteSetting extends Model
{
    public const CACHE_KEY = 'site_settings.all';

    /** Structured contact lists managed by the backend settings editor. */
    public const ARRAY_KEYS = [
        'contact_email_addresses',
        'contact_phone_numbers',
    ];

    protected $fillable = ['key', 'value', 'group'];

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::allCached()[$key] ?? $default;
    }

    public static function put(string $key, mixed $value, string $group = 'general'): void
    {
        self::updateOrCreate(
            ['key' => $key],
            ['value' => self::normalizeValueForStorage($key, $value), 'group' => $group]
        );
    }

    public static function bulkPut(array $values, string $group = 'general'): void
    {
        self::withoutEvents(function () use ($values, $group): void {
            foreach ($values as $key => $value) {
                self::updateOrCreate(
                    ['key' => $key],
                    ['value' => self::normalizeValueForStorage($key, $value), 'group' => $group]
                );
            }
        });

        Cache::forget(self::CACHE_KEY);
        FrontendCache::bump();
    }

    public static function allCached(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addHours(24), function () {
            return self::query()
                ->get(['key', 'value'])
                ->mapWithKeys(fn (self $setting) => [
                    $setting->key => self::castValueFromStorage($setting->key, $setting->value),
                ])
                ->all();
        });
    }

    protected static function normalizeValueForStorage(string $key, mixed $value): ?string
    {
        if (in_array($key, self::ARRAY_KEYS, true)) {
            $items = collect(is_array($value) ? $value : [$value])
                ->map(fn ($item) => trim((string) $item))
                ->filter()
                ->values()
                ->all();

            return json_encode($items, JSON_UNESCAPED_UNICODE);
        }

        return $value === null ? null : (string) $value;
    }

    protected static function castValueFromStorage(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return in_array($key, self::ARRAY_KEYS, true) ? [] : null;
        }

        if (! in_array($key, self::ARRAY_KEYS, true)) {
            return $value;
        }

        $decoded = json_decode((string) $value, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected static function booted(): void
    {
        static::saved(function () {
            Cache::forget(self::CACHE_KEY);
            FrontendCache::bump();
        });
        static::deleted(function () {
            Cache::forget(self::CACHE_KEY);
            FrontendCache::bump();
        });
    }
}
