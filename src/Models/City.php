<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * City model (backed by the cities table).
 */
class City extends Model
{
    protected string $table = 'cities';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'state_id',
        'name',
        'short_name',
    ];
}