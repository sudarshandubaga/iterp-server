<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Locality model (backed by the localities table).
 */
class Locality extends Model
{
    protected string $table = 'localities';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'short_name',
        'city_id',
        'tenant_id',
    ];
}