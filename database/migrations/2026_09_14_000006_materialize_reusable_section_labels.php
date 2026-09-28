<?php

use App\Models\ContentBlock;
use App\Support\ContentFieldStore;
use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (ContentBlock::whereIn('type', ['expertTeam', 'marketing', 'aboutSolution'])->with('fields')->get() as $block) {
                $defaults = $block->type === 'expertTeam' ? [
                    'labels' => ['organization' => 'ORGANIZATION:', 'challenge' => 'CHALLENGE',
                        'initiatives' => 'RECENT INITIATIVES', 'result' => 'RESULT'],
                    'imgAlt' => 'Expert Team',
                ] : ['linkText' => 'LEARN MORE'];
                ContentFieldStore::replace($block, array_replace_recursive($defaults, $block->cmsData()));
            }
        });
        FrontendCache::bump();
    }

    public function down(): void
    {
        // The former frontend labels are now authored database content.
    }
};
