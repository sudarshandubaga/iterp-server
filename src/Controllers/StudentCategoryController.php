<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\StudentCategory;

/**
 * CRUD API for student categories.
 */
class StudentCategoryController extends CrudController
{
    protected string $model = StudentCategory::class;

    protected array $rules = [
        'name'   => 'required|string|max:255',
        'status' => 'required|in:active,inactive',
    ];

    protected string $orderBy = 'id';

    protected array $searchable = ['name', 'status'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        $status = $request->query('status');
        if ($status === 'active' || $status === 'inactive') {
            $query->where('status', $status);
        }
    }
}
