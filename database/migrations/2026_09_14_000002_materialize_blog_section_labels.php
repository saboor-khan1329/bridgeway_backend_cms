<?php

use App\Models\ContentBlock;
use App\Support\ContentFieldStore;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (ContentBlock::where('type', 'blogsHero')->with('fields')->get() as $block) {
            $data = $block->cmsData();
            $data += ['popularTitle' => 'Most Popular', 'recentTitle' => 'Recently Added'];
            ContentFieldStore::replace($block, $data);
        }
    }

    public function down(): void
    {
        // Keep editor-managed labels when rolling back application code.
    }
};
