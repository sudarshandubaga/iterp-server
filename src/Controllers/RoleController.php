<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\Role;

/**
 * CRUD and lookup API for roles.
 */
class RoleController extends CrudController
{
    protected string $model = Role::class;

    protected array $rules = [
        'name'      => 'required|string|max:255',
        'for'       => 'nullable|string|max:50',
        'role_for'  => 'nullable|string|max:50',
        'parent_id' => 'nullable|integer|exists:roles,id',
        'firm_id'   => 'nullable|integer|exists:firms,id',
    ];

    protected string $orderBy = 'id';

    protected array $searchable = ['name'];

    protected function applyFilters(\Iterp\Core\Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        $for = $request->query('for') ?? $request->query('role_for');
        if ($for !== null && $for !== '') {
            $query->where('role_for', (string) $for);
        }
    }
}
