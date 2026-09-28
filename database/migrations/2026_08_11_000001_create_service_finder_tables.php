<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Service Finder — aggregated coverage map, imported operational sites,
 * search analytics, quote leads and a permanent geocode cache.
 *
 * Public map pins are AREAS (city-level postcode-area groups) with service
 * counts; raw client sites stay admin-only. Everything here is additive —
 * no existing table is touched.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('finder_areas')) {
            Schema::create('finder_areas', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('slug', 140)->unique();
                $table->string('region', 80)->nullable();
                // Postcode-area letter prefixes owned by this area, e.g.
                // ["B"] or the eight London codes ["E","EC","N","NW","SE","SW","W","WC"].
                $table->json('postcode_areas')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                // When true the admin has pinned the centroid; recounts skip it.
                $table->boolean('centroid_is_locked')->default(false);
                $table->foreignId('featured_service_id')->nullable()
                    ->constrained('services')->nullOnDelete();
                $table->foreignId('location_id')->nullable()
                    ->constrained('locations')->nullOnDelete();
                $table->unsignedInteger('active_sites_count')->default(0);
                $table->boolean('is_active')->default(true)->index();
                $table->timestamps();
                $table->unique('name');
            });
        }

        if (! Schema::hasTable('finder_sites')) {
            Schema::create('finder_sites', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finder_area_id')->nullable()
                    ->constrained('finder_areas')->nullOnDelete();
                $table->foreignId('service_id')->nullable()
                    ->constrained('services')->nullOnDelete();
                // Client site names are commercially sensitive — admin-only,
                // never exposed by any public endpoint.
                $table->string('site_name', 190);
                $table->string('postcode_raw', 60);
                $table->string('postcode', 12)->nullable();
                $table->string('outcode', 8)->nullable()->index();
                $table->string('postcode_area', 4)->nullable()->index();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('geocode_source', 20)->nullable();
                $table->boolean('is_active')->default(true)->index();
                $table->string('inactive_reason', 60)->nullable();
                // csv_import rows may be replaced by a --force re-import;
                // admin rows always survive.
                $table->string('source', 20)->default('csv_import');
                $table->string('import_fingerprint', 64)->nullable()->index();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('finder_area_service')) {
            Schema::create('finder_area_service', function (Blueprint $table) {
                $table->id();
                $table->foreignId('finder_area_id')
                    ->constrained('finder_areas')->cascadeOnDelete();
                $table->foreignId('service_id')
                    ->constrained('services')->cascadeOnDelete();
                // Per-area override for the result card; falls back to
                // services.card_description, then the configurator's generic text.
                $table->text('description')->nullable();
                $table->string('source', 20)->default('import');
                $table->unsignedInteger('sites_count')->default(0);
                $table->unsignedSmallInteger('sort_order')->default(0);
                $table->timestamps();
                $table->unique(['finder_area_id', 'service_id']);
            });
        }

        if (! Schema::hasTable('finder_searches')) {
            Schema::create('finder_searches', function (Blueprint $table) {
                $table->id();
                $table->string('query', 190);
                $table->string('postcode', 12)->nullable();
                $table->foreignId('matched_area_id')->nullable()
                    ->constrained('finder_areas')->nullOnDelete();
                $table->foreignId('service_id')->nullable()
                    ->constrained('services')->nullOnDelete();
                $table->string('match_type', 20)->nullable();
                $table->unsignedSmallInteger('results_count')->default(0);
                $table->string('ip_hash', 64)->nullable();
                $table->string('user_agent', 255)->nullable();
                $table->timestamps();
                $table->index('created_at');
                $table->index(['match_type', 'created_at']);
            });
        }

        if (! Schema::hasTable('finder_leads')) {
            Schema::create('finder_leads', function (Blueprint $table) {
                $table->id();
                $table->string('name', 120);
                $table->string('phone', 40);
                $table->string('company', 120)->nullable();
                $table->string('email', 190)->index();
                $table->foreignId('service_id')->nullable()
                    ->constrained('services')->nullOnDelete();
                // Title snapshot at submit time — survives catalog changes.
                $table->string('service_label', 190)->nullable();
                $table->foreignId('finder_area_id')->nullable()
                    ->constrained('finder_areas')->nullOnDelete();
                $table->string('postcode_or_area', 120);
                $table->string('start_date', 64)->nullable();
                $table->string('origin', 30)->default('result_card');
                $table->string('status', 16)->default('new');
                $table->unsignedSmallInteger('spam_score')->default(0);
                $table->json('spam_reasons')->nullable();
                $table->boolean('captcha_passed')->default(false);
                $table->json('meta')->nullable();
                $table->timestamp('read_at')->nullable();
                $table->timestamps();
                $table->index(['status', 'created_at']);
            });
        }

        if (! Schema::hasTable('finder_geocodes')) {
            Schema::create('finder_geocodes', function (Blueprint $table) {
                $table->id();
                // Full postcodes and bare outcodes share this cache.
                $table->string('postcode', 12)->unique();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->string('postcode_area', 4)->nullable();
                $table->string('region', 80)->nullable();
                $table->string('source', 24)->nullable();
                $table->string('status', 12)->default('ok');
                $table->json('payload')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('finder_geocodes');
        Schema::dropIfExists('finder_leads');
        Schema::dropIfExists('finder_searches');
        Schema::dropIfExists('finder_area_service');
        Schema::dropIfExists('finder_sites');
        Schema::dropIfExists('finder_areas');
    }
};
