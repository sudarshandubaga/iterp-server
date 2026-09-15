<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Country model (backed by the countries table).
 */
class Country extends Model
{
    protected string $table = 'countries';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'short_name',
    ];
}