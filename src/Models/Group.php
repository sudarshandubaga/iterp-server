<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Group model (backed by the groups table).
 */
class Group extends Model
{
    protected string $table = 'groups';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'section_id',
    ];
}