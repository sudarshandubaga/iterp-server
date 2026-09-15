<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\Firm;

/**
 * CRUD API for organisations / firms (Master Settings > Organizations).
 */
class FirmController extends CrudController
{
    protected string $model = Firm::class;

    protected array $rules = [
        'city_id'         => 'required|integer|exists:cities,id',
        'name'            => 'required|string|max:255',
        'short_name'      => 'required|string|max:50',
        'code'            => 'required|string|max:50',
        'registration_no' => 'nullable|string|max:100',
        'address'         => 'nullable|string',
        'email'           => 'nullable|email|max:255',
        'phone_no'        => 'nullable|string|max:50',
        'fax'             => 'nullable|string|max:50',
        'logo'            => 'nullable|string|max:255',
    ];

    protected string $orderBy = 'name';

    protected array $searchable = ['name', 'short_name', 'code'];
}