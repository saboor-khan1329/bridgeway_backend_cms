<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class ContentLinkGraphService
{
    public function sanitizeIds(array $ids): array
    {
        return collect($ids)
            ->filter(fn ($id) => filled($id))
            ->map(fn ($id) => (int) $id)
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function validate(
        string $table,
        string $parentColumn,
        string $childColumn,
        ?int $currentId,
        array $parentIds,
        array $childIds,
        string $entityLabel
    ): ?string {
        if (array_intersect($parentIds, $childIds) !== []) {
            return "The same {$entityLabel} cannot be selected as both parent and child.";
        }

        if ($currentId !== null && (in_array($currentId, $parentIds, true) || in_array($currentId, $childIds, true))) {
            return "A {$entityLabel} cannot reference itself.";
        }

        $nodeId = $currentId ?? 0;
        $adjacency = $this->adjacency($table, $parentColumn, $childColumn, $nodeId, $parentIds, $childIds);

        return $this->introducesCycle($nodeId, $adjacency)
            ? "Select {$entityLabel} parents and children that do not create a circular relationship."
            : null;
    }

    protected function adjacency(
        string $table,
        string $parentColumn,
        string $childColumn,
        int $currentId,
        array $parentIds,
        array $childIds
    ): array {
        $rows = DB::table($table)
            ->select([$parentColumn, $childColumn])
            ->where($parentColumn, '!=', $currentId)
            ->where($childColumn, '!=', $currentId)
            ->get();

        $adjacency = [];

        foreach ($rows as $row) {
            $parentId = (int) $row->{$parentColumn};
            $childId = (int) $row->{$childColumn};

            $adjacency[$parentId][] = $childId;
        }

        foreach ($parentIds as $parentId) {
            $adjacency[$parentId][] = $currentId;
        }

        foreach ($childIds as $childId) {
            $adjacency[$currentId][] = $childId;
        }

        return array_map(fn (array $children) => array_values(array_unique($children)), $adjacency);
    }

    protected function introducesCycle(int $currentId, array $adjacency): bool
    {
        $stack = collect($adjacency[$currentId] ?? [])
            ->map(fn (int $childId) => [$childId, [$currentId]])
            ->all();

        while ($stack !== []) {
            [$node, $path] = array_pop($stack);

            if ($node === $currentId) {
                return true;
            }

            if (in_array($node, $path, true)) {
                continue;
            }

            $nextPath = [...$path, $node];

            foreach ($adjacency[$node] ?? [] as $childId) {
                $stack[] = [$childId, $nextPath];
            }
        }

        return false;
    }
}
