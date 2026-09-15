<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\State;

/**
 * CRUD API for states (Master Settings > Location > State).
 * Optionally filter by `?country_id=` to list states of a country.
 */
class StateController extends CrudController
{
    protected string $model = State::class;

    protected array $rules = [
        'country_id' => 'required|integer|exists:countries,id',
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('country_id') !== null) {
            $query->where('country_id', (int) $request->query('country_id'));
        }
    }
}