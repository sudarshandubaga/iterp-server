<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\City;

/**
 * CRUD API for cities (Master Settings > Location > City).
 * Optionally filter by `?state_id=` to list cities of a state.
 */
class CityController extends CrudController
{
    protected string $model = City::class;

    protected array $rules = [
        'state_id'   => 'required|integer|exists:states,id',
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('state_id') !== null) {
            $query->where('state_id', (int) $request->query('state_id'));
        }
    }
}