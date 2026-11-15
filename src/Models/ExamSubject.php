<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * ExamSubject model (backed by the exam_subjects table).
 */
class ExamSubject extends Model
{
    protected string $table = 'exam_subjects';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tenant_id',
        'firm_id',
        'name',
        'short_name',
        'code',
        'type',
        'status',
        'sort_order',
    ];
}

