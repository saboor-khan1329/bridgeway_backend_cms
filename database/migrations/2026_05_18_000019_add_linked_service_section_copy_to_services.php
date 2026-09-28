<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $this->addColumnIfMissing('linked_services_v1_heading', function (Blueprint $table) {
            $table->string('linked_services_v1_heading')->nullable()->after('banner_description');
        });

        $this->addColumnIfMissing('linked_services_v1_sub_description', function (Blueprint $table) {
            $table->text('linked_services_v1_sub_description')->nullable()->after('linked_services_v1_heading');
        });

        $this->addColumnIfMissing('linked_services_v2_heading', function (Blueprint $table) {
            $table->string('linked_services_v2_heading')->nullable()->after('linked_services_v1_sub_description');
        });

        $this->addColumnIfMissing('linked_services_v2_sub_description', function (Blueprint $table) {
            $table->text('linked_services_v2_sub_description')->nullable()->after('linked_services_v2_heading');
        });

        $this->addColumnIfMissing('linked_services_v3_heading', function (Blueprint $table) {
            $table->string('linked_services_v3_heading')->nullable()->after('linked_services_v2_sub_description');
        });

        $this->addColumnIfMissing('linked_services_v3_sub_description', function (Blueprint $table) {
            $table->text('linked_services_v3_sub_description')->nullable()->after('linked_services_v3_heading');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $columns = [
            'linked_services_v1_heading',
            'linked_services_v1_sub_description',
            'linked_services_v2_heading',
            'linked_services_v2_sub_description',
            'linked_services_v3_heading',
            'linked_services_v3_sub_description',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('services', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    protected function addColumnIfMissing(string $column, callable $definition): void
    {
        if (Schema::hasColumn('services', $column)) {
            return;
        }

        Schema::table('services', function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }
};
