<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onboarding_form_configs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->json('form_config');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(false);
            $table->timestamps();
        });

        Schema::create('onboarding_submissions', function (Blueprint $table) {
            $table->id();
            $table->string('reference_number', 32)->unique();
            $table->foreignId('form_config_id')->constrained('onboarding_form_configs')->restrictOnDelete();
            $table->json('form_config_snapshot');
            $table->json('submission_data');
            $table->string('applicant_name', 255)->nullable();
            $table->string('applicant_email', 255)->nullable();
            $table->string('status', 50)->default('pending');
            $table->text('admin_notes')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->boolean('email_sent_to_admin')->default(false);
            $table->boolean('email_sent_to_applicant')->default(false);
            $table->timestamp('submitted_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'submitted_at']);
            $table->index('applicant_email');
        });

        Schema::create('onboarding_submission_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('submission_id')
                ->constrained('onboarding_submissions')
                ->cascadeOnDelete();
            $table->string('field_key', 120);
            $table->string('field_label', 255)->nullable();
            $table->unsignedSmallInteger('item_index')->nullable();
            $table->string('file_path', 500);
            $table->string('original_name', 255);
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('mime_type', 120)->nullable();
            $table->timestamps();

            $table->index(['submission_id', 'field_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onboarding_submission_files');
        Schema::dropIfExists('onboarding_submissions');
        Schema::dropIfExists('onboarding_form_configs');
    }
};
