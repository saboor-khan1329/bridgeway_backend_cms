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

        Schema::table('categories', function (Blueprint $table) {
            if (! Schema::hasColumn('categories', 'sub_heading1')) {
                $table->text('sub_heading1')->nullable()->after('short_description');
            }

            if (! Schema::hasColumn('categories', 'sub_heading_description')) {
                $table->text('sub_heading_description')->nullable()->after('sub_heading1');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('categories')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('categories', 'sub_heading_description') ? 'sub_heading_description' : null,
            Schema::hasColumn('categories', 'sub_heading1') ? 'sub_heading1' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }
};
