<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * CustomFieldCategory model (backed by the custom_field_categories table).
 */
class CustomFieldCategory extends Model
{
    protected string $table = 'custom_field_categories';
    protected string $primaryKey = 'id';

    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_STUDENT  = 'student';

    protected array $fillable = [
        'name',
        'short_name',
        'type',
        'sort_order',
    ];
}