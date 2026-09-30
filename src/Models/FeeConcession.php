<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * FeeConcession model (backed by the fee_concessions table).
 */
class FeeConcession extends Model
{
    protected string $table = 'fee_concessions';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'firm_id',
        'session_id',
        'name',
        'description',
    ];

    /**
     * Load all fee head concession items for this concession rule.
     */
    public function loadItems(): array
    {
        $id = $this->id();
        if ($id <= 0) {
            return [];
        }

        $sql = 'SELECT ci.*, fh.name AS fee_head_name
                FROM fee_concession_items ci
                LEFT JOIN fee_heads fh ON fh.id = ci.fee_head_id
                WHERE ci.concession_id = :concession_id
                ORDER BY ci.id ASC';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute(['concession_id' => $id]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Hydrate transformed array with items, session name, and firm name.
     */
    public function toResponseArray(): array
    {
        $data = $this->toArray();
        $items = $this->loadItems();
        $data['items'] = $items;
        $data['items_count'] = count($items);

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
