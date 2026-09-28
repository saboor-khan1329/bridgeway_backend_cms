<?php

use App\Models\ContentPage;
use App\Models\ContentBlock;
use App\Support\ContentFieldStore;
use App\Support\SectionDocument;
use App\Support\StaticPageOwnership;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (ContentBlock::where('type', 'serContactForm')->with('fields')->get() as $block) {
                $data = $block->cmsData();
                if (isset($data['content']) && is_array($data['content'])) {
                    $data = array_replace($data, $data['content']);
                    unset($data['content']);
                    ContentFieldStore::replace($block, $data);
                }
            }
            foreach (ContentPage::whereIn('slug', StaticPageOwnership::SLUGS)->with('contentBlocks.fields')->get() as $page) {
                $sections = [];
                foreach (SectionDocument::forEditor($page) as $section) {
                    $section['data'] = StaticPageOwnership::dynamicFields($section['type'], $section['data']);
                    if ($section['data'] !== []) $sections[] = $section;
                }
                if ($sections !== []) {
                    $global = ContentPage::firstOrCreate(['slug' => $page->slug.'-sections'], [
                        'title' => $page->title.' — Forms and feeds', 'template' => 'global',
                        'is_cms_managed' => true, 'status' => true, 'sort_order' => $page->sort_order,
                    ]);
                    if ($global->wasRecentlyCreated) SectionDocument::sync($global, $sections);
                }
                // Keep the historical record for recovery; it is neither editable nor served.
                $page->update(['is_cms_managed' => false]);
            }
        });
    }

    public function down(): void
    {
        // Ownership is a deployment decision; do not overwrite newer editorial content.
    }
};
