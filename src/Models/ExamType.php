<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * ExamType model (backed by the exam_types table).
 */
class ExamType extends Model
{
    protected string $table = 'exam_types';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'tenant_id',
        'firm_id',
        'name',
        'short_name',
        'status',
        'description',
    ];
}

