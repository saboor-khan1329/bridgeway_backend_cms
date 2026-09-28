<?php

use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Upgrade known legacy branding only; preserve editor-authored values.
        foreach ([
            'site_name' => 'BridgeWay Digital',
            'copyright_text' => '© 2025 bridgewaydigital. All Rights Reserved',
        ] as $key => $value) {
            DB::table('site_settings')->where('key', $key)
                ->where('value', 'like', '%Intraguard%')
                ->update(['value' => $value, 'updated_at' => now()]);
        }

        DB::table('site_settings')->where('key', 'company_registration_number')->delete();
        Cache::forget(SiteSetting::CACHE_KEY);
    }

    public function down(): void
    {
        // Do not restore obsolete branding over editor-managed content.
    }
};
