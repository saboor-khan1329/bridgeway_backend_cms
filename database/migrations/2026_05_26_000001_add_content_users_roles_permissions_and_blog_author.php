<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (! Schema::hasColumn('users', 'display_name')) {
                    $table->string('display_name')->nullable()->after('name');
                }
                if (! Schema::hasColumn('users', 'job_title')) {
                    $table->string('job_title')->nullable()->after('display_name');
                }
                if (! Schema::hasColumn('users', 'bio')) {
                    $table->text('bio')->nullable()->after('job_title');
                }
                if (! Schema::hasColumn('users', 'status')) {
                    $table->boolean('status')->default(true)->after('password');
                    $table->index('status', 'users_status_idx');
                }
                if (! Schema::hasColumn('users', 'is_root')) {
                    $table->boolean('is_root')->default(false)->after('status');
                    $table->index('is_root', 'users_is_root_idx');
                }
            });
        }

        if (Schema::hasTable('blogs') && Schema::hasTable('users') && ! Schema::hasColumn('blogs', 'author_user_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->unsignedBigInteger('author_user_id')->nullable()->after('author');
                $table->foreign('author_user_id', 'blogs_author_user_id_fk')
                    ->references('id')
                    ->on('users')
                    ->nullOnDelete();
                $table->index('author_user_id', 'blogs_author_user_id_idx');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('blogs') && Schema::hasColumn('blogs', 'author_user_id')) {
            Schema::table('blogs', function (Blueprint $table) {
                $table->dropForeign('blogs_author_user_id_fk');
                $table->dropIndex('blogs_author_user_id_idx');
                $table->dropColumn('author_user_id');
            });
        }

        if (Schema::hasTable('users')) {
            Schema::table('users', function (Blueprint $table) {
                if (Schema::hasColumn('users', 'is_root')) {
                    $table->dropIndex('users_is_root_idx');
                    $table->dropColumn('is_root');
                }
                if (Schema::hasColumn('users', 'status')) {
                    $table->dropIndex('users_status_idx');
                    $table->dropColumn('status');
                }
                foreach (['bio', 'job_title', 'display_name'] as $column) {
                    if (Schema::hasColumn('users', $column)) {
                        $table->dropColumn($column);
                    }
                }
            });
        }
    }
};
