<?php

use App\Models\ContentPage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The frontend's section-driven content pages.
 *
 * These are the pages the Next.js site shipped as JSON under
 * `frontend/src/data/pages` — the Amazon, development, SEO and company pages.
 * Each is a slug, a template name and an ordered list of sections, and the
 * sections themselves live in `content_blocks` exactly as a service page's do.
 *
 * Deliberately not folded into `pages`. That table models one fixed layout in
 * 114 columns — banner fields, six linked-service slots, six linked-location
 * slots — and a page here would leave all but four of them null while
 * inheriting an admin form built around them. Reuse here is of the
 * architecture, which is total: the image trait, the ContentBlock morph,
 * BaseFrontendController and its caching all work on this model unchanged.
 * Only the record is its own.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('content_pages')) {
            return;
        }

        Schema::create('content_pages', function (Blueprint $table) {
            $table->id();

            // The slug lives here rather than in the shared `slugs` table.
            //
            // That table enforces uniqueness across every model, which assumes
            // one flat URL namespace. This frontend has two: a page at
            // /angular-development-company and a service page at
            // /service/angular-development-company are different documents with
            // different content, and both are live. A single global slug string
            // cannot hold both, so a page's slug is unique among pages and the
            // route prefix keeps the two namespaces apart.
            $table->string('slug')->unique();

            $table->string('title');

            // The frontend template that renders this page ("amazon-service",
            // "service", "legal", …). Carried through to the payload because
            // the frontend's page mapper reads it; the backend never branches
            // on it.
            $table->string('template', 64)->default('service')->index();

            // The page's metadata block, stored and returned exactly as the
            // frontend authored it. The frontend already builds Next.js
            // metadata from this shape, so keeping it verbatim means moving
            // these pages into the database changes no rendered <head> tag.
            $table->json('seo')->nullable();

            $table->boolean('status')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();
        });
    }

    /**
     * The sections go with the table.
     *
     * `content_blocks` points at its owner through a morph, which has no
     * foreign key and so no cascade. Dropping this table alone would leave
     * every section behind as a row addressed to a page that no longer exists,
     * and because ids restart at 1 those orphans would then be silently
     * adopted by whatever was created next.
     */
    public function down(): void
    {
        if (Schema::hasTable('content_blocks')) {
            DB::table('content_blocks')
                ->where('blockable_type', ContentPage::class)
                ->delete();
        }

        Schema::dropIfExists('content_pages');
    }
};
