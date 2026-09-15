<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * StudentCategory model (backed by student_categories table).
 */
class StudentCategory extends Model
{
    protected string $table = 'student_categories';
    protected string $primaryKey = 'id';

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected array $fillable = [
        'name',
        'status',
    ];
}
