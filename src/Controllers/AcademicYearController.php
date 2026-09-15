<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\AcademicYear;

/**
 * CRUD API for academic years (Annual Settings > Academic Year).
 */
class AcademicYearController extends CrudController
{
    protected string $model = AcademicYear::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'start_date' => 'required|date',
        'end_date'   => 'required|date',
    ];

    protected string $orderBy = 'start_date';
    protected string $orderDir = 'DESC';

    protected array $searchable = ['name'];
}