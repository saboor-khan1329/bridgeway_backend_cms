<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mail_dispatches', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64)->index();
            $table->string('status', 32)->index();
            $table->string('mailer', 64)->nullable();
            $table->string('queue', 64)->nullable();
            $table->json('recipients');
            $table->json('payload')->nullable();
            $table->string('related_type', 191)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('max_attempts')->default(10);
            $table->timestamp('available_at')->nullable()->index();
            $table->timestamp('locked_at')->nullable();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->longText('error_message')->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at'], 'mail_dispatches_status_available_idx');
            $table->index(['type', 'status', 'created_at'], 'mail_dispatches_type_status_created_idx');
            $table->index(['related_type', 'related_id'], 'mail_dispatches_related_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mail_dispatches');
    }
};
