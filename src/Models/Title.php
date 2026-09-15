<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * Title model (backed by the titles table).
 */
class Title extends Model
{
    protected string $table = 'titles';
    protected string $primaryKey = 'id';

    public const GENDER_MALE   = 'male';
    public const GENDER_FEMALE = 'female';
    public const GENDER_BOTH   = 'both';

    public const TYPE_STUDENT  = 'student';
    public const TYPE_EMPLOYEE = 'employee';
    public const TYPE_BOTH     = 'both';

    protected array $fillable = [
        'name',
        'short_name',
        'gender',
        'user_type',
    ];
}