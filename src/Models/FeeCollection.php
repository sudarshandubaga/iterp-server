<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;
use PDO;

/**
 * FeeCollection model (backed by the fee_collections table).
 */
class FeeCollection extends Model
{
    protected string $table = 'fee_collections';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'firm_id',
        'session_id',
        'student_id',
        'receipt_no',
        'payment_date',
        'payment_mode',
        'reference_no',
        'fee_bill_scheme_id',
        'concession_id',
        'subtotal_amount',
        'concession_amount',
        'discount_type',
        'discount_value',
        'discount_amount',
        'discount_reason',
        'total_amount',
        'paid_amount',
        'balance_amount',
        'remarks',
        'created_by',
    ];

    /**
     * Generate next sequential receipt number (e.g. REC-2026-0001).
     */
    public static function generateNextNumber(?int $academicYearId = null): string
    {
        $yearPrefix = date('Y');
        if ($academicYearId) {
            $yearRecord = AcademicYear::find($academicYearId);
            if ($yearRecord && !empty($yearRecord->name)) {
                $parts = explode('-', (string) $yearRecord->name);
                $yearPrefix = $parts[0] ?: date('Y');
            }
        }

        $prefix = "REC-{$yearPrefix}-";
        $sql = "SELECT receipt_no FROM fee_collections 
                WHERE receipt_no LIKE :prefix 
                ORDER BY id DESC LIMIT 1";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['prefix' => "{$prefix}%"]);
        $last = $stmt->fetchColumn();

        if ($last) {
            $lastNum = (int) str_replace($prefix, '', (string) $last);
            $nextNum = str_pad((string) ($lastNum + 1), 4, '0', STR_PAD_LEFT);
        } else {
            $nextNum = '0001';
        }

        return $prefix . $nextNum;
    }

    /**
     * Load fee collection items with fee head names.
     */
    public function loadItems(): array
    {
        $id = $this->id();
        if ($id <= 0) {
            return [];
        }

        $sql = 'SELECT fci.*, fh.name AS fee_head_name
                FROM fee_collection_items fci
                LEFT JOIN fee_heads fh ON fh.id = fci.fee_head_id
                WHERE fci.fee_collection_id = :collection_id
                ORDER BY fci.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['collection_id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Load slabs settled in this collection.
     */
    public function loadSlabs(): array
    {
        $id = $this->id();
        if ($id <= 0) {
            return [];
        }

        $sql = 'SELECT fcs.*, s.slab_no, s.due_date
                FROM fee_collection_slabs fcs
                LEFT JOIN fee_bill_scheme_slabs s ON s.id = fcs.fee_bill_scheme_slab_id
                WHERE fcs.fee_collection_id = :collection_id
                ORDER BY s.slab_no ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['collection_id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
