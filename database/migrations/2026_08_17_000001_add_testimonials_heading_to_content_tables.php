<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Per-record override copy for the testimonials section (App\Models\Review,
 * attached via the existing reviewable morph). Nullable and blank by
 * default — the frontend falls back to its own default heading/sub-heading
 * when these are null, so no admin has to touch anything for the section
 * to render correctly once a testimonial is attached.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['categories', 'services', 'locations', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('testimonials_heading')->nullable();
                $blueprint->string('testimonials_sub_heading')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['categories', 'services', 'locations', 'pages'] as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['testimonials_heading', 'testimonials_sub_heading']);
            });
        }
    }
};
