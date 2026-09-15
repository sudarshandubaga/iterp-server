<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Role model (backed by the roles table).
 */
class Role extends Model
{
    protected string $table = 'roles';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'name',
        'for',
        'role_for',
        'parent_id',
        'firm_id',
    ];

    public function fill(array $attributes): self
    {
        if (isset($attributes['for']) && !isset($attributes['role_for'])) {
            $attributes['role_for'] = $attributes['for'];
        } elseif (isset($attributes['role_for']) && !isset($attributes['for'])) {
            $attributes['for'] = $attributes['role_for'];
        }
        return parent::fill($attributes);
    }
}