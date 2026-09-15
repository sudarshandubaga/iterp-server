<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\Request;
use Iterp\Models\Section;

/**
 * CRUD API for sections (SIS > Sections).
 * Optionally filter by `?class_id=` or `?academic_year_id=`.
 */
class SectionController extends CrudController
{
    protected string $model = Section::class;

    protected array $rules = [
        'name'             => 'required|string|max:255',
        'class_id'         => 'required|integer|exists:academic_classes,id',
        'academic_year_id' => 'required|integer|exists:academic_years,id',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name'];

    protected function applyFilters(Request $request, \Iterp\Core\QueryBuilder $query): void
    {
        if ($request->query('class_id') !== null) {
            $query->where('class_id', (int) $request->query('class_id'));
        }
        if ($request->query('academic_year_id') !== null) {
            $query->where('academic_year_id', (int) $request->query('academic_year_id'));
        }
    }
}