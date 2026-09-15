<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * State model (backed by the states table).
 */
class State extends Model
{
    protected string $table = 'states';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'country_id',
        'name',
        'short_name',
    ];
}