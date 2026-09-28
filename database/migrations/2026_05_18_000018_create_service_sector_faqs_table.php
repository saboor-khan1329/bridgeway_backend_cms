<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('service_sector_faqs')) {
            return;
        }

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
    }
};
