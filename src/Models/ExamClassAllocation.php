<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * ExamClassAllocation model (backed by the exam_class_allocations table).
 */
class ExamClassAllocation extends Model
{
    protected string $table = 'exam_class_allocations';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'exam_id',
        'academic_class_id',
        'section_id',
    ];
}

