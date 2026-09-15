<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * AcademicYear model (backed by the academic_years table).
 */
class AcademicYear extends Model
{
    protected string $table = 'academic_years';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'start_date',
        'end_date',
        'firm_id',
    ];
}