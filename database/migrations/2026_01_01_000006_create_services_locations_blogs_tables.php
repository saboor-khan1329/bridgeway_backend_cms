<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $contentTable = function (string $name, bool $hasDescription = true) {
            Schema::create($name, function (Blueprint $table) use ($hasDescription) {
                $table->id();
                $table->string('title');
                $table->text('short_description')->nullable();
                if ($hasDescription) {
                    $table->longText('description')->nullable();
                }
                $table->string('banner_title')->nullable();
                $table->text('banner_description')->nullable();
                if (! $hasDescription) {
                    $table->string('linked_services_v1_heading')->nullable();
                    $table->text('linked_services_v1_sub_description')->nullable();
                    $table->string('linked_services_v2_heading')->nullable();
                    $table->text('linked_services_v2_sub_description')->nullable();
                    $table->string('linked_services_v3_heading')->nullable();
                    $table->text('linked_services_v3_sub_description')->nullable();
                    $table->json('section_2_sectors_faqs')->nullable();
                    $table->json('section_3_sectors_faqs')->nullable();
                }
                $table->boolean('status')->default(true);
                $table->boolean('is_featured')->default(false);
                $table->timestamps();
                $table->index('status');
            });
        };

        $contentTable('services', false);
        $contentTable('locations');

        Schema::create('blogs', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('short_description')->nullable();
            $table->longText('content')->nullable();
            $table->string('author')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('status')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
            $table->index(['status', 'published_at']);
        });

        $pivot = function (string $owner, string $ownerKey) {
            Schema::create($owner.'_category', function (Blueprint $table) use ($owner, $ownerKey) {
                $table->unsignedBigInteger($ownerKey);
                $table->unsignedBigInteger('category_id');
                $table->primary([$ownerKey, 'category_id'], $owner.'_category_pk');
                $table->foreign($ownerKey)->references('id')->on($owner)->cascadeOnDelete();
                $table->foreign('category_id')->references('id')->on('categories')->cascadeOnDelete();
            });
        };

        $pivot('services', 'service_id');
        $pivot('locations', 'location_id');
        $pivot('blogs', 'blog_id');

        Schema::create('service_sector_faqs', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('faq_id');
            $table->string('section_key', 32);
            $table->primary(['service_id', 'faq_id', 'section_key'], 'service_sector_faqs_pk');
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('faq_id')->references('id')->on('faqs')->cascadeOnDelete();
            $table->index(['faq_id', 'section_key'], 'service_sector_faqs_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_sector_faqs');
        Schema::dropIfExists('blogs_category');
        Schema::dropIfExists('locations_category');
        Schema::dropIfExists('services_category');
        Schema::dropIfExists('blogs');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('services');
    }
};
