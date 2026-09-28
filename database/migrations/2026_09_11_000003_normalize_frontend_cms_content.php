<?php

use App\Support\ContentFieldStore;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_pages', function (Blueprint $table) {
            $table->boolean('is_cms_managed')->default(false)->index();
        });

        DB::table('content_pages')->whereIn('slug', [
            'amazon-marketing-services',
            'amazon-photography-service',
            'amazon-ppc-management-services',
            'amazon-product-listing-services',
            'amazon-product-research-services',
            'amazon-seo-services',
            'angular-development-company',
            'web-development-company',
            'blogs',
            'get-a-free-quote',
        ])->update(['is_cms_managed' => true]);

        Schema::create('content_block_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('content_block_id')->constrained('content_blocks')->cascadeOnDelete();
            $table->string('path', 1000);
            $table->string('value_type', 16);
            $table->longText('value')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
            $table->unique(['content_block_id', 'path'], 'content_block_fields_owner_path_unique');
            $table->index(['content_block_id', 'sort_order'], 'content_block_fields_order_idx');
        });

        DB::table('content_blocks')->orderBy('id')->chunkById(100, function ($blocks): void {
            foreach ($blocks as $row) {
                $data = is_string($row->data ?? null) ? json_decode($row->data, true) : $row->data;
                $block = new \App\Models\ContentBlock();
                $block->setRawAttributes((array) $row, true);
                ContentFieldStore::replace($block, is_array($data) ? $data : []);
            }
        });

        Schema::table('seo_metas', function (Blueprint $table) {
            $table->string('canonical_url', 500)->nullable();
            $table->text('keywords')->nullable();
            $table->string('og_title')->nullable();
            $table->text('og_description')->nullable();
            $table->string('og_type', 40)->nullable();
            $table->string('og_url', 500)->nullable();
            $table->string('og_image', 1000)->nullable();
            $table->string('twitter_card', 40)->nullable();
            $table->string('twitter_title')->nullable();
            $table->text('twitter_description')->nullable();
            $table->string('twitter_image', 1000)->nullable();
        });

        DB::table('content_pages')->orderBy('id')->chunkById(100, function ($pages): void {
            foreach ($pages as $page) {
                $seo = is_string($page->seo ?? null) ? json_decode($page->seo, true) : $page->seo;
                if (! is_array($seo) || $seo === []) {
                    continue;
                }

                $og = is_array($seo['openGraph'] ?? null) ? $seo['openGraph'] : [];
                $twitter = is_array($seo['twitter'] ?? null) ? $seo['twitter'] : [];
                $ogImage = is_array($og['images'] ?? null) ? ($og['images'][0] ?? null) : ($og['images'] ?? null);
                $twitterImage = is_array($twitter['images'] ?? null) ? ($twitter['images'][0] ?? null) : ($twitter['images'] ?? null);
                $imageValue = fn ($image) => is_array($image) ? ($image['url'] ?? $image['src'] ?? null) : $image;

                DB::table('seo_metas')->updateOrInsert([
                    'seoable_type' => \App\Models\ContentPage::class,
                    'seoable_id' => $page->id,
                ], [
                    'meta_title' => $seo['title'] ?? null,
                    'meta_description' => $seo['description'] ?? null,
                    'canonical_url' => $seo['canonical'] ?? null,
                    'keywords' => is_array($seo['keywords'] ?? null) ? implode(', ', $seo['keywords']) : ($seo['keywords'] ?? null),
                    'robots_index' => ($seo['robots']['index'] ?? true) === false ? 'noindex' : 'index',
                    'robots_follow' => ($seo['robots']['follow'] ?? true) === false ? 'nofollow' : 'follow',
                    'og_title' => $og['title'] ?? null,
                    'og_description' => $og['description'] ?? null,
                    'og_type' => $og['type'] ?? null,
                    'og_url' => $og['url'] ?? null,
                    'og_image' => $imageValue($ogImage),
                    'twitter_card' => $twitter['card'] ?? null,
                    'twitter_title' => $twitter['title'] ?? null,
                    'twitter_description' => $twitter['description'] ?? null,
                    'twitter_image' => $imageValue($twitterImage),
                    'enable_schema' => true,
                    'enable_microdata' => false,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        });

        Schema::table('content_blocks', fn (Blueprint $table) => $table->dropColumn('data'));
        Schema::table('content_pages', fn (Blueprint $table) => $table->dropColumn('seo'));
    }

    public function down(): void
    {
        Schema::table('content_blocks', fn (Blueprint $table) => $table->json('data')->nullable());
        Schema::table('content_pages', fn (Blueprint $table) => $table->json('seo')->nullable());
        Schema::table('content_pages', fn (Blueprint $table) => $table->dropColumn('is_cms_managed'));

        $columns = [
            'canonical_url', 'keywords', 'og_title', 'og_description', 'og_type',
            'og_url', 'og_image', 'twitter_card', 'twitter_title',
            'twitter_description', 'twitter_image',
        ];
        Schema::table('seo_metas', fn (Blueprint $table) => $table->dropColumn($columns));
        Schema::dropIfExists('content_block_fields');
    }
};
