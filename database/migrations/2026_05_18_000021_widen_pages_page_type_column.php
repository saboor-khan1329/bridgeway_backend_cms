<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasColumn('pages', 'page_type')) {
            return;
        }

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE pages ALTER COLUMN page_type TYPE VARCHAR(64)'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE pages MODIFY page_type VARCHAR(64) NULL'),
            default => null,
        };
    }

    public function down(): void
    {
        if (! Schema::hasTable('pages') || ! Schema::hasColumn('pages', 'page_type')) {
            return;
        }

        DB::table('pages')
            ->whereNotNull('page_type')
            ->update(['page_type' => DB::raw("SUBSTRING(page_type, 1, 16)")]);

        match (DB::getDriverName()) {
            'pgsql' => DB::statement('ALTER TABLE pages ALTER COLUMN page_type TYPE VARCHAR(16)'),
            'mysql', 'mariadb' => DB::statement('ALTER TABLE pages MODIFY page_type VARCHAR(16) NULL'),
            default => null,
        };
    }
};
