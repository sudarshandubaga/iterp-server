<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\AttendanceLegend;

/**
 * CRUD API for attendance legends (Annual Settings > Attendance Legends).
 */
class AttendanceLegendController extends CrudController
{
    protected string $model = AttendanceLegend::class;

    protected array $rules = [
        'name'         => 'required|string|max:255',
        'short_name'   => 'required|string|max:50',
        'treat_as'     => 'required|in:present,absent',
        'total_leaves' => 'nullable|integer',
        'legend_for'   => 'nullable|in:student,employee,both',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];
}