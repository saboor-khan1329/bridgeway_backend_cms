<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Allow an inquiry to be stored without an email address.
 *
 * Every form on the site asks for an email except one: the short prompt on a
 * service page hero is a single "Enter your details" box, and a visitor may
 * type a phone number into it. That is a real lead, and the alternatives were
 * both worse than relaxing the column — reject the submission, or invent an
 * address and hand an admin something that bounces.
 *
 * Only the constraint changes; the column keeps its type and every existing
 * row keeps its value. Nothing stops storing an email, and the forms that ask
 * for one still require it — see FrontendFormRegistry, where `email` remains
 * `required` for all five.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('inquiries') || ! Schema::hasColumn('inquiries', 'email')) {
            return;
        }

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('inquiries') || ! Schema::hasColumn('inquiries', 'email')) {
            return;
        }

        // A row with no email would fail the constraint on the way back, so
        // those are given a placeholder rather than blocking the rollback.
        \Illuminate\Support\Facades\DB::table('inquiries')
            ->whereNull('email')
            ->update(['email' => 'unknown@example.invalid']);

        Schema::table('inquiries', function (Blueprint $table) {
            $table->string('email')->nullable(false)->change();
        });
    }
};
