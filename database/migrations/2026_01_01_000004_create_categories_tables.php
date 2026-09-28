<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16)->index();
            $table->unsignedBigInteger('parent_id')->nullable()->index();
            $table->string('name');
            $table->unsignedInteger('depth')->default(0);
            $table->text('short_description')->nullable();
            $table->text('sub_heading1')->nullable();
            $table->text('sub_heading_description')->nullable();
            $table->string('banner_title')->nullable();
            $table->text('banner_description')->nullable();
            $table->boolean('is_featured')->default(false);
            $table->boolean('status')->default(true);
            $table->boolean('menu_status')->default(true);
            $table->timestamps();
            $table->foreign('parent_id')->references('id')->on('categories')->nullOnDelete();
            $table->index(['type', 'parent_id'], 'categories_tree_idx');
        });

        Schema::create('category_closures', function (Blueprint $table) {
            $table->unsignedBigInteger('ancestor_id');
            $table->unsignedBigInteger('descendant_id');
            $table->unsignedInteger('depth');
            $table->primary(['ancestor_id', 'descendant_id'], 'category_closures_pk');
            $table->foreign('ancestor_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->foreign('descendant_id')->references('id')->on('categories')->cascadeOnDelete();
            $table->index('descendant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_closures');
        Schema::dropIfExists('categories');
    }
};
