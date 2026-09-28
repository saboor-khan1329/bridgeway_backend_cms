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

        $columns = [
            'section_2_heading' => fn (Blueprint $table) => $table->string('section_2_heading')->nullable()->after('banner_description'),
            'section_2_description' => fn (Blueprint $table) => $table->text('section_2_description')->nullable()->after('section_2_heading'),
            'section_3_heading' => fn (Blueprint $table) => $table->string('section_3_heading')->nullable()->after('section_2_description'),
            'section_3_description' => fn (Blueprint $table) => $table->text('section_3_description')->nullable()->after('section_3_heading'),
            'section_4_heading' => fn (Blueprint $table) => $table->string('section_4_heading')->nullable()->after('section_3_description'),
            'section_4_description' => fn (Blueprint $table) => $table->text('section_4_description')->nullable()->after('section_4_heading'),
            'section_5_heading' => fn (Blueprint $table) => $table->string('section_5_heading')->nullable()->after('section_4_description'),
            'section_5_description' => fn (Blueprint $table) => $table->text('section_5_description')->nullable()->after('section_5_heading'),
            'section_6_heading' => fn (Blueprint $table) => $table->string('section_6_heading')->nullable()->after('section_5_description'),
            'section_6_description' => fn (Blueprint $table) => $table->text('section_6_description')->nullable()->after('section_6_heading'),
            'section_7_heading' => fn (Blueprint $table) => $table->string('section_7_heading')->nullable()->after('linked_services_v1_sub_description'),
            'section_7_description' => fn (Blueprint $table) => $table->text('section_7_description')->nullable()->after('section_7_heading'),
            'section_7_button_name' => fn (Blueprint $table) => $table->string('section_7_button_name')->nullable()->after('section_7_description'),
            'section_7_button_url' => fn (Blueprint $table) => $table->string('section_7_button_url', 500)->nullable()->after('section_7_button_name'),
            'section_8_heading' => fn (Blueprint $table) => $table->string('section_8_heading')->nullable()->after('section_7_button_url'),
            'section_8_description' => fn (Blueprint $table) => $table->text('section_8_description')->nullable()->after('section_8_heading'),
            'section_8_button_name' => fn (Blueprint $table) => $table->string('section_8_button_name')->nullable()->after('section_8_description'),
            'section_8_button_url' => fn (Blueprint $table) => $table->string('section_8_button_url', 500)->nullable()->after('section_8_button_name'),
            'related_locations_heading' => fn (Blueprint $table) => $table->string('related_locations_heading')->nullable()->after('linked_services_v2_sub_description'),
            'related_locations_sub_heading' => fn (Blueprint $table) => $table->text('related_locations_sub_heading')->nullable()->after('related_locations_heading'),
            'section_9_heading' => fn (Blueprint $table) => $table->string('section_9_heading')->nullable()->after('related_locations_sub_heading'),
            'section_9_description' => fn (Blueprint $table) => $table->text('section_9_description')->nullable()->after('section_9_heading'),
            'section_9_button_name' => fn (Blueprint $table) => $table->string('section_9_button_name')->nullable()->after('section_9_description'),
            'section_9_button_url' => fn (Blueprint $table) => $table->string('section_9_button_url', 500)->nullable()->after('section_9_button_name'),
            'related_blogs_heading' => fn (Blueprint $table) => $table->string('related_blogs_heading')->nullable()->after('section_9_button_url'),
            'related_blogs_sub_heading' => fn (Blueprint $table) => $table->text('related_blogs_sub_heading')->nullable()->after('related_blogs_heading'),
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn('services', $column)) {
                continue;
            }

            Schema::table('services', function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('services')) {
            return;
        }

        $columns = [
            'section_2_heading',
            'section_2_description',
            'section_3_heading',
            'section_3_description',
            'section_4_heading',
            'section_4_description',
            'section_5_heading',
            'section_5_description',
            'section_6_heading',
            'section_6_description',
            'section_7_heading',
            'section_7_description',
            'section_7_button_name',
            'section_7_button_url',
            'section_8_heading',
            'section_8_description',
            'section_8_button_name',
            'section_8_button_url',
            'related_locations_heading',
            'related_locations_sub_heading',
            'section_9_heading',
            'section_9_description',
            'section_9_button_name',
            'section_9_button_url',
            'related_blogs_heading',
            'related_blogs_sub_heading',
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
};
