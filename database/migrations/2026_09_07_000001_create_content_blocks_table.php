<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Content blocks — the admin-authored sections that service, sector and
 * location pages render underneath their existing fixed sections.
 *
 * One table rather than four: the four components introduced alongside this
 * migration (feature cards, packages, cost factors, process steps) all share
 * the same shape — an optional heading, an optional intro, and an ordered
 * list of repeatable items — and differ only in what a single item holds.
 * Keeping that in `items` as JSON means a fifth component later is a new
 * `type` string and a frontend component, not another migration plus another
 * admin module.
 *
 * Purely additive: nothing here touches an existing table, so an existing
 * database keeps every row it had. A page with no rows in this table renders
 * exactly as it does today.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();

            // Page this block belongs to — Service (covers sector pages, same
            // model), Location, or Page. morphs() indexes the pair for us.
            $table->morphs('blockable');

            // Which component renders it — see App\Models\ContentBlock::TYPES.
            $table->string('type', 40)->index();

            $table->string('heading')->nullable();
            $table->text('intro')->nullable();

            // The repeatable rows. Shape is per-type and validated in the
            // admin layer rather than by the column, which is the whole point
            // of keeping it JSON.
            $table->json('items')->nullable();

            // Per-type extras that are not repeatable — a CTA label/url, a
            // layout choice. Kept separate from `items` so a reorder of the
            // list can never disturb them.
            $table->json('options')->nullable();

            $table->unsignedSmallInteger('sort_order')->default(0);

            // Lets an operator hide a finished block without deleting the
            // content they wrote. The frontend also hides any block with no
            // items at all, so an empty block is never a broken-looking gap.
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // One block of each type per page. The four components are each a
            // distinct, named section in the design and appear at most once,
            // so this turns "edit this page's packages block" into a single
            // lookup and makes accidental duplicates impossible. If a second
            // instance is ever wanted, dropping this index is the only change.
            $table->unique(['blockable_type', 'blockable_id', 'type'], 'content_blocks_owner_type_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
    }
};
