<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('database_backups');
    }

    public function down(): void
    {
        // Database management has been removed.
    }
};
