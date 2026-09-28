<?php

use App\Support\FrontendCache;
use App\Models\SiteSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('site_settings')
            ->where('key', 'blog_default_author_image_alt')
            ->where('value', 'Blog author')
            ->update(['value' => 'blog-1-author-img']);
        Cache::forget(SiteSetting::CACHE_KEY);
        FrontendCache::bump();
    }

    public function down(): void
    {
        // Preserve the migrated content during a code rollback.
    }
};
