<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Database;
use Iterp\Core\Model;

/**
 * FeeHead model (backed by the fee_heads table).
 */
class FeeHead extends Model
{
    protected string $table = 'fee_heads';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'firm_id',
        'name',
        'is_admission_fee',
        'is_refundable_fee',
        'is_once_a_year',
        'is_once_a_career',
        'student_category_ids',
        'description',
    ];

    /**
     * Decode student_category_ids to an integer array.
     */
    public function getCategoryIds(): array
    {
        $raw = $this->student_category_ids;
        if (empty($raw)) {
            return [];
        }
        if (is_array($raw)) {
            return array_map('intval', $raw);
        }
        $decoded = json_decode((string) $raw, true);
        if (is_array($decoded)) {
            return array_map('intval', $decoded);
        }
        // Fallback for comma separated string
        return array_filter(array_map('intval', explode(',', (string) $raw)));
    }

    /**
     * Fetch related student categories.
     */
    public function getStudentCategories(): array
    {
        $ids = $this->getCategoryIds();
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = Database::pdo()->prepare(
            "SELECT id, name FROM student_categories WHERE id IN ({$placeholders}) ORDER BY name ASC"
        );
        $stmt->execute($ids);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Hydrate transformed array with categories decoded.
     */
    public function toResponseArray(): array
    {
        $data = $this->toArray();
        $data['is_admission_fee'] = (int) ($data['is_admission_fee'] ?? 0);
        $data['is_refundable_fee'] = (int) ($data['is_refundable_fee'] ?? 0);
        $data['is_once_a_year'] = (int) ($data['is_once_a_year'] ?? 0);
        $data['is_once_a_career'] = (int) ($data['is_once_a_career'] ?? 0);
        $data['student_category_ids'] = $this->getCategoryIds();
        $data['student_categories'] = $this->getStudentCategories();

        if (!empty($data['firm_id'])) {
            $firm = Firm::find((int) $data['firm_id']);
            $data['firm_name'] = $firm ? $firm->name : null;
        } else {
            $data['firm_name'] = null;
        }

        return $data;
    }
}
