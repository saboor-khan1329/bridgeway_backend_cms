<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Category;
use App\Services\CategoryTreeService;
use Illuminate\Support\Facades\DB;

class FixCategoryTree extends Command
{
    protected $signature = 'category:fix-tree';
    protected $description = 'Rebuild category depth values from parent_id links';

    public function handle()
    {
        $this->info('Starting category tree fix...');

        DB::transaction(function () {

            // 1. FIX DEPTH RECURSIVELY
            $this->fixNode(null, 0);

            // 2. NORMALIZE ANY SUBTREES UPDATED BY MODEL EVENTS
            Category::orderBy('id')->chunk(200, function ($categories) {
                foreach ($categories as $category) {
                    CategoryTreeService::attach($category);
                }
            });
        });

        $this->info('✅ Category tree fixed successfully!');
    }

    private function fixNode($parentId, int $depth)
    {
        $children = Category::where('parent_id', $parentId)
            ->orderBy('name')
            ->get();

        foreach ($children as $child) {

            // FIX DEPTH
            $child->update([
                'depth' => $depth,
            ]);

            $this->line("Fixed: {$child->name} (Depth: {$depth})");

            // RECURSIVE
            $this->fixNode($child->id, $depth + 1);
        }
    }
}
