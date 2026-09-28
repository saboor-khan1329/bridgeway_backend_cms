<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pages') && ! Schema::hasColumn('pages', 'template_name')) {
            Schema::table('pages', function (Blueprint $table) {
                $table->string('template_name', 32)->default('static_v1')->after('page_title')->index();
            });
        }

        if (! Schema::hasTable('inquiries')) {
            return;
        }

        if (! Schema::hasColumn('inquiries', 'spam_score')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->unsignedSmallInteger('spam_score')->default(0)->after('user_agent');
            });
        }

        if (! Schema::hasColumn('inquiries', 'spam_reasons')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->json('spam_reasons')->nullable()->after('spam_score');
            });
        }

        if (! Schema::hasColumn('inquiries', 'captcha_passed')) {
            Schema::table('inquiries', function (Blueprint $table) {
                $table->boolean('captcha_passed')->default(false)->after('spam_reasons');
            });
        }
    }

    public function down(): void
    {
        $this->dropColumns('inquiries', ['captcha_passed', 'spam_reasons', 'spam_score']);
        $this->dropColumns('pages', ['template_name']);
    }

    protected function dropColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $existing = array_values(array_filter(
            $columns,
            fn (string $column) => Schema::hasColumn($tableName, $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }
};
