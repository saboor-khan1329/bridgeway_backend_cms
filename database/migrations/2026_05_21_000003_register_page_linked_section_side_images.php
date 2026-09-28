<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        // Side images are stored in the existing polymorphic images table by image_type.
    }

    public function down(): void
    {
        // No schema changes are made by this migration.
    }
};
