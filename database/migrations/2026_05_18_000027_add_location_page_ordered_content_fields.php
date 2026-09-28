<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('locations')) {
            return;
        }

        $columns = [
            'sub_heading' => fn (Blueprint $table) => $table->string('sub_heading')->nullable()->after('title'),
            'section_2_heading' => fn (Blueprint $table) => $table->string('section_2_heading')->nullable()->after('short_description'),
            'section_2_description' => fn (Blueprint $table) => $table->text('section_2_description')->nullable()->after('section_2_heading'),
            'section_2_button_name' => fn (Blueprint $table) => $table->string('section_2_button_name')->nullable()->after('section_2_description'),
            'section_2_button_url' => fn (Blueprint $table) => $table->string('section_2_button_url', 500)->nullable()->after('section_2_button_name'),
            'linked_services_v1_heading' => fn (Blueprint $table) => $table->string('linked_services_v1_heading')->nullable()->after('section_2_button_url'),
            'linked_services_v1_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v1_sub_description')->nullable()->after('linked_services_v1_heading'),
            'section_3_locations_faqs' => fn (Blueprint $table) => $table->json('section_3_locations_faqs')->nullable()->after('linked_services_v1_sub_description'),
            'section_4_locations_faqs' => fn (Blueprint $table) => $table->json('section_4_locations_faqs')->nullable()->after('section_3_locations_faqs'),
            'section_5_heading' => fn (Blueprint $table) => $table->string('section_5_heading')->nullable()->after('section_4_locations_faqs'),
            'section_5_description' => fn (Blueprint $table) => $table->text('section_5_description')->nullable()->after('section_5_heading'),
            'section_6_heading' => fn (Blueprint $table) => $table->string('section_6_heading')->nullable()->after('section_5_description'),
            'section_6_description' => fn (Blueprint $table) => $table->text('section_6_description')->nullable()->after('section_6_heading'),
            'linked_child_locations_heading' => fn (Blueprint $table) => $table->string('linked_child_locations_heading')->nullable()->after('section_6_description'),
            'linked_child_locations_sub_description' => fn (Blueprint $table) => $table->text('linked_child_locations_sub_description')->nullable()->after('linked_child_locations_heading'),
            'section_7_heading' => fn (Blueprint $table) => $table->string('section_7_heading')->nullable()->after('linked_child_locations_sub_description'),
            'section_7_description' => fn (Blueprint $table) => $table->text('section_7_description')->nullable()->after('section_7_heading'),
            'section_8_heading' => fn (Blueprint $table) => $table->string('section_8_heading')->nullable()->after('section_7_description'),
            'section_8_description' => fn (Blueprint $table) => $table->text('section_8_description')->nullable()->after('section_8_heading'),
            'linked_services_v2_heading' => fn (Blueprint $table) => $table->string('linked_services_v2_heading')->nullable()->after('section_8_description'),
            'linked_services_v2_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v2_sub_description')->nullable()->after('linked_services_v2_heading'),
            'related_blogs_heading' => fn (Blueprint $table) => $table->string('related_blogs_heading')->nullable()->after('linked_services_v2_sub_description'),
            'related_blogs_sub_heading' => fn (Blueprint $table) => $table->text('related_blogs_sub_heading')->nullable()->after('related_blogs_heading'),
            'linked_services_v3_heading' => fn (Blueprint $table) => $table->string('linked_services_v3_heading')->nullable()->after('related_blogs_sub_heading'),
            'linked_services_v3_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v3_sub_description')->nullable()->after('linked_services_v3_heading'),
            'linked_services_v4_heading' => fn (Blueprint $table) => $table->string('linked_services_v4_heading')->nullable()->after('linked_services_v3_sub_description'),
            'linked_services_v4_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v4_sub_description')->nullable()->after('linked_services_v4_heading'),
            'section_9_map_src' => fn (Blueprint $table) => $table->text('section_9_map_src')->nullable()->after('linked_services_v4_sub_description'),
        ];

        foreach ($columns as $column => $definition) {
            if (Schema::hasColumn('locations', $column)) {
                continue;
            }

            Schema::table('locations', function (Blueprint $table) use ($definition) {
                $definition($table);
            });
        }

        if (! Schema::hasTable('location_linked_services')) {
            Schema::create('location_linked_services', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('linked_service_id');
                $table->string('link_group', 16);
                $table->primary(['location_id', 'linked_service_id', 'link_group'], 'location_linked_services_pk');
                $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
                $table->foreign('linked_service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->index(['linked_service_id', 'link_group'], 'location_linked_services_lookup_idx');
            });
        }

        if (Schema::hasTable('location_service')) {
            DB::table('location_service')
                ->orderBy('location_id')
                ->chunk(500, function ($rows) {
                    $records = $rows->map(fn ($row) => [
                        'location_id' => $row->location_id,
                        'linked_service_id' => $row->service_id,
                        'link_group' => 'v1',
                    ])->all();

                    DB::table('location_linked_services')->insertOrIgnore($records);
                });
        }

        if (! Schema::hasTable('location_section_faqs')) {
            Schema::create('location_section_faqs', function (Blueprint $table) {
                $table->unsignedBigInteger('location_id');
                $table->unsignedBigInteger('faq_id');
                $table->string('section_key', 32);
                $table->primary(['location_id', 'faq_id', 'section_key'], 'location_section_faqs_pk');
                $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
                $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
                $table->index(['faq_id', 'section_key'], 'location_section_faqs_lookup_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('location_section_faqs');
        Schema::dropIfExists('location_linked_services');

        if (! Schema::hasTable('locations')) {
            return;
        }

        $columns = [
            'sub_heading',
            'section_2_heading',
            'section_2_description',
            'section_2_button_name',
            'section_2_button_url',
            'linked_services_v1_heading',
            'linked_services_v1_sub_description',
            'section_3_locations_faqs',
            'section_4_locations_faqs',
            'section_5_heading',
            'section_5_description',
            'section_6_heading',
            'section_6_description',
            'linked_child_locations_heading',
            'linked_child_locations_sub_description',
            'section_7_heading',
            'section_7_description',
            'section_8_heading',
            'section_8_description',
            'linked_services_v2_heading',
            'linked_services_v2_sub_description',
            'related_blogs_heading',
            'related_blogs_sub_heading',
            'linked_services_v3_heading',
            'linked_services_v3_sub_description',
            'linked_services_v4_heading',
            'linked_services_v4_sub_description',
            'section_9_map_src',
        ];

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn('locations', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('locations', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
