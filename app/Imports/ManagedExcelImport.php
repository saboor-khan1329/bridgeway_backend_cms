<?php

namespace App\Imports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class ManagedExcelImport implements ToCollection, WithHeadingRow
{
    public function __construct(private array &$rows)
    {
    }

    public function collection(Collection $collection): void
    {
        $this->rows = $collection
            ->map(fn ($row) => collect($row)->mapWithKeys(
                fn ($value, $key) => [(string) $key => is_string($value) ? trim($value) : $value]
            )->all())
            ->filter(fn (array $row) => collect($row)->filter(fn ($value) => $value !== null && $value !== '')->isNotEmpty())
            ->values()
            ->all();
    }
}
