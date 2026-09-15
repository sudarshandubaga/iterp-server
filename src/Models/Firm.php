<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Firm model (backed by the firms table). Represents an Organisation.
 */
class Firm extends Model
{
    protected string $table = 'firms';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'city_id',
        'name',
        'short_name',
        'code',
        'registration_no',
        'address',
        'email',
        'phone_no',
        'fax',
        'logo',
    ];
}