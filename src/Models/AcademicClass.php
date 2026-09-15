<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * AcademicClass model (backed by the academic_classes table).
 */
class AcademicClass extends Model
{
    protected string $table = 'academic_classes';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'short_name',
        'description',
        'sort_order',
        'capacity',
        'firm_id',
    ];
}