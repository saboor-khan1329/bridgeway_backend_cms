<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('categories') && Schema::hasColumn('categories', 'menu_status')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->dropColumn('menu_status');
            });
        }

        if (Schema::hasTable('blogs') && ! Schema::hasColumn('blogs', 'excerpt')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->string('excerpt')->nullable()->after('short_description');
            });
        }

        if (Schema::hasTable('blogs') && Schema::hasColumn('blogs', 'is_featured')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('is_featured');
            });
        }

        if (Schema::hasTable('locations')) {
            $columns = [
                'linked_services_v5_heading' => fn (Blueprint $table) => $table->string('linked_services_v5_heading')->nullable()->after('linked_services_v4_sub_description'),
                'linked_services_v5_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v5_sub_description')->nullable()->after('linked_services_v5_heading'),
                'linked_services_v6_heading' => fn (Blueprint $table) => $table->string('linked_services_v6_heading')->nullable()->after('linked_services_v5_sub_description'),
                'linked_services_v6_sub_description' => fn (Blueprint $table) => $table->text('linked_services_v6_sub_description')->nullable()->after('linked_services_v6_heading'),
                'reviews_section_heading' => fn (Blueprint $table) => $table->string('reviews_section_heading')->nullable()->after('linked_services_v6_sub_description'),
                'reviews_section_sub_description' => fn (Blueprint $table) => $table->text('reviews_section_sub_description')->nullable()->after('reviews_section_heading'),
            ];

            foreach ($columns as $column => $definition) {
                if (Schema::hasColumn('locations', $column)) {
                    continue;
                }

                Schema::table('locations', function (Blueprint $table) use ($definition) {
                    $definition($table);
                });
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('locations')) {
            $columns = [
                'linked_services_v5_heading',
                'linked_services_v5_sub_description',
                'linked_services_v6_heading',
                'linked_services_v6_sub_description',
                'reviews_section_heading',
                'reviews_section_sub_description',
            ];

            $existing = array_values(array_filter($columns, fn (string $column) => Schema::hasColumn('locations', $column)));

            if ($existing !== []) {
                Schema::table('locations', function (Blueprint $table) use ($existing) {
                    $table->dropColumn($existing);
                });
            }
        }

        if (Schema::hasTable('blogs') && Schema::hasColumn('blogs', 'excerpt')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropColumn('excerpt');
            });
        }

        if (Schema::hasTable('blogs') && ! Schema::hasColumn('blogs', 'is_featured')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->boolean('is_featured')->default(false)->after('status');
            });
        }

        if (Schema::hasTable('categories') && ! Schema::hasColumn('categories', 'menu_status')) {
            Schema::table('categories', function (Blueprint $table) {
                $table->boolean('menu_status')->default(true)->after('is_featured');
            });
        }
    }
};
