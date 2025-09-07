<?php

namespace App\Http\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

trait HandlesQueryFiltering
{
    protected function applyFilters(Request $request, Builder $query, array $searchableColumns, string $defaultSort, array $sortableColumns): Builder
    {
        // Búsqueda
        $query->when($request->input('search'), function ($q, $search) use ($searchableColumns) {
            $q->where(function ($subQ) use ($search, $searchableColumns) {
                foreach ($searchableColumns as $column) {
                    $subQ->orWhere($column, 'like', "%{$search}%");
                }
            });
        });

        // Ordenamiento
        $sortBy = $request->input('sort_by', $defaultSort);
        $sortDirection = $request->input('sort_direction', 'asc');

        if (in_array($sortBy, $sortableColumns)) {
            $query->orderBy($sortBy, $sortDirection);
        }

        return $query;
    }
}