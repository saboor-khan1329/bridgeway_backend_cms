<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->string('author_name');
            $table->string('author_role')->nullable();
            $table->string('company_name')->nullable();
            $table->string('title')->nullable();
            $table->longText('content');
            $table->unsignedTinyInteger('rating')->default(5);
            $table->boolean('status')->default(true);
            $table->boolean('is_testimonial')->default(false);
            $table->timestamps();
            $table->index(['status', 'is_testimonial']);
        });

        Schema::create('reviewables', function (Blueprint $table) {
            $table->unsignedBigInteger('review_id');
            $table->morphs('reviewable');
            $table->primary(['review_id', 'reviewable_type', 'reviewable_id'], 'reviewables_pk');
            $table->foreign('review_id')->references('id')->on('reviews')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviewables');
        Schema::dropIfExists('reviews');
    }
};
