<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * ExamSchedule model (backed by the exam_schedules table).
 */
class ExamSchedule extends Model
{
    protected string $table = 'exam_schedules';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'exam_id',
        'academic_class_id',
        'section_id',
        'subject_id',
        'exam_date',
        'start_time',
        'end_time',
        'room_no',
        'min_marks',
        'max_marks',
    ];
}

