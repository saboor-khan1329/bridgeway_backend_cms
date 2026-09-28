<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pages', function (Blueprint $table) {
            $table->id();
            $table->string('page_title');
            $table->string('template_name', 32)->default('static_v1')->index();
            $table->timestamp('last_updated_at')->nullable();
            $table->string('banner_title')->nullable();
            $table->text('banner_description')->nullable();
            $table->text('banner_short_description')->nullable();
            $table->string('button1_name')->nullable();
            $table->string('button1_link')->nullable();
            $table->string('button2_name')->nullable();
            $table->string('button2_link')->nullable();
            $table->longText('page_content')->nullable();
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('pageables', function (Blueprint $table) {
            $table->unsignedBigInteger('page_id');
            $table->morphs('pageable');
            $table->primary(['page_id', 'pageable_type', 'pageable_id'], 'pageables_pk');
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pageables');
        Schema::dropIfExists('pages');
    }
};
