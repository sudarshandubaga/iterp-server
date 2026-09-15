<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\Country;

/**
 * CRUD API for countries (Master Settings > Location > Country).
 */
class CountryController extends CrudController
{
    protected string $model = Country::class;

    protected array $rules = [
        'name'       => 'required|string|max:255',
        'short_name' => 'required|string|max:10',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name'];
}