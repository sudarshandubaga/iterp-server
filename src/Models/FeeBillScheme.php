<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;
use PDO;

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
     * Load all slabs with their respective fee head amounts.
     */
    public function loadSlabs(): array
    {
        $id = $this->id();
        if ($id <= 0) {
            return [];
        }

        $pdo = Database::pdo();

        // 1. Fetch slabs ordered by slab_no
        $slabSql = 'SELECT * FROM fee_bill_scheme_slabs 
                    WHERE fee_bill_scheme_id = :scheme_id 
                    ORDER BY slab_no ASC, id ASC';
        $stmt = $pdo->prepare($slabSql);
        $stmt->execute(['scheme_id' => $id]);
        $slabs = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if ($slabs === []) {
            return [];
        }

        $slabIds = array_column($slabs, 'id');
        $placeholders = implode(',', array_fill(0, count($slabIds), '?'));

        // 2. Fetch amounts for all these slabs in a single query
        $amtSql = "SELECT a.*, fh.name AS fee_head_name
                   FROM fee_bill_scheme_amounts a
                   LEFT JOIN fee_heads fh ON fh.id = a.fee_head_id
                   WHERE a.fee_bill_scheme_slab_id IN ({$placeholders})
                   ORDER BY a.id ASC";
        $amtStmt = $pdo->prepare($amtSql);
        $amtStmt->execute($slabIds);
        $allAmounts = $amtStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        // Group amounts by fee_bill_scheme_slab_id
        $amountsBySlab = [];
        foreach ($allAmounts as $amt) {
            $sId = (int) $amt['fee_bill_scheme_slab_id'];
            $amountsBySlab[$sId][] = $amt;
        }

        // Hydrate slabs
        foreach ($slabs as &$slab) {
            $sId = (int) $slab['id'];
            $slab['slab_no'] = (int) $slab['slab_no'];
            $slabAmounts = $amountsBySlab[$sId] ?? [];
            $slab['amounts'] = $slabAmounts;

            $slabTotal = 0.0;
            foreach ($slabAmounts as $sa) {
                $slabTotal += (float) ($sa['amount'] ?? 0);
            }
            $slab['total_amount'] = $slabTotal;
        }
        unset($slab);

        return $slabs;
    }

    /**
     * Hydrate transformed array with slabs, amounts, session name, and firm name.
     */
    public function toResponseArray(): array
    {
        $data = $this->toArray();
        $data['slab'] = (int) ($data['slab'] ?? 1);

        $slabs = $this->loadSlabs();
        $data['slabs'] = $slabs;
        $data['slabs_count'] = count($slabs);

        // Calculate total amount across all slabs
        $grandTotal = 0.0;
        $flatAmounts = [];
        foreach ($slabs as $slab) {
            $grandTotal += (float) ($slab['total_amount'] ?? 0);
            foreach ($slab['amounts'] as $sa) {
                $flat = $sa;
                $flat['slab_no'] = $slab['slab_no'];
                $flat['due_date'] = $slab['due_date'] ?? null;
                $flatAmounts[] = $flat;
            }
        }

        $data['total_amount'] = $grandTotal;
        $data['amounts'] = $flatAmounts;
        $data['amounts_count'] = count($flatAmounts);

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
