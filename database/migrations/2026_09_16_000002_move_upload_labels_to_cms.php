<?php

use App\Models\ContentBlock;
use App\Support\ContentFieldStore;
use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (ContentBlock::where('type', 'serContactForm')->with('fields')->get() as $block) {
            $data = $block->cmsData();
            $data['uploadLabel'] ??= 'Upload Reference/Sample (can send later as well)';
            $data['uploadAccessibleLabel'] ??= 'Upload reference or sample file';
            ContentFieldStore::replace($block, $data);
        }
        FrontendCache::bump();
    }

    public function down(): void {}
};
