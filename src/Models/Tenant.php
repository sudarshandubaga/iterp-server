<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Tenant model (backed by the tenants table).
 */
class Tenant extends Model
{
    protected string $table = 'tenants';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'logo',
        'favicon',
        'domain',
        'email',
        'phone_no',
    ];
}