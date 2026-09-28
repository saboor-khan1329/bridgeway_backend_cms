<?php

namespace App\Services;

use App\Models\Category;
use Illuminate\Support\Facades\DB;

class CategoryTreeService
{
    public static function attach(Category $category): void
    {
        DB::transaction(function () use ($category) {
            self::syncNode($category->fresh());
        });
    }

    public static function rebuild(Category $category): void
    {
        DB::transaction(function () use ($category) {
            self::syncSubtree($category->fresh());
        });
    }

    protected static function syncSubtree(Category $category): void
    {
        self::syncNode($category);

        Category::query()
            ->where('parent_id', $category->id)
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->each(fn (Category $child) => self::syncSubtree($child));
    }

    protected static function syncNode(Category $category): void
    {
        $depth = $category->parent_id
            ? (int) Category::query()->whereKey($category->parent_id)->value('depth') + 1
            : 0;

        if ((int) $category->depth !== $depth) {
            $category->forceFill(['depth' => $depth])->saveQuietly();
        }
    }
}
