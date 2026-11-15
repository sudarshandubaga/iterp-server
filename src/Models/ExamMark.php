<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * ExamMark model (backed by the exam_marks table).
 */
class ExamMark extends Model
{
    protected string $table = 'exam_marks';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'exam_id',
        'academic_class_id',
        'section_id',
        'subject_id',
        'student_id',
        'min_marks',
        'max_marks',
        'obtained_marks',
        'attendance',
        'remarks',
        'created_by',
    ];
}

