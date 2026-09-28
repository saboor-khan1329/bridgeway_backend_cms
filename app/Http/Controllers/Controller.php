<?php

namespace App\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Str;

class Controller extends BaseController
{
    use AuthorizesRequests, ValidatesRequests;

    protected function renderAdminIndex(Builder|Relation $query, array $config, string $view = 'admin.shared.index')
    {
        $request = request();
        $searchable = $config['search'] ?? array_keys($config['columns'] ?? []);

        $this->applyAdminSearch($query, $request, $searchable);

        $perPage = max(1, min((int) $request->query('per_page', 25), 100));
        $items = $query->paginate($perPage)->appends($request->query());

        return view($view, compact('items', 'config'));
    }

    protected function renderAdminShow(Model $item, array $config, string $view = 'admin.shared.show')
    {
        return view($view, compact('item', 'config'));
    }

    protected function applyAdminSearch(Builder|Relation $query, Request $request, array $columns): void
    {
        $term = Str::of((string) $request->query('q', ''))
            ->squish()
            ->limit(100, '')
            ->toString();

        if ($term === '' || $columns === []) {
            return;
        }

        $operator = $this->searchOperator($query);

        $query->where(function (Builder $inner) use ($columns, $term, $operator) {
            foreach ($columns as $column) {
                if (str_contains($column, '.')) {
                    $relation = Str::beforeLast($column, '.');
                    $col = Str::afterLast($column, '.');
                    $inner->orWhereHas($relation, function ($q) use ($col, $operator, $term) {
                        $this->applySearchColumn($q, $col, $operator, $term);
                    });
                } else {
                    $this->applySearchColumn($inner, $column, $operator, $term);
                }
            }
        });
    }

    protected function applySearchColumn(Builder $query, string $column, string $operator, string $term): void
    {
        if ($this->isIdColumn($column)) {
            if (ctype_digit($term)) {
                $query->orWhere($column, (int) $term);
            }

            return;
        }

        $query->orWhere($column, $operator, '%'.$term.'%');
    }

    protected function isIdColumn(string $column): bool
    {
        return $column === 'id' || str_ends_with($column, '_id');
    }

    protected function searchOperator(Builder|Relation $query): string
    {
        $model = $query instanceof Relation ? $query->getRelated() : $query->getModel();
        return $model->getConnection()->getDriverName() === 'pgsql' ? 'ILIKE' : 'LIKE';
    }
}
