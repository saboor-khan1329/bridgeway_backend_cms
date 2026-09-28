<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per day per metered event — a running self-tracked count of the
 * Service Finder's own calls into Google's paid APIs, so the admin usage
 * graph has something to draw without needing Google Cloud Billing
 * credentials. See FinderUsageService for what counts as an event and why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finder_usage_daily', function (Blueprint $table) {
            $table->id();
            $table->date('date')->index();
            $table->string('event', 40);
            $table->unsignedInteger('count')->default(0);
            $table->timestamps();

            $table->unique(['date', 'event']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finder_usage_daily');
    }
};
