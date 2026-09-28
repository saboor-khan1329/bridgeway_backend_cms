<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $after = Schema::hasColumn('pages', 'linked_blogs_v3_button_url')
            ? 'linked_blogs_v3_button_url'
            : (Schema::hasColumn('pages', 'button2_link') ? 'button2_link' : 'status');

        foreach ($this->columnDefinitions() as $column => $type) {
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

    public function down(): void
    {
        if (! Schema::hasTable('pages')) {
            return;
        }

        $existing = array_values(array_filter(
            array_keys($this->columnDefinitions()),
            fn (string $column) => Schema::hasColumn('pages', $column)
        ));

        if ($existing === []) {
            return;
        }

        Schema::table('pages', function (Blueprint $table) use ($existing) {
            $table->dropColumn($existing);
        });
    }

    protected function columnDefinitions(): array
    {
        $definitions = [];

        foreach (['services', 'locations', 'faqs', 'blogs'] as $resource) {
            for ($number = 4; $number <= 6; $number++) {
                $prefix = "linked_{$resource}_v{$number}";
                $definitions["{$prefix}_heading"] = 'string';
                $definitions["{$prefix}_sub_description"] = 'text';
                $definitions["{$prefix}_button_name"] = 'string';
                $definitions["{$prefix}_button_url"] = 'url';
            }
        }

        return $definitions;
    }
};
