<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Exam model (backed by the exams table).
 */
class Exam extends Model
{
    protected string $table = 'exams';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tenant_id',
        'firm_id',
        'academic_year_id',
        'exam_type_id',
        'name',
        'start_date',
        'end_date',
        'status',
        'description',
    ];
}

