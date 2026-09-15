<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Section model (backed by the sections table).
 */
class Section extends Model
{
    protected string $table = 'sections';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'class_id',
        'academic_year_id',
    ];
}