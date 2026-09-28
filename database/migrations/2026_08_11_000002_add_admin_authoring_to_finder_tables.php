<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Makes admin-authored coverage the authority.
 *
 * The operations CSV is only a bootstrap: real coverage is decided by an
 * operator who picks a service, the areas it is offered in, and the related
 * services to cross-sell there. These columns give that authoring a home and
 * let a re-import top up counts without ever overwriting a human decision.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('finder_areas', function (Blueprint $table) {
            if (! Schema::hasColumn('finder_areas', 'source')) {
                // admin rows are never touched by a forced re-import.
                $table->string('source', 20)->default('import')->after('is_active');
            }
            if (! Schema::hasColumn('finder_areas', 'intro')) {
                // Optional per-area popup blurb, overriding the global template.
                $table->text('intro')->nullable()->after('region');
            }
            if (! Schema::hasColumn('finder_areas', 'sort_order')) {
                $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            }
        });

        Schema::table('finder_area_service', function (Blueprint $table) {
            if (! Schema::hasColumn('finder_area_service', 'related_service_ids')) {
                // Cross-sell list shown with this service in this area.
                $table->json('related_service_ids')->nullable()->after('description');
            }
            if (! Schema::hasColumn('finder_area_service', 'is_featured')) {
                $table->boolean('is_featured')->default(false)->after('related_service_ids');
            }
            if (! Schema::hasColumn('finder_area_service', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_featured');
            }
        });
    }

    public function down(): void
    {
        Schema::table('finder_area_service', function (Blueprint $table) {
            foreach (['related_service_ids', 'is_featured', 'is_active'] as $column) {
                if (Schema::hasColumn('finder_area_service', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('finder_areas', function (Blueprint $table) {
            foreach (['source', 'intro', 'sort_order'] as $column) {
                if (Schema::hasColumn('finder_areas', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
