<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the dynamic header/footer navigation system. The header and footer
 * are now rendered statically by the Next.js client, so the admin-managed menu
 * tables are no longer used. down() recreates the schema (not the data) so the
 * change is reversible.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }

    public function down(): void
    {
        if (! Schema::hasTable('menus')) {
            Schema::create('menus', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 191)->unique();
                $table->string('placement', 64)->default('header');
                $table->text('description')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->index(['placement', 'status', 'sort_order'], 'menus_placement_status_order_idx');
            });
        }

        if (! Schema::hasTable('menu_items')) {
            Schema::create('menu_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
                $table->foreignId('parent_id')->nullable()->constrained('menu_items')->nullOnDelete();
                $table->string('link_type', 32)->default('custom');
                $table->nullableMorphs('linkable');
                $table->string('title');
                $table->text('description')->nullable();
                $table->text('detail')->nullable();
                $table->string('custom_url', 500)->nullable();
                $table->string('target', 16)->default('_self');
                $table->json('extra_content')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->boolean('status')->default(true);
                $table->timestamps();
                $table->index(['menu_id', 'parent_id', 'status', 'sort_order'], 'menu_items_tree_status_order_idx');
                $table->index(['menu_id', 'status'], 'menu_items_menu_status_idx');
            });
        }
    }
};
