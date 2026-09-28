<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $columns = array_values(array_filter(
            ['description', 'menu_title', 'menu_description'],
            fn (string $column) => Schema::hasColumn('categories', $column)
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        if (! Schema::hasColumn('categories', 'description')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->longText('description')->nullable()->after('short_description');
            });
        }

        if (! Schema::hasColumn('categories', 'menu_title')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->string('menu_title')->nullable()->after('banner_description');
            });
        }

        if (! Schema::hasColumn('categories', 'menu_description')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->text('menu_description')->nullable()->after('menu_title');
            });
        }
    }
};
