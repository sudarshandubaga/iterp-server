<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\CustomFieldCategory;

/**
 * CRUD API for custom field categories (Annual Settings > Custom Field Categories).
 */
class CustomFieldCategoryController extends CrudController
{
    protected string $model = CustomFieldCategory::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
        'type'       => 'required|in:employee,student',
        'sort_order' => 'nullable|integer',
    ];

    protected string $orderBy = 'sort_order';

    protected array $searchable = ['name', 'short_name', 'type'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('type') !== null) {
            $query->where('type', (string) $request->query('type'));
        }
    }
}