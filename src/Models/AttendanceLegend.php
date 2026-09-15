<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * AttendanceLegend model (backed by the attendance_legends table).
 */
class AttendanceLegend extends Model
{
    protected string $table = 'attendance_legends';
    protected string $primaryKey = 'id';

    public const TREAT_AS_PRESENT = 'present';
    public const TREAT_AS_ABSENT  = 'absent';

    public const FOR_STUDENT  = 'student';
    public const FOR_EMPLOYEE = 'employee';
    public const FOR_BOTH     = 'both';

    protected array $fillable = [
        'name',
        'short_name',
        'treat_as',
        'total_leaves',
        'legend_for',
    ];
}