<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add sort_order column to exams table for manual drag-and-drop ordering.
 */
class AddSortOrderToExamsTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();

        $cols = $pdo->query("SHOW COLUMNS FROM exams LIKE 'sort_order'")->fetchAll();
        if (empty($cols)) {
            $this->schema('ALTER TABLE exams
                ADD COLUMN sort_order INT UNSIGNED NOT NULL DEFAULT 0 AFTER exam_type_id,
                ADD INDEX idx_exams_sort_order (sort_order)');

            // Populate existing rows with 1-based order by start_date ASC, id ASC
            $exams = $pdo->query('SELECT id FROM exams ORDER BY start_date ASC, id ASC')->fetchAll(\PDO::FETCH_ASSOC);
            if (!empty($exams)) {
                $upStmt = $pdo->prepare('UPDATE exams SET sort_order = :ord WHERE id = :id');
                $pos = 1;
                foreach ($exams as $row) {
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
        $cols = $pdo->query("SHOW COLUMNS FROM exams LIKE 'sort_order'")->fetchAll();
        if (!empty($cols)) {
            $this->schema('ALTER TABLE exams DROP COLUMN sort_order');
        }
    }
}

