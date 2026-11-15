<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\ExamType;

/**
 * CRUD API for exam types (Examinations > Exam Type).
 */
class ExamTypeController extends CrudController
{
    protected string $model = ExamType::class;

    protected array $rules = [
        'name'        => 'required|string|max:255',
        'short_name'  => 'required|string|max:50',
        'status'      => 'nullable|string|in:active,inactive',
        'description' => 'nullable|string',
    ];

    protected string $orderBy = 'id';
    protected string $orderDir = 'ASC';

    protected array $searchable = ['name', 'short_name'];
}

