<?php

namespace App\Services;

use App\Exports\ManagedExcelExport;
use App\Imports\ManagedExcelImport;
use App\Models\ExcelExport;
use App\Models\ExcelImport;
use App\Support\FrontendCache;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Facades\Excel;

class ExcelManagementService
{
    public function runImport(ExcelImport $import): void
    {
        $import->update(['status' => 'processing', 'started_at' => now(), 'failure_message' => null]);

        try {
            $config = $this->resource($import->resource);
            $operation = (string) data_get($import->metadata, 'operation', 'update');
            $rows = [];
            Excel::import(new ManagedExcelImport($rows), $import->stored_path);

            $errors = $this->validateRows($rows, $config, $import->lookup_field, $import->mode, $operation);
            $errorReportPath = $errors === [] ? null : $this->storeErrorReport($import, $errors);
            $import->update([
                'total_rows' => count($rows),
                'failed_rows' => count($errors),
                'row_errors' => $errors,
                'error_report_path' => $errorReportPath,
                'metadata' => array_merge($import->metadata ?? [], [
                    'columns' => $this->templateColumns($config, $operation, $import->lookup_field),
                    'operation' => $operation,
                ]),
            ]);

            if ($errors !== []) {
                $import->update([
                    'status' => 'failed',
                    'failure_message' => 'Import contains row-level validation errors. No data was written.',
                    'finished_at' => now(),
                ]);
                return;
            }

            if ($import->dry_run) {
                $import->update([
                    'status' => 'validated',
                    'processed_rows' => 0,
                    'failed_rows' => 0,
                    'row_errors' => [],
                    'finished_at' => now(),
                    'failure_message' => null,
                ]);
                return;
            }

            $currentRow = null;

            try {
                DB::transaction(function () use ($rows, $config, $import, &$currentRow) {
                    foreach ($rows as $index => $row) {
                        $currentRow = [
                            'number' => $index + 2,
                            'lookup' => $row[$import->lookup_field] ?? null,
                        ];

                        $this->persistRow($row, $config, $import->lookup_field, $import->mode, (string) data_get($import->metadata, 'operation', 'update'));
                    }
                });
            } catch (\Throwable $exception) {
                $runtimeErrors = [[
                    'row' => $currentRow['number'] ?? null,
                    'lookup' => $currentRow['lookup'] ?? null,
                    'errors' => [
                        'row' => [$exception->getMessage()],
                    ],
                ]];

                $import->update([
                    'status' => 'failed',
                    'failed_rows' => 1,
                    'row_errors' => $runtimeErrors,
                    'error_report_path' => $this->storeErrorReport($import, $runtimeErrors),
                    'failure_message' => 'Import failed while writing row '.($currentRow['number'] ?? 'unknown').'. No data was written.',
                    'finished_at' => now(),
                ]);

                return;
            } finally {
                if (DB::connection()->transactionLevel() === 0) {
                    $currentRow = null;
                }
            }

            FrontendCache::bump();

            $import->update([
                'status' => 'completed',
                'processed_rows' => count($rows),
                'failed_rows' => 0,
                'row_errors' => [],
                'finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $import->update([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    public function runExport(ExcelExport $export): void
    {
        $export->update(['status' => 'processing', 'started_at' => now(), 'failure_message' => null]);

        try {
            $config = $this->resource($export->resource);
            $columns = $this->columns($config, $export->columns ?: []);
            [$baseColumns, $relationColumns] = $this->splitColumns($config, $columns);
            $query = $this->query($config, $export->filters ?: []);
            // Computed columns are accessors (owner_type, owner_title...),
            // not real columns. Selecting them would be invalid SQL, and
            // selecting only the remaining ones would starve the accessors of
            // the fields they read — so a resource that declares any is
            // fetched whole. The over-fetch is a handful of columns on an
            // admin-triggered export, which is not worth optimising.
            $computed = $config['computed_columns'] ?? [];
            $selectColumns = array_values(array_diff($baseColumns, $computed));

            if ($computed !== []) {
                $selectColumns = ['*'];
            } elseif ($relationColumns !== [] && ! in_array('id', $selectColumns, true)) {
                $selectColumns[] = 'id';
            }

            $models = $query
                ->select($selectColumns !== [] ? $selectColumns : ['id'])
                ->with($this->relationNames($config, $relationColumns))
                ->get();
            $rows = $models->map(fn (Model $model) => $this->exportRow($model, $config, $columns));

            $path = 'excel/exports/'.$export->filename;
            Excel::store(new ManagedExcelExport($rows, $columns), $path, $export->disk ?: null);

            $export->update([
                'status' => 'completed',
                'stored_path' => $path,
                'exported_rows' => $rows->count(),
                'metadata' => array_merge($export->metadata ?? [], ['columns' => $columns]),
                'finished_at' => now(),
            ]);
        } catch (\Throwable $exception) {
            $export->update([
                'status' => 'failed',
                'failure_message' => $exception->getMessage(),
                'finished_at' => now(),
            ]);
        }
    }

    public function resourceOptions(): array
    {
        return collect(config('excel_management.resources', []))
            ->reject(fn (array $config) => ($config['visible'] ?? true) === false)
            ->mapWithKeys(fn (array $config, string $key) => [$key => $config['label'] ?? $key])
            ->all();
    }

    public function lookupOptions(string $resource): array
    {
        $config = $this->resource($resource);

        return collect($config['lookup_fields'] ?? [])->mapWithKeys(fn ($field) => [$field => $field])->all();
    }

    public function columnOptions(string $resource): array
    {
        $config = $this->resource($resource);

        return collect($this->allColumns($config))->mapWithKeys(fn ($field) => [$field => $field])->all();
    }

    public function templateExport(string $resource, string $operation = 'update', ?string $lookupField = null): ManagedExcelExport
    {
        $config = $this->resource($resource);
        $lookupField = $lookupField ?: ($config['lookup_fields'][0] ?? 'id');
        $columns = $this->templateColumns($config, $operation, $lookupField);
        $sample = collect($columns)->mapWithKeys(fn (string $column) => [
            $column => $this->sampleValue($config, $column, $lookupField, $operation),
        ]);

        return new ManagedExcelExport(collect([$sample]), $columns);
    }

    public function parseFilters(?string $json): array
    {
        if (blank($json)) {
            return [];
        }

        $decoded = json_decode((string) $json, true);

        if (! is_array($decoded)) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'filters' => 'Filters must be valid JSON.',
            ]);
        }

        foreach ($decoded as $index => $filter) {
            if (! is_array($filter) || ! array_key_exists('column', $filter) || ! array_key_exists('operator', $filter)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'filters' => 'Each filter must include column and operator keys. Invalid filter at index '.$index.'.',
                ]);
            }
        }

        return $decoded;
    }

    protected function validateRows(array $rows, array $config, string $lookupField, string $mode, string $operation): array
    {
        $errors = [];
        $columns = $this->allColumns($config);
        $baseColumns = $config['columns'];
        $relationColumns = array_keys($config['relations'] ?? []);
        $required = $operation === 'create'
            ? array_unique($config['required'] ?? [])
            : [$lookupField];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $unknown = array_values(array_diff(array_keys($row), $columns));
            $rules = [];

            foreach ($columns as $column) {
                $rules[$column] = in_array($column, $required, true) ? ['required'] : ['nullable'];

                if (in_array($column, $baseColumns, true) && (str_ends_with($column, '_id') || $column === 'id')) {
                    $rules[$column][] = 'integer';
                }

                if ($column === 'status' || $column === 'is_featured') {
                    $rules[$column][] = 'boolean';
                }
            }

            $validator = Validator::make(array_intersect_key($row, array_flip($columns)), $rules);

            if ($unknown !== []) {
                $validator->after(fn ($validator) => $validator->errors()->add('columns', 'Unknown columns: '.implode(', ', $unknown)));
            }

            if ($mode === 'update_only' && ! $this->findExisting($config, $lookupField, $row[$lookupField] ?? null)) {
                $validator->after(fn ($validator) => $validator->errors()->add($lookupField, 'No existing record found for update_only mode.'));
            }

            foreach ($relationColumns as $column) {
                if (! array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
                    continue;
                }

                try {
                    $ids = $this->parseRelationIds($row[$column]);
                    $single = (bool) data_get($config, "relations.{$column}.single", false);

                    if ($single && count($ids) > 1) {
                        $validator->after(fn ($validator) => $validator->errors()->add($column, 'Only one related ID is allowed.'));
                    }

                    $missing = $this->missingRelatedIds($config, $column, $ids);

                    if ($missing !== []) {
                        $validator->after(fn ($validator) => $validator->errors()->add($column, 'Related IDs not found: '.implode(', ', $missing)));
                    }
                } catch (\InvalidArgumentException $exception) {
                    $validator->after(fn ($validator) => $validator->errors()->add($column, $exception->getMessage()));
                }
            }

            foreach ($config['json_columns'] ?? [] as $column) {
                if (! array_key_exists($column, $row) || $row[$column] === null || $row[$column] === '') {
                    continue;
                }

                $decoded = is_array($row[$column])
                    ? $row[$column]
                    : json_decode((string) $row[$column], true);

                if (! is_array($decoded)) {
                    $validator->after(fn ($validator) => $validator->errors()->add($column, 'Enter a valid JSON object or array.'));
                }
            }

            if ($validator->fails()) {
                $errors[] = [
                    'row' => $rowNumber,
                    'lookup' => $row[$lookupField] ?? null,
                    'errors' => $validator->errors()->toArray(),
                ];
            }
        }

        return $errors;
    }

    protected function storeErrorReport(ExcelImport $import, array $errors): string
    {
        $rows = collect($errors)->map(fn (array $error) => [
            'row' => $error['row'] ?? null,
            'lookup' => $error['lookup'] ?? null,
            'errors' => json_encode($error['errors'] ?? [], JSON_UNESCAPED_SLASHES),
        ]);

        $path = 'excel/import-errors/import-'.$import->id.'-errors.xlsx';
        Excel::store(new ManagedExcelExport($rows, ['row', 'lookup', 'errors']), $path);

        return $path;
    }

    protected function persistRow(array $row, array $config, string $lookupField, string $mode, string $operation): void
    {
        $attributes = collect($row)
            ->only($config['columns'])
            // Export-only columns exist to make a spreadsheet readable
            // (owner_title and the like). They have no setter, so filling one
            // would try to write a column that is not there.
            ->except($config['export_only_columns'] ?? [])
            ->reject(fn ($value) => $value === null || $value === '')
            ->map(fn ($value, string $column) => in_array($column, $config['json_columns'] ?? [], true) && ! is_array($value)
                ? json_decode((string) $value, true)
                : $value)
            ->all();
        $relations = collect($row)->only(array_keys($config['relations'] ?? []))->all();

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $existing = $this->findExisting($config, $lookupField, $attributes[$lookupField] ?? null);

        if (! $existing && $mode === 'update_only') {
            return;
        }

        if ($existing) {
            unset($attributes['id']);
            if ($operation !== 'relations') {
                $existing->fill($attributes)->save();
            }
            $this->syncRelations($existing, $config, $relations);
            return;
        }

        $model = new $modelClass();
        if ($operation !== 'relations') {
            $model->fill($attributes)->save();
            $this->syncRelations($model, $config, $relations);
        }
    }

    protected function findExisting(array $config, string $lookupField, mixed $value): ?Model
    {
        if ($value === null || $value === '') {
            return null;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];

        $query = $modelClass::query();
        $this->applyModelConstraints($query, $config);

        return $query->where($lookupField, $value)->first();
    }

    protected function query(array $config, array $filters)
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $query = $modelClass::query();

        if (Schema::hasColumn((new $modelClass())->getTable(), 'status')) {
            $query->active();
        }

        $this->applyModelConstraints($query, $config);

        foreach ($filters as $filter) {
            if (! is_array($filter)) {
                continue;
            }

            $column = (string) ($filter['column'] ?? '');
            $operator = strtolower((string) ($filter['operator'] ?? '='));
            $value = $filter['value'] ?? null;

            if (! in_array($column, $config['columns'], true)) {
                continue;
            }

            if (! in_array($operator, ['=', '!=', '>', '>=', '<', '<=', 'like'], true)) {
                continue;
            }

            $query->where($column, $operator === 'like' ? 'LIKE' : $operator, $operator === 'like' ? '%'.$value.'%' : $value);
        }

        return $query;
    }

    protected function applyModelConstraints($query, array $config): void
    {
        foreach ((array) ($config['where_has'] ?? []) as $relation => $constraints) {
            $query->whereHas($relation, function ($relatedQuery) use ($constraints) {
                foreach ((array) $constraints as $field => $value) {
                    $relatedQuery->where($field, $value);
                }
            });
        }
    }

    protected function columns(array $config, array $requested): array
    {
        $allowed = $this->allColumns($config);
        $columns = array_values(array_intersect($requested, $allowed));

        return $columns !== [] ? $columns : $allowed;
    }

    public function templateColumns(array $config, string $operation, string $lookupField): array
    {
        // Export-only columns are stripped here rather than in each branch
        // below, so a template never offers a cell the import discards.
        $baseColumns = array_values(array_diff($config['columns'], $config['export_only_columns'] ?? []));
        $relationColumns = array_keys($config['relations'] ?? []);

        return match ($operation) {
            'create' => array_values(array_unique(array_merge(
                array_values(array_diff($baseColumns, ['id'])),
                $relationColumns,
            ))),
            'relations' => array_values(array_unique(array_merge([$lookupField], $relationColumns))),
            default => array_values(array_unique(array_merge([$lookupField], $baseColumns, $relationColumns))),
        };
    }

    protected function allColumns(array $config): array
    {
        return array_values(array_unique(array_merge($config['columns'] ?? [], array_keys($config['relations'] ?? []))));
    }

    protected function splitColumns(array $config, array $columns): array
    {
        $relationColumns = array_keys($config['relations'] ?? []);

        return [
            array_values(array_diff($columns, $relationColumns)),
            array_values(array_intersect($columns, $relationColumns)),
        ];
    }

    protected function relationNames(array $config, array $relationColumns): array
    {
        return collect($relationColumns)
            ->map(fn (string $column) => data_get($config, "relations.{$column}.relation"))
            ->filter()
            ->values()
            ->all();
    }

    protected function exportRow(Model $model, array $config, array $columns): array
    {
        return collect($columns)->mapWithKeys(function (string $column) use ($model, $config) {
            $relation = data_get($config, "relations.{$column}.relation");

            if ($relation) {
                return [$column => $model->{$relation}->pluck($model->{$relation}->first()?->getKeyName() ?? 'id')->implode(',')];
            }

            $value = $model->{$column};

            if (in_array($column, $config['json_columns'] ?? [], true) && is_array($value)) {
                $value = json_encode($value, JSON_UNESCAPED_SLASHES);
            }

            return [$column => $value];
        })->all();
    }

    protected function syncRelations(Model $model, array $config, array $relations): void
    {
        foreach ($relations as $column => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $relationName = data_get($config, "relations.{$column}.relation");

            if (! $relationName || ! method_exists($model, $relationName)) {
                continue;
            }

            $ids = $this->parseRelationIds($value);
            $pivot = data_get($config, "relations.{$column}.pivot", []);
            $syncPayload = $pivot === []
                ? $ids
                : collect($ids)->mapWithKeys(fn (int $id) => [$id => $pivot])->all();

            $model->{$relationName}()->sync($syncPayload);
        }
    }

    protected function parseRelationIds(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_unique(array_map('intval', $value)));
        }

        $normalized = trim((string) $value);

        if ($normalized === 'clear' || $normalized === '[]') {
            return [];
        }

        $parts = preg_split('/[,\s;]+/', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $ids = [];

        foreach ($parts as $part) {
            if (! ctype_digit($part)) {
                throw new \InvalidArgumentException('Relation values must be comma-separated numeric IDs, or clear.');
            }

            $ids[] = (int) $part;
        }

        return array_values(array_unique($ids));
    }

    protected function missingRelatedIds(array $config, string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $config['model'];
        $model = new $modelClass();
        $relationName = data_get($config, "relations.{$column}.relation");

        if (! $relationName || ! method_exists($model, $relationName)) {
            return $ids;
        }

        $relation = $model->{$relationName}();

        if (! $relation instanceof Relation) {
            return $ids;
        }

        $related = $relation->getRelated();
        $query = $related->newQuery()->whereIn($related->getKeyName(), $ids);

        if (Schema::hasColumn($related->getTable(), 'status') && method_exists($related, 'scopeActive')) {
            $query->active();
        }

        foreach ((array) data_get($config, "relations.{$column}.constraints", []) as $field => $value) {
            $query->where($field, $value);
        }

        $found = $query->pluck($related->getKeyName())
            ->map(fn ($id) => (int) $id)
            ->all();

        return array_values(array_diff($ids, $found));
    }

    protected function sampleValue(array $config, string $column, string $lookupField, string $operation): string
    {
        if ($column === $lookupField && $operation !== 'create') {
            return 'lookup value';
        }

        if (array_key_exists($column, $config['relations'] ?? [])) {
            return data_get($config, "relations.{$column}.single", false) ? '1' : '1,2,3';
        }

        if (in_array($column, $config['json_columns'] ?? [], true)) {
            return '{"heading":"Example heading"}';
        }

        return in_array($column, $config['required'] ?? [], true) ? 'required' : '';
    }

    protected function resource(string $resource): array
    {
        $config = config("excel_management.resources.{$resource}");

        if (! $config) {
            throw new \InvalidArgumentException('Unsupported Excel resource.');
        }

        return $config;
    }
}
