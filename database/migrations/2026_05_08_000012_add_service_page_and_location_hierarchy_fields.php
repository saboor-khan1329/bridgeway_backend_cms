<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('category_type', 16)->nullable()->after('type')->index();
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->string('page_type', 64)->nullable()->after('page_title')->index();
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->foreignId('parent_id')
                ->nullable()
                ->after('id')
                ->constrained('locations')
                ->nullOnDelete();
            $table->index('parent_id', 'locations_tree_idx');
        });

        DB::table('categories')
            ->where('type', 'service')
            ->whereNull('category_type')
            ->update(['category_type' => 'service']);
    }

    public function down(): void
    {
        Schema::table('locations', function (Blueprint $table) {
            $table->dropIndex('locations_tree_idx');
            $table->dropForeign(['parent_id']);
            $table->dropColumn('parent_id');
        });

        Schema::table('pages', function (Blueprint $table) {
            $table->dropIndex(['page_type']);
            $table->dropColumn('page_type');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['category_type']);
            $table->dropColumn('category_type');
        });
    }
};
