<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\Group;

/**
 * CRUD API for groups (SIS > Group).
 * Optionally filter by `?section_id=`.
 */
class GroupController extends CrudController
{
    protected string $model = Group::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'section_id' => 'required|integer|exists:sections,id',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('section_id') !== null) {
            $query->where('section_id', (int) $request->query('section_id'));
        }
    }
}