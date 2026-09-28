<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('excel_imports', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 64);
            $table->string('original_filename');
            $table->string('stored_path');
            $table->string('lookup_field', 64);
            $table->string('mode', 32)->default('update_or_create');
            $table->boolean('dry_run')->default(false);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('failed_rows')->default(0);
            $table->json('metadata')->nullable();
            $table->json('row_errors')->nullable();
            $table->string('error_report_path')->nullable();
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('excel_exports', function (Blueprint $table) {
            $table->id();
            $table->string('resource', 64);
            $table->string('filename');
            $table->string('stored_path')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->json('columns')->nullable();
            $table->json('filters')->nullable();
            $table->json('metadata')->nullable();
            $table->string('disk')->default('local');
            $table->unsignedInteger('exported_rows')->default(0);
            $table->text('failure_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('excel_exports');
        Schema::dropIfExists('excel_imports');
    }
};
