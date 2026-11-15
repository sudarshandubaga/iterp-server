<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Core\QueryBuilder;
use Iterp\Core\Request;
use Iterp\Models\ExamSubject;

/**
 * CRUD API for exam subjects (Examinations > Exam Subjects).
 * Supports filtering by `?type=primary|extra_activity` and `?status=active|inactive`.
 */
class ExamSubjectController extends CrudController
{
    protected string $model = ExamSubject::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:50',
        'code'       => 'nullable|string|max:50',
        'type'       => 'required|string|in:primary,extra_activity',
        'status'     => 'nullable|string|in:active,inactive',
        'sort_order' => 'nullable|integer',
    ];

    protected string $orderBy = 'sort_order';
    protected string $orderDir = 'ASC';

    protected array $searchable = ['name', 'short_name', 'code'];

    protected function applyFilters(Request $request, QueryBuilder $query): void
    {
        $type = $request->query('type');
        if ($type !== null && $type !== '') {
            $query->where('type', (string) $type);
        }

        $status = $request->query('status');
        if ($status !== null && $status !== '') {
            $query->where('status', (string) $status);
        }
    }
}

