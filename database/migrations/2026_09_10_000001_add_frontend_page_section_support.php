<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Support for the Next.js frontend's section-driven pages, its blog cards and
 * its multi-form contact submissions.
 *
 * Every change here is additive and guarded, in the same style as the other
 * migrations in this directory: an existing database keeps every row and every
 * column it had, and each existing endpoint answers exactly as it did before.
 *
 * Three things are added:
 *
 * 1. `content_blocks.section_key` + `content_blocks.data`.
 *
 *    content_blocks already models "an ordered, admin-authored section on a
 *    page" — a morph owner, a `type` naming the component, `sort_order`,
 *    `is_active`. Its four original components each hold a list of uniform
 *    rows, which is what `items` is for. The frontend's page sections hold a
 *    single nested payload per section instead, so `data` carries that; it
 *    sits beside `items` rather than replacing it, and the original four
 *    components never write to it.
 *
 *    That is deliberately not a second sections table. The alternative — a
 *    parallel `page_sections` — would duplicate the ordering, activation and
 *    ownership rules this table already enforces, and the admin tooling built
 *    on top of them.
 *
 * 2. The per-owner uniqueness rule moves from `type` to `section_key`.
 *
 *    The original index allowed one block of each type per page, which suited
 *    four components that each appear at most once. The frontend's pages repeat
 *    components — a service page carries two `subService` sections — so the
 *    thing that must be unique per page is the section's own key, not its type.
 *    The original migration anticipated exactly this: "If a second instance is
 *    ever wanted, dropping this index is the only change."
 *
 * 3. `inquiries.form_name` + `inquiries.details`.
 *
 *    The frontend posts six different forms. They share the fields inquiries
 *    already stores (name, email, phone, message, service); what differs is a
 *    handful of per-form extras — budget, currency, brand, website. `details`
 *    keeps those without a column per form, and `form_name` records which form
 *    produced the row so the admin list can tell them apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_blocks')) {
            Schema::table('content_blocks', function (Blueprint $table) {
                if (! Schema::hasColumn('content_blocks', 'section_key')) {
                    // The frontend's stable section id ("hero", "sub-service-2").
                    // It is what anchors a section across edits and reorders,
                    // and what the TOC and in-page links resolve against.
                    $table->string('section_key', 100)->nullable()->after('type');
                }

                if (! Schema::hasColumn('content_blocks', 'data')) {
                    $table->json('data')->nullable()->after('items');
                }
            });

            $this->replaceOwnerUniqueIndex();
        }

        if (Schema::hasTable('blogs') && ! Schema::hasColumn('blogs', 'is_featured')) {
            Schema::table('blogs', function (Blueprint $table) {
                // Drives the blog listing's "Most Popular" rail. Kept off the
                // publishing flags on purpose: a post can be featured before it
                // is published, and unfeaturing must never unpublish.
                $table->boolean('is_featured')->default(false)->after('status')->index();
            });
        }

        if (! Schema::hasTable('inquiries')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            if (! Schema::hasColumn('inquiries', 'form_name')) {
                $table->string('form_name', 64)->nullable()->after('type_of_service_required')->index();
            }

            if (! Schema::hasColumn('inquiries', 'details')) {
                $table->json('details')->nullable()->after('form_name');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('content_blocks')) {
            $this->restoreOwnerUniqueIndex();

            $this->dropColumns('content_blocks', ['section_key', 'data']);
        }

        $this->dropColumns('blogs', ['is_featured']);
        $this->dropColumns('inquiries', ['form_name', 'details']);
    }

    /**
     * Swap the per-page uniqueness rule from `type` to `section_key`.
     *
     * Both index names are spelled out rather than left to Laravel's
     * convention, so the down() path removes precisely what up() added.
     */
    private function replaceOwnerUniqueIndex(): void
    {
        $this->dropIndexIfExists('content_blocks', 'content_blocks_owner_type_unique');

        if (! $this->indexExists('content_blocks', 'content_blocks_owner_section_unique')) {
            Schema::table('content_blocks', function (Blueprint $table) {
                $table->unique(
                    ['blockable_type', 'blockable_id', 'section_key'],
                    'content_blocks_owner_section_unique'
                );
            });
        }
    }

    private function restoreOwnerUniqueIndex(): void
    {
        $this->dropIndexIfExists('content_blocks', 'content_blocks_owner_section_unique');

        if (! $this->indexExists('content_blocks', 'content_blocks_owner_type_unique')) {
            Schema::table('content_blocks', function (Blueprint $table) {
                $table->unique(
                    ['blockable_type', 'blockable_id', 'type'],
                    'content_blocks_owner_type_unique'
                );
            });
        }
    }

    /**
     * Whether a named index exists, asked of the database directly.
     *
     * Deliberately not via Doctrine's schema manager: doctrine/dbal is not a
     * dependency of this project, so `getDoctrineSchemaManager()` fatals. Each
     * driver is queried through its own catalog instead — Postgres in
     * production, SQLite under the test suite.
     */
    private function indexExists(string $tableName, string $indexName): bool
    {
        $connection = Schema::getConnection();

        $sql = [
            'pgsql' => 'select 1 from pg_indexes where schemaname = current_schema() and tablename = ? and indexname = ?',
            'sqlite' => "select 1 from sqlite_master where type = 'index' and tbl_name = ? and name = ?",
            'mysql' => 'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
            'mariadb' => 'select 1 from information_schema.statistics where table_schema = database() and table_name = ? and index_name = ?',
        ][$connection->getDriverName()] ?? null;

        if ($sql === null) {
            return false;
        }

        return $connection->select($sql, [$tableName, $indexName]) !== [];
    }

    private function dropIndexIfExists(string $tableName, string $indexName): void
    {
        if (! $this->indexExists($tableName, $indexName)) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($indexName) {
            $table->dropUnique($indexName);
        });
    }

    private function dropColumns(string $tableName, array $columns): void
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
};
