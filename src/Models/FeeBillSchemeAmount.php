<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;

/**
 * FeeBillSchemeAmount model (backed by the fee_bill_scheme_amounts table).
 */
class FeeBillSchemeAmount extends Model
{
    protected string $table = 'fee_bill_scheme_amounts';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'fee_bill_scheme_slab_id',
        'fee_head_id',
        'amount',
    ];
}
