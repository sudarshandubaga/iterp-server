<?php

declare(strict_types=1);

namespace Iterp\Controllers;

use Iterp\Models\Event;

/**
 * CRUD API for the school calendar (Annual Settings > School Calendar).
 * Backed by the `events` migration.
 */
class EventController extends CrudController
{
    protected string $model = Event::class;

    protected array $rules = [
        'type'            => 'required|in:event,assessment,holiday,sport',
        'name'            => 'required|string|max:255',
        'start_date'      => 'required|date',
        'end_date'        => 'required|date',
        'description'     => 'nullable|string',
        'mark_attendance' => 'required|in:yes,no',
        'event_for'       => 'required|in:employee,student,both',
    ];

    protected string $orderBy = 'start_date';

    protected array $searchable = ['name', 'type'];
}