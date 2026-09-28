<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('navigation_menus')) {
            Schema::create('navigation_menus', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('location', 32)->default('header'); // 'header' | 'footer'
                $table->json('meta')->nullable(); // logo, cta, footer detail/rights/info
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->index('location', 'nav_menus_location_idx');
            });
        }

        if (! Schema::hasTable('navigation_menu_items')) {
            Schema::create('navigation_menu_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_id')->constrained('navigation_menus')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('navigation_menu_items')->nullOnDelete();
                // item_type:
                //   header → 'nav_link' (top-level), 'sub_link' (mega menu child)
                //   footer → 'locations_group', 'group', 'link' (child of group), 'other_link'
                $table->string('item_type', 32)->default('link');
                // link_type: 'custom' | 'service' | 'category' | 'location' | 'blog'
                // 'custom' = manual href; others auto-resolve URL from the linked content item
                $table->string('link_type', 32)->default('custom');
                $table->string('linkable_type', 191)->nullable(); // Eloquent model class
                $table->unsignedBigInteger('linkable_id')->nullable();
                $table->string('title', 500);
                $table->string('href', 500)->nullable(); // custom URL, or fallback for linked items
                $table->string('target', 16)->default('_self');
                $table->json('meta')->nullable(); // icon, mega-menu fields, etc.
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->index(['menu_id', 'parent_id', 'sort_order'], 'nav_items_tree_order_idx');
                $table->index(['menu_id', 'item_type', 'status'], 'nav_items_type_status_idx');
                $table->index(['linkable_type', 'linkable_id'], 'nav_items_linkable_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu_items');
        Schema::dropIfExists('navigation_menus');
    }
};
