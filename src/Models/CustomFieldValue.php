<?php

declare(strict_types=1);

namespace Iterp\Models;

use Iterp\Core\Model;
use Iterp\Core\Database;

/**
 * CustomFieldValue model (backed by custom_field_values table).
 */
class CustomFieldValue extends Model
{
    protected string $table = 'custom_field_values';
    protected string $primaryKey = 'id';

    protected array $fillable = [
        'user_id',
        'custom_field_id',
        'value',
    ];

    /**
     * Set or update a custom field value by custom_field_id.
     */
    public static function setValue(int $userId, int $customFieldId, ?string $value): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO custom_field_values (user_id, custom_field_id, value, created_at, updated_at)
             VALUES (:uid, :cfid, :val, NOW(), NOW())
             ON DUPLICATE KEY UPDATE value = VALUES(value), updated_at = NOW()'
        );
        $stmt->execute([
            'uid'  => $userId,
            'cfid' => $customFieldId,
            'val'  => $value,
        ]);
    }

    /**
     * Set or update a custom field value by field name (case-insensitive search).
     */
    public static function setValueByName(int $userId, string $fieldName, ?string $value): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT id FROM custom_fields WHERE LOWER(TRIM(name)) = LOWER(TRIM(:name)) AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute(['name' => $fieldName]);
        $cfId = $stmt->fetchColumn();

        if ($cfId) {
            self::setValue($userId, (int) $cfId, $value);
            return true;
        }

        return false;
    }

    /**
     * Get all custom field values for a user as an associative map:
     * ['cf_1' => 'value', 'cf_2' => 'value', ...] and also by name.
     */
    public static function getForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT cfv.custom_field_id, cfv.value, cf.name
             FROM custom_field_values cfv
             INNER JOIN custom_fields cf ON cf.id = cfv.custom_field_id
             WHERE cfv.user_id = :uid AND cf.deleted_at IS NULL'
        );
        $stmt->execute(['uid' => $userId]);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $results = [
            'answers'   => [], // keyed by cf_{id}
            'by_name'   => [], // keyed by field name
        ];

        foreach ($rows as $row) {
            $results['answers']['cf_' . $row['custom_field_id']] = $row['value'];
            $results['by_name'][$row['name']] = $row['value'];
        }

        return $results;
    }
}
