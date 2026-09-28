<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropIndexQuietly('categories', 'categories_tree_idx');
        $this->dropIndexQuietly('services', 'services_status_position_index');
        $this->dropIndexQuietly('services', 'services_tree_idx');
        $this->dropIndexQuietly('locations', 'locations_status_position_index');
        $this->dropIndexQuietly('locations', 'locations_tree_idx');
        $this->dropIndexQuietly('reviews', 'reviews_status_is_testimonial_position_index');
        $this->dropIndexQuietly('menu_items', 'menu_items_tree_idx');

        $this->dropColumns('categories', ['position']);
        $this->dropColumns('services', ['description', 'position']);
        $this->dropColumns('locations', ['position']);
        $this->dropColumns('blogs', ['position']);
        $this->dropColumns('reviews', ['position']);
        $this->dropColumns('images', ['position']);
        $this->dropColumns('faqables', ['position']);
        $this->dropColumns('reviewables', ['position']);
        $this->dropColumns('pageables', ['position']);
        $this->dropColumns('services_category', ['position']);
        $this->dropColumns('locations_category', ['position']);
        $this->dropColumns('blogs_category', ['position']);
        $this->dropColumns('menu_items', ['position']);

        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'section_2_sectors_faqs')) {
            Schema::table('services', function (Blueprint $table) {
                $table->json('section_2_sectors_faqs')->nullable()->after('banner_description');
            });
        }

        if (Schema::hasTable('services') && ! Schema::hasColumn('services', 'section_3_sectors_faqs')) {
            Schema::table('services', function (Blueprint $table) {
                $table->json('section_3_sectors_faqs')->nullable()->after('section_2_sectors_faqs');
            });
        }

        if (! Schema::hasTable('service_linked_services')) {
            Schema::create('service_linked_services', function (Blueprint $table) {
                $table->unsignedBigInteger('service_id');
                $table->unsignedBigInteger('linked_service_id');
                $table->string('link_group', 16);
                $table->primary(['service_id', 'linked_service_id', 'link_group'], 'service_linked_services_pk');
                $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->foreign('linked_service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->index(['linked_service_id', 'link_group'], 'service_linked_services_lookup_idx');
            });
        }

        $this->copyLegacyServiceLinks();
        Schema::dropIfExists('service_links');

        $this->createIndexQuietly('categories', ['type', 'parent_id'], 'categories_tree_idx');
        $this->createIndexQuietly('services', ['status'], 'services_status_index');
        $this->createIndexQuietly('services', ['parent_id'], 'services_tree_idx');
        $this->createIndexQuietly('locations', ['status'], 'locations_status_index');
        $this->createIndexQuietly('locations', ['parent_id'], 'locations_tree_idx');
        $this->createIndexQuietly('reviews', ['status', 'is_testimonial'], 'reviews_status_is_testimonial_index');
        $this->createIndexQuietly('menu_items', ['menu_id', 'parent_id'], 'menu_items_tree_idx');
    }

    public function down(): void
    {
        Schema::dropIfExists('service_linked_services');

        if (! Schema::hasTable('service_links') && Schema::hasTable('services')) {
            Schema::create('service_links', function (Blueprint $table) {
                $table->unsignedBigInteger('parent_service_id');
                $table->unsignedBigInteger('child_service_id');
                $table->primary(['parent_service_id', 'child_service_id'], 'service_links_pk');
                $table->foreign('parent_service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->foreign('child_service_id')->references('id')->on('services')->cascadeOnDelete();
                $table->index('child_service_id');
            });
        }

        $this->dropIndexQuietly('categories', 'categories_tree_idx');
        $this->dropIndexQuietly('services', 'services_status_index');
        $this->dropIndexQuietly('services', 'services_tree_idx');
        $this->dropIndexQuietly('locations', 'locations_status_index');
        $this->dropIndexQuietly('locations', 'locations_tree_idx');
        $this->dropIndexQuietly('reviews', 'reviews_status_is_testimonial_index');
        $this->dropIndexQuietly('menu_items', 'menu_items_tree_idx');

        $this->addColumnIfMissing('categories', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('depth'));
        $this->addColumnIfMissing('services', 'description', fn (Blueprint $table) => $table->longText('description')->nullable()->after('short_description'));
        $this->addColumnIfMissing('services', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('is_featured'));
        $this->addColumnIfMissing('locations', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('is_featured'));
        $this->addColumnIfMissing('blogs', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('is_featured'));
        $this->addColumnIfMissing('reviews', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('is_testimonial'));
        $this->addColumnIfMissing('images', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('alt'));
        $this->addColumnIfMissing('faqables', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('faqable_id'));
        $this->addColumnIfMissing('reviewables', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('reviewable_id'));
        $this->addColumnIfMissing('pageables', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('pageable_id'));
        $this->addColumnIfMissing('services_category', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('category_id'));
        $this->addColumnIfMissing('locations_category', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('category_id'));
        $this->addColumnIfMissing('blogs_category', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('category_id'));
        $this->addColumnIfMissing('menu_items', 'position', fn (Blueprint $table) => $table->unsignedInteger('position')->default(0)->after('target'));

        $this->dropColumns('services', ['section_2_sectors_faqs', 'section_3_sectors_faqs']);

        $this->createIndexQuietly('categories', ['type', 'parent_id', 'position'], 'categories_tree_idx');
        $this->createIndexQuietly('services', ['status', 'position'], 'services_status_position_index');
        $this->createIndexQuietly('services', ['parent_id', 'position'], 'services_tree_idx');
        $this->createIndexQuietly('locations', ['status', 'position'], 'locations_status_position_index');
        $this->createIndexQuietly('locations', ['parent_id', 'position'], 'locations_tree_idx');
        $this->createIndexQuietly('reviews', ['status', 'is_testimonial', 'position'], 'reviews_status_is_testimonial_position_index');
        $this->createIndexQuietly('menu_items', ['menu_id', 'parent_id', 'position'], 'menu_items_tree_idx');
    }

    protected function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($tableName, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    protected function addColumnIfMissing(string $tableName, string $column, callable $definition): void
    {
        if (! Schema::hasTable($tableName) || Schema::hasColumn($tableName, $column)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($definition) {
            $definition($table);
        });
    }

    protected function dropIndexQuietly(string $tableName, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || ! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->dropIndex($indexName));
    }

    protected function createIndexQuietly(string $tableName, array $columns, string $indexName): void
    {
        if (! Schema::hasTable($tableName) || $this->indexExists($tableName, $indexName)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($tableName, $column)) {
                return;
            }
        }

        Schema::table($tableName, fn (Blueprint $table) => $table->index($columns, $indexName));
    }

    protected function indexExists(string $tableName, string $indexName): bool
    {
        return match (Schema::getConnection()->getDriverName()) {
            'pgsql' => in_array(
                data_get(DB::selectOne(
                    'select exists (
                        select 1
                        from pg_indexes
                        where schemaname = current_schema()
                        and tablename = ?
                        and indexname = ?
                    ) as exists',
                    [$tableName, $indexName]
                ), 'exists'),
                [true, 1, '1', 't', 'true'],
                true
            ),
            'mysql', 'mariadb' => (int) data_get(DB::selectOne(
                'select count(*) as aggregate
                from information_schema.statistics
                where table_schema = database()
                and table_name = ?
                and index_name = ?',
                [$tableName, $indexName]
            ), 'aggregate') > 0,
            'sqlite' => collect(DB::select("pragma index_list('{$tableName}')"))
                ->contains(fn ($index) => data_get($index, 'name') === $indexName),
            default => false,
        };
    }

    protected function copyLegacyServiceLinks(): void
    {
        if (! Schema::hasTable('service_links') || ! Schema::hasTable('service_linked_services')) {
            return;
        }

        DB::table('service_links')
            ->orderBy('parent_service_id')
            ->orderBy('child_service_id')
            ->chunk(500, function ($rows) {
                $records = [];

                foreach ($rows as $row) {
                    $records[] = [
                        'service_id' => $row->child_service_id,
                        'linked_service_id' => $row->parent_service_id,
                        'link_group' => 'v1',
                    ];
                    $records[] = [
                        'service_id' => $row->parent_service_id,
                        'linked_service_id' => $row->child_service_id,
                        'link_group' => 'v2',
                    ];
                }

                if ($records !== []) {
                    DB::table('service_linked_services')->insertOrIgnore($records);
                }
            });
    }
};
