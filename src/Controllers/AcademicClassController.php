<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\AcademicClass;

/**
 * CRUD API for academic classes (SIS > Class).
 */
class AcademicClassController extends CrudController
{
    protected string $model = AcademicClass::class;

    protected array $rules = [
        'name'        => 'required|string|max:255',
        'short_name'  => 'required|string|max:50',
        'description' => 'nullable|string',
        'sort_order'  => 'nullable|integer',
    ];

    protected string $orderBy = 'sort_order';

    protected array $searchable = ['name', 'short_name'];
}