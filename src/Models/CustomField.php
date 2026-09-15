<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * CustomField model (backed by the custom_fields table).
 */
class CustomField extends Model
{
    protected string $table = 'custom_fields';
    protected string $primaryKey = 'id';

    public const FIELD_TYPE_TEXTBOX   = 'textbox';
    public const FIELD_TYPE_RADIO     = 'radio';
    public const FIELD_TYPE_CHECKBOX  = 'checkbox';
    public const FIELD_TYPE_PULLDOWN  = 'pulldown';
    public const FIELD_TYPE_TEXTAREA  = 'textarea';
    public const FIELD_TYPE_DATE      = 'date';

    public const DATA_TYPE_NUMERIC                = 'numeric';
    public const DATA_TYPE_ALPHA_NUMERIC          = 'alpha_numeric';
    public const DATA_TYPE_ALPHABATIC             = 'alphabatic';
    public const DATA_TYPE_ALPHABATIC_SPECIAL     = 'alphabatic_special';
    public const DATA_TYPE_ALPHA_NUMERIC_SPECIAL  = 'alpha_numeric_special';
    public const DATA_TYPE_NUMERIC_SPECIAL        = 'numeric_special';

    public const YES = 'yes';
    public const NO  = 'no';

    protected array $fillable = [
        'custom_field_category_id',
        'name',
        'field_type',
        'data_type',
        'options',
        'mandatory',
        'show',
        'default_value',
        'validation_message',
        'max_length',
        'sort_order',
    ];
}