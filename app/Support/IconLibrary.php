<?php

namespace App\Support;

/**
 * Icon library for admin pickers (navigation mega-menu sub-link icons).
 *
 * Paths point to SVGs that exist in the client app's /public/images/icons.
 * The admin form renders these as a dropdown instead of a free-text path —
 * "Default (no icon)" keeps the frontend's current behaviour, and any
 * previously saved custom path not in this list is preserved as-is.
 */
class IconLibrary
{
    public const ICONS = [
        ['value' => '/images/icons/bell_icon.svg',      'label' => 'Bell (Alarm / Response)'],
        ['value' => '/images/icons/car_icon.svg',       'label' => 'Car (Mobile Patrol)'],
        ['value' => '/images/icons/clean_icon.svg',     'label' => 'Cleaning'],
        ['value' => '/images/icons/fire_icon.svg',      'label' => 'Fire (Fire Watch)'],
        ['value' => '/images/icons/key_icon.svg',       'label' => 'Key (Keyholding)'],
        ['value' => '/images/icons/reception_icon.svg', 'label' => 'Reception (Concierge)'],
        ['value' => '/images/icons/users_icon.svg',     'label' => 'Users (Manned Guarding)'],
        ['value' => '/images/icons/warehouse_icon.svg', 'label' => 'Warehouse'],
    ];

    /** @return array<int, array{value: string, label: string}> */
    public static function all(): array
    {
        return self::ICONS;
    }

    public static function has(?string $path): bool
    {
        if (! $path) {
            return false;
        }

        return in_array($path, array_column(self::ICONS, 'value'), true);
    }
}
