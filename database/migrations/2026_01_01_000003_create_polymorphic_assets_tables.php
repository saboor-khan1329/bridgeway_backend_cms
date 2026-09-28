<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('slugs', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 191)->unique();
            $table->morphs('sluggable');
            $table->timestamps();
        });

        Schema::create('seo_metas', function (Blueprint $table) {
            $table->id();
            $table->morphs('seoable');
            $table->string('meta_title')->nullable();
            $table->text('meta_description')->nullable();
            $table->longText('seo_content')->nullable();
            $table->longText('schema')->nullable();
            $table->longText('microdata')->nullable();
            $table->boolean('enable_schema')->default(true);
            $table->boolean('enable_microdata')->default(false);
            $table->string('robots_index', 16)->default('index');
            $table->string('robots_follow', 16)->default('follow');
            $table->longText('og_tags')->nullable();
            $table->timestamps();
            $table->unique(['seoable_type', 'seoable_id'], 'seo_metas_unique_owner');
        });

        Schema::create('images', function (Blueprint $table) {
            $table->id();
            $table->morphs('imageable');
            $table->string('image_type', 64)->default('default');
            $table->string('path');
            $table->string('alt')->nullable();
            $table->timestamps();
            $table->index(['imageable_type', 'imageable_id', 'image_type'], 'images_owner_type_idx');
        });

        Schema::create('faqs', function (Blueprint $table) {
            $table->id();
            $table->string('question');
            $table->longText('answer');
            $table->boolean('status')->default(true);
            $table->timestamps();
        });

        Schema::create('faqables', function (Blueprint $table) {
            $table->unsignedBigInteger('faq_id');
            $table->morphs('faqable');
            $table->primary(['faq_id', 'faqable_type', 'faqable_id'], 'faqables_pk');
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
        });

        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('from_url', 500);
            $table->string('to_url', 500);
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->boolean('status')->default(true);
            $table->timestamps();
            $table->index('from_url');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('faqables');
        Schema::dropIfExists('faqs');
        Schema::dropIfExists('images');
        Schema::dropIfExists('seo_metas');
        Schema::dropIfExists('slugs');
    }
};
