<?php

use App\Models\ContentPage;
use App\Support\ContentFieldStore;
use App\Support\FrontendCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            foreach (ContentPage::whereIn('slug', ['service', 'local-seo-services', 'international-seo-services', 'technical-seo-agency'])
                ->with('contentBlocks.fields')->get() as $page) {
                foreach ($page->contentBlocks as $block) {
                    $data = $block->cmsData();
                    if ($block->section_key === 'sub-service-2' && ! array_key_exists('btnHref', $data)) {
                        $data['btnHref'] = '/get-a-free-quote';
                    }
                    if ($block->section_key === 'ser-contact-form'
                        && ($data['formDescription'] ?? '') === "Complete the form below and we'll contact you with further instructions and details about your project.") {
                        $data['formDescription'] = "Complete the form below and we\u{2019}ll contact you with further instructions and details about your project.";
                    }
                    ContentFieldStore::replace($block, $data);
                }
            }
        });
        FrontendCache::bump();
    }

    public function down(): void
    {
        // Preserve the original website content on rollback.
    }
};
