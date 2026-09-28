<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('menu_items');
        Schema::dropIfExists('menus');
    }

    public function down(): void
    {
        Schema::create('menus', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['status', 'slug']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('menu_id')->constrained('menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('menu_items')->cascadeOnDelete();
            $table->string('link_type', 32)->default('custom');
            $table->nullableMorphs('linkable');
            $table->string('title')->nullable();
            $table->text('description')->nullable();
            $table->string('custom_url', 500)->nullable();
            $table->string('target', 16)->default('_self');
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index(['menu_id', 'parent_id'], 'menu_items_tree_idx');
            $table->index(['menu_id', 'status'], 'menu_items_status_idx');
        });
    }
};
