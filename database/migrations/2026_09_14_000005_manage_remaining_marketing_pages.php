<?php

use App\Models\ContentPage;
use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ContentPage::whereIn('slug', [
            'home', 'about-us', 'portfolio', 'digital-marketing-services',
            'local-seo-services', 'international-seo-services', 'technical-seo-agency',
            'video-animation-services', 'service', 'service-two',
        ])->update(['is_cms_managed' => true]);
        FrontendCache::bump();
    }

    public function down(): void
    {
        // Keep authored pages manageable; rolling code back must not hide content.
    }
};
