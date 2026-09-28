<?php

use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Seeds the Google review badge (score + profile link) into the existing
 * footer navigation menu meta.
 *
 * The NavigationSeeder uses firstOrCreate, so it never touches a footer menu
 * that already exists — this backfill is what gets the values into a live
 * database. Both keys are written only when absent, so an admin who has
 * already set their own score/link is never overwritten.
 */
return new class extends Migration
{
    private const DEFAULTS = [
        'google_rating'     => '4.8',
        'google_review_url' => 'https://share.google/GwrfhkPFXUFrZyc83',
    ];

    public function up(): void
    {
        $menu = DB::table('navigation_menus')->where('location', 'footer')->first();

        if (! $menu) {
            return; // Nothing seeded yet — NavigationSeeder will include the defaults.
        }

        $meta    = json_decode($menu->meta ?? '[]', true) ?: [];
        $changed = false;

        foreach (self::DEFAULTS as $key => $value) {
            if (! array_key_exists($key, $meta) || $meta[$key] === null || $meta[$key] === '') {
                $meta[$key] = $value;
                $changed    = true;
            }
        }

        if ($changed) {
            DB::table('navigation_menus')
                ->where('id', $menu->id)
                ->update(['meta' => json_encode($meta), 'updated_at' => now()]);
        }

        // The revamped bottom bar drops "Blogs & Posts". Deactivated rather
        // than deleted, so the row (and its link target) survives and an admin
        // can switch it back on from the navigation screen at any time.
        DB::table('navigation_menu_items')
            ->where('menu_id', $menu->id)
            ->where('item_type', 'other_link')
            ->where('title', 'Blogs & Posts')
            ->update(['status' => false, 'updated_at' => now()]);

        FrontendCache::bump();
    }

    public function down(): void
    {
        $menu = DB::table('navigation_menus')->where('location', 'footer')->first();

        if (! $menu) {
            return;
        }

        $meta = json_decode($menu->meta ?? '[]', true) ?: [];
        unset($meta['google_rating'], $meta['google_review_url']);

        DB::table('navigation_menus')
            ->where('id', $menu->id)
            ->update(['meta' => json_encode($meta), 'updated_at' => now()]);

        DB::table('navigation_menu_items')
            ->where('menu_id', $menu->id)
            ->where('item_type', 'other_link')
            ->where('title', 'Blogs & Posts')
            ->update(['status' => true, 'updated_at' => now()]);

        FrontendCache::bump();
    }
};
