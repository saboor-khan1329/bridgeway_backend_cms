<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the geocode provenance columns.
 *
 * The original 20 characters could not hold "postcodes_io_terminated", the
 * source used when a retired postcode is resolved from the terminated
 * register — a common case for long-standing client sites.
 *
 * Uses raw ALTER on PostgreSQL because widening a varchar in Laravel 10
 * otherwise requires doctrine/dbal, which this project does not install.
 * SQLite (used by the test suite) has no varchar length enforcement, so it
 * needs no change at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->resize(32);
    }

    public function down(): void
    {
        $this->resize(20);
    }

    protected function resize(int $length): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        if (Schema::hasColumn('finder_sites', 'geocode_source')) {
            DB::statement("ALTER TABLE finder_sites ALTER COLUMN geocode_source TYPE varchar({$length})");
        }

        if (Schema::hasColumn('finder_geocodes', 'source')) {
            DB::statement("ALTER TABLE finder_geocodes ALTER COLUMN source TYPE varchar({$length})");
        }
    }
};
