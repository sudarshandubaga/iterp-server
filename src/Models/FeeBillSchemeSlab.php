<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;
use PDO;

/**
 * FeeBillSchemeSlab model (backed by the fee_bill_scheme_slabs table).
 */
class FeeBillSchemeSlab extends Model
{
    protected string $table = 'fee_bill_scheme_slabs';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'fee_bill_scheme_id',
        'slab_no',
        'due_date',
    ];

    /**
     * Load all fee amounts for this slab.
     */
    public function loadAmounts(): array
    {
        $id = $this->id();
        if ($id <= 0) {
            return [];
        }

        $sql = 'SELECT a.*, fh.name AS fee_head_name
                FROM fee_bill_scheme_amounts a
                LEFT JOIN fee_heads fh ON fh.id = a.fee_head_id
                WHERE a.fee_bill_scheme_slab_id = :slab_id
                ORDER BY a.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['slab_id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Response representation of this slab.
     */
    public function toResponseArray(): array
    {
        $data = $this->toArray();
        $amounts = $this->loadAmounts();
        $data['amounts'] = $amounts;

        $total = 0.0;
        foreach ($amounts as $amt) {
            $total += (float) ($amt['amount'] ?? 0);
        }
        $data['total_amount'] = $total;

        return $data;
    }
}
