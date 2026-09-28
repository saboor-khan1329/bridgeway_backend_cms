<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const COLUMNS = [
        'linked_services_v5_heading',
        'linked_services_v5_sub_description',
        'linked_services_v6_heading',
        'linked_services_v6_sub_description',
        'reviews_section_heading',
        'reviews_section_sub_description',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        $columns = $this->existingColumns();

        if ($columns === []) {
            return;
        }

        $filled = array_values(array_filter(
            $columns,
            fn (string $column) => DB::table('locations')
                ->whereNotNull($column)
                ->where($column, '!=', '')
                ->exists()
        ));

        if ($filled !== []) {
            throw new RuntimeException(
                'Refusing to drop non-empty location columns: '.implode(', ', $filled).'. Clear or export this data first.'
            );
        }

        Schema::table('locations', function (Blueprint $table) use ($columns) {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) {
            if (! Schema::hasColumn('locations', 'linked_services_v5_heading')) {
                $table->string('linked_services_v5_heading')->nullable();
            }

            if (! Schema::hasColumn('locations', 'linked_services_v5_sub_description')) {
                $table->text('linked_services_v5_sub_description')->nullable();
            }

            if (! Schema::hasColumn('locations', 'linked_services_v6_heading')) {
                $table->string('linked_services_v6_heading')->nullable();
            }

            if (! Schema::hasColumn('locations', 'linked_services_v6_sub_description')) {
                $table->text('linked_services_v6_sub_description')->nullable();
            }

            if (! Schema::hasColumn('locations', 'reviews_section_heading')) {
                $table->string('reviews_section_heading')->nullable();
            }

            if (! Schema::hasColumn('locations', 'reviews_section_sub_description')) {
                $table->text('reviews_section_sub_description')->nullable();
            }
        });
    }

    private function existingColumns(): array
    {
        return array_values(array_filter(
            self::COLUMNS,
            fn (string $column) => Schema::hasColumn('locations', $column)
        ));
    }
};
