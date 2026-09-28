<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->string('disk', 50)->default('public')->after('path');
            $table->string('title')->nullable()->after('alt');
            $table->text('caption')->nullable()->after('title');
            $table->string('mime_type', 191)->nullable()->after('caption');
            $table->unsignedBigInteger('size_bytes')->nullable()->after('mime_type');
            $table->unsignedInteger('width')->nullable()->after('size_bytes');
            $table->unsignedInteger('height')->nullable()->after('width');
            $table->string('source', 32)->default('upload')->after('height');
            $table->index(['disk', 'path'], 'images_disk_path_idx');
        });
    }

    public function down(): void
    {
        Schema::table('images', function (Blueprint $table) {
            $table->dropIndex('images_disk_path_idx');
            $table->dropColumn([
                'disk',
                'title',
                'caption',
                'mime_type',
                'size_bytes',
                'width',
                'height',
                'source',
            ]);
        });
    }
};
