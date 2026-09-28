<?php

use App\Models\Blog;
use App\Models\Location;
use App\Models\Page;
use App\Models\Service;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $this->addLinkedSectionColumns();

        $this->createLinkedTable(
            'page_linked_services',
            'linked_service_id',
            'services',
            'page_linked_services_pk',
            'page_linked_services_lookup_idx'
        );
        $this->createLinkedTable(
            'page_linked_locations',
            'linked_location_id',
            'locations',
            'page_linked_locations_pk',
            'page_linked_locations_lookup_idx'
        );
        $this->createLinkedTable(
            'page_linked_faqs',
            'faq_id',
            'faqs',
            'page_linked_faqs_pk',
            'page_linked_faqs_lookup_idx'
        );
        $this->createLinkedTable(
            'page_linked_blogs',
            'linked_blog_id',
            'blogs',
            'page_linked_blogs_pk',
            'page_linked_blogs_lookup_idx'
        );

        $this->copyPageableLinks(Service::class, 'page_linked_services', 'linked_service_id');
        $this->copyPageableLinks(Location::class, 'page_linked_locations', 'linked_location_id');
        $this->copyPageableLinks(Blog::class, 'page_linked_blogs', 'linked_blog_id');
        $this->copyFaqableLinks();
    }

    public function down(): void
    {
        Schema::dropIfExists('page_linked_blogs');
        Schema::dropIfExists('page_linked_faqs');
        Schema::dropIfExists('page_linked_locations');
        Schema::dropIfExists('page_linked_services');

        if (! Schema::hasTable('pages')) {
            return;
        }

        $existing = array_values(array_filter(
            $this->linkedSectionColumns(),
            fn (string $column) => Schema::hasColumn('pages', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    protected function addLinkedSectionColumns(): void
    {
        $after = Schema::hasColumn('pages', 'button2_link') ? 'button2_link' : 'status';

        foreach ($this->linkedSectionColumnDefinitions() as $column => $type) {
            if (! Schema::hasColumn('pages', $column)) {
                Schema::table('pages', function (Blueprint $table) use ($column, $type, $after) {
                    match ($type) {
                        'text' => $table->text($column)->nullable()->after($after),
                        'url' => $table->string($column, 500)->nullable()->after($after),
                        default => $table->string($column)->nullable()->after($after),
                    };
                });
            }

            $after = $column;
        }
    }

    protected function createLinkedTable(
        string $tableName,
        string $relatedColumn,
        string $relatedTable,
        string $primaryName,
        string $lookupName
    ): void {
        if (Schema::hasTable($tableName) || ! Schema::hasTable($relatedTable)) {
            return;
        }

        Schema::create($tableName, function (Blueprint $table) use (
            $relatedColumn,
            $relatedTable,
            $primaryName,
            $lookupName
        ) {
            $table->unsignedBigInteger('page_id');
            $table->unsignedBigInteger($relatedColumn);
            $table->string('link_group', 16);
            $table->primary(['page_id', $relatedColumn, 'link_group'], $primaryName);
            $table->foreign('page_id')->references('id')->on('pages')->cascadeOnDelete();
            $table->foreign($relatedColumn)->references('id')->on($relatedTable)->cascadeOnDelete();
            $table->index([$relatedColumn, 'link_group'], $lookupName);
        });
    }

    protected function copyPageableLinks(string $modelClass, string $targetTable, string $targetColumn): void
    {
        if (! Schema::hasTable('pageables') || ! Schema::hasTable($targetTable)) {
            return;
        }

        DB::table('pageables')
            ->select(['page_id', 'pageable_id'])
            ->where('pageable_type', $modelClass)
            ->orderBy('page_id')
            ->orderBy('pageable_id')
            ->chunk(500, function ($rows) use ($targetTable, $targetColumn) {
                $records = $rows->map(fn ($row) => [
                    'page_id' => $row->page_id,
                    $targetColumn => $row->pageable_id,
                    'link_group' => 'v1',
                ])->all();

                if ($records !== []) {
                    DB::table($targetTable)->insertOrIgnore($records);
                }
            });
    }

    protected function copyFaqableLinks(): void
    {
        if (! Schema::hasTable('faqables') || ! Schema::hasTable('page_linked_faqs')) {
            return;
        }

        DB::table('faqables')
            ->select(['faqable_id', 'faq_id'])
            ->where('faqable_type', Page::class)
            ->orderBy('faqable_id')
            ->orderBy('faq_id')
            ->chunk(500, function ($rows) {
                $records = $rows->map(fn ($row) => [
                    'page_id' => $row->faqable_id,
                    'faq_id' => $row->faq_id,
                    'link_group' => 'v1',
                ])->all();

                if ($records !== []) {
                    DB::table('page_linked_faqs')->insertOrIgnore($records);
                }
            });
    }

    protected function linkedSectionColumnDefinitions(): array
    {
        $definitions = [];

        foreach (['services', 'locations', 'faqs', 'blogs'] as $resource) {
            for ($number = 1; $number <= 3; $number++) {
                $prefix = "linked_{$resource}_v{$number}";
                $definitions["{$prefix}_heading"] = 'string';
                $definitions["{$prefix}_sub_description"] = 'text';
                $definitions["{$prefix}_button_name"] = 'string';
                $definitions["{$prefix}_button_url"] = 'url';
            }
        }

        return $definitions;
    }

    protected function linkedSectionColumns(): array
    {
        return array_keys($this->linkedSectionColumnDefinitions());
    }
};
