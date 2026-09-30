<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * FeeConcessionItem model (backed by the fee_concession_items table).
 */
class FeeConcessionItem extends Model
{
    protected string $table = 'fee_concession_items';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'concession_id',
        'fee_head_id',
        'amount_type',
        'amount_value',
    ];
}
