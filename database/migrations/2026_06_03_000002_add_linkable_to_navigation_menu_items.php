<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds link_type / linkable_type / linkable_id to navigation_menu_items.
 *
 * Safe to run on a table created by the original migration (which did not
 * include these columns) AND on a fresh table (columns already exist —
 * hasColumn guards skip them, so this is idempotent).
 *
 * link_type values:
 *   'custom'   → manual href + title (original behaviour — default)
 *   'service'  → resolved from App\Models\Service slug
 *   'category' → resolved from App\Models\Category slug
 *   'location' → resolved from App\Models\Location slug
 *   'blog'     → resolved from App\Models\Blog slug
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_menu_items')) {
            return; // table not yet created — first migration handles it
        }

        Schema::table('navigation_menu_items', function (Blueprint $table) {
            if (! Schema::hasColumn('navigation_menu_items', 'link_type')) {
                $table->string('link_type', 32)->default('custom')->after('item_type');
            }
            if (! Schema::hasColumn('navigation_menu_items', 'linkable_type')) {
                $table->string('linkable_type', 191)->nullable()->after('link_type');
            }
            if (! Schema::hasColumn('navigation_menu_items', 'linkable_id')) {
                $table->unsignedBigInteger('linkable_id')->nullable()->after('linkable_type');
            }
        });

        // Add composite index for morph lookups — ignore if already exists
        try {
            Schema::table('navigation_menu_items', function (Blueprint $table) {
                $table->index(['linkable_type', 'linkable_id'], 'nav_items_linkable_idx');
            });
        } catch (\Throwable) {
            // index already exists — safe to ignore
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('navigation_menu_items')) {
            return;
        }

        Schema::table('navigation_menu_items', function (Blueprint $table) {
            try {
                $table->dropIndex('nav_items_linkable_idx');
            } catch (\Throwable) {}

            $cols = ['linkable_id', 'linkable_type', 'link_type'];
            foreach ($cols as $col) {
                if (Schema::hasColumn('navigation_menu_items', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
