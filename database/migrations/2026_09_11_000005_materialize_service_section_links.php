<?php

use App\Models\ContentBlock;
use App\Support\ContentFieldStore;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        ContentBlock::query()->where('type', 'subService')->with('fields')->each(function (ContentBlock $block): void {
            $data = $block->cmsData();

            if (! empty($data['btnText']) && empty($data['btnHref'])) {
                $data['btnHref'] = '/get-a-free-quote';
                ContentFieldStore::replace($block, $data);
            }
        });
    }

    public function down(): void
    {
        // This migration materializes a previously rendered value. It is not
        // removed on rollback because doing so would discard editor changes.
    }
};
