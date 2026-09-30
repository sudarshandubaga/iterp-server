<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * FeeBillScheme model (backed by the fee_bill_schemes table).
 */
class FeeBillScheme extends Model
{
    protected string $table = 'fee_bill_schemes';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'firm_id',
        'session_id',
        'name',
        'slab',
        'description',
    ];

    /**
     * Load all slab amounts for this scheme.
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
                WHERE a.fee_bill_scheme_id = :scheme_id
                ORDER BY a.slab_no ASC, a.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['scheme_id' => $id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Hydrate transformed array with amounts, session name, and firm name.
     */
    public function toResponseArray(): array
    {
        $data = $this->toArray();
        $amounts = $this->loadAmounts();
        $data['amounts'] = $amounts;
        $data['amounts_count'] = count($amounts);

        $total = 0.0;
        foreach ($amounts as $amt) {
            $total += (float) ($amt['amount'] ?? 0);
        }
        $data['total_amount'] = $total;

        if (!empty($data['session_id'])) {
            $ay = AcademicYear::find((int) $data['session_id']);
            $data['session_name'] = $ay ? $ay->name : null;
        } else {
            $data['session_name'] = null;
        }

        if (!empty($data['firm_id'])) {
            $firm = Firm::find((int) $data['firm_id']);
            $data['firm_name'] = $firm ? $firm->name : null;
        } else {
            $data['firm_name'] = null;
        }

        return $data;
    }
}
