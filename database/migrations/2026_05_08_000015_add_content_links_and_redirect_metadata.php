<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_linked_services', function (Blueprint $table) {
            $table->unsignedBigInteger('service_id');
            $table->unsignedBigInteger('linked_service_id');
            $table->string('link_group', 16);
            $table->primary(['service_id', 'linked_service_id', 'link_group'], 'service_linked_services_pk');
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->foreign('linked_service_id')->references('id')->on('services')->cascadeOnDelete();
            $table->index(['linked_service_id', 'link_group'], 'service_linked_services_lookup_idx');
        });

        Schema::create('location_links', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_location_id');
            $table->unsignedBigInteger('child_location_id');
            $table->primary(['parent_location_id', 'child_location_id'], 'location_links_pk');
            $table->foreign('parent_location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('child_location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->index('child_location_id');
        });

        Schema::create('blog_links', function (Blueprint $table) {
            $table->unsignedBigInteger('parent_blog_id');
            $table->unsignedBigInteger('child_blog_id');
            $table->primary(['parent_blog_id', 'child_blog_id'], 'blog_links_pk');
            $table->foreign('parent_blog_id')->references('id')->on('blogs')->cascadeOnDelete();
            $table->foreign('child_blog_id')->references('id')->on('blogs')->cascadeOnDelete();
            $table->index('child_blog_id');
        });

        Schema::create('location_service', function (Blueprint $table) {
            $table->unsignedBigInteger('location_id');
            $table->unsignedBigInteger('service_id');
            $table->primary(['location_id', 'service_id'], 'location_service_pk');
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::create('blog_service', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_id');
            $table->unsignedBigInteger('service_id');
            $table->primary(['blog_id', 'service_id'], 'blog_service_pk');
            $table->foreign('blog_id')->references('id')->on('blogs')->cascadeOnDelete();
            $table->foreign('service_id')->references('id')->on('services')->cascadeOnDelete();
        });

        Schema::create('blog_location', function (Blueprint $table) {
            $table->unsignedBigInteger('blog_id');
            $table->unsignedBigInteger('location_id');
            $table->primary(['blog_id', 'location_id'], 'blog_location_pk');
            $table->foreign('blog_id')->references('id')->on('blogs')->cascadeOnDelete();
            $table->foreign('location_id')->references('id')->on('locations')->cascadeOnDelete();
        });

        Schema::table('redirects', function (Blueprint $table) {
            $table->nullableMorphs('sourceable');
            $table->boolean('is_auto_generated')->default(false)->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('redirects', function (Blueprint $table) {
            $table->dropMorphs('sourceable');
            $table->dropIndex(['is_auto_generated']);
            $table->dropColumn('is_auto_generated');
        });

        Schema::dropIfExists('blog_location');
        Schema::dropIfExists('blog_service');
        Schema::dropIfExists('location_service');
        Schema::dropIfExists('blog_links');
        Schema::dropIfExists('location_links');
        Schema::dropIfExists('service_links');
        Schema::dropIfExists('service_linked_services');
    }
};
