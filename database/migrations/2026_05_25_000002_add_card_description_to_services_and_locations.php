<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->text('card_description')->nullable()->after('short_description');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->text('card_description')->nullable()->after('short_description');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            $table->dropColumn('card_description');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->dropColumn('card_description');
        });
    }
};
