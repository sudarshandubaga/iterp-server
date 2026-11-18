<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add sort_order column to attendance_legends table for manual drag-and-drop ordering.
 */
class AddSortOrderToAttendanceLegendsTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();

        $cols = $pdo->query("SHOW COLUMNS FROM attendance_legends LIKE 'sort_order'")->fetchAll();
        if (empty($cols)) {
            $this->schema(
                'ALTER TABLE attendance_legends
                    ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER total_leaves,
                    ADD INDEX idx_attendance_legends_sort_order (sort_order)'
            );

            // Populate existing rows with 1-based order (Present legends first, then Absent)
            $rows = $pdo->query(
                "SELECT id FROM attendance_legends ORDER BY FIELD(treat_as, 'present', 'absent'), id ASC"
            )->fetchAll(\PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $upStmt = $pdo->prepare('UPDATE attendance_legends SET sort_order = :ord WHERE id = :id');
                $pos = 1;
                foreach ($rows as $row) {
                    $upStmt->execute([
                        'id'  => (int) $row['id'],
                        'ord' => $pos++,
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        $pdo = $this->connection();
        $cols = $pdo->query("SHOW COLUMNS FROM attendance_legends LIKE 'sort_order'")->fetchAll();
        if (!empty($cols)) {
            $this->schema('ALTER TABLE attendance_legends DROP COLUMN sort_order');
        }
    }
}

