<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Event model (backed by the events table). Used for the school calendar.
 */
class Event extends Model
{
    protected string $table = 'events';
    protected string $primaryKey = 'id';

    public const TYPE_EVENT      = 'event';
    public const TYPE_ASSESSMENT = 'assessment';
    public const TYPE_HOLIDAY    = 'holiday';
    public const TYPE_SPORT      = 'sport';

    public const FOR_EMPLOYEE = 'employee';
    public const FOR_STUDENT  = 'student';
    public const FOR_BOTH     = 'both';

    protected array $fillable = [
        'type',
        'name',
        'start_date',
        'end_date',
        'description',
        'mark_attendance',
        'event_for',
        'firm_id',
    ];
}