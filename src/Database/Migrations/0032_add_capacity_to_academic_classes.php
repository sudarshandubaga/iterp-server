<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Database;
use Iterp\Core\Migration;

/**
 * Add capacity column to academic_classes table for tracking class seat capacity in admission strength reports.
 */
class AddCapacityToAcademicClassesTable extends Migration
{
    public function up(): void
    {
        $pdo = Database::pdo();
        $cols = $pdo->query('DESCRIBE academic_classes')->fetchAll(\PDO::FETCH_COLUMN);

        if (!in_array('capacity', $cols, true)) {
            $this->schema(
                'ALTER TABLE academic_classes
                 ADD COLUMN capacity INT UNSIGNED NOT NULL DEFAULT 40 AFTER sort_order'
            );
        }
    }

    public function down(): void
    {
        $pdo = Database::pdo();
        $cols = $pdo->query('DESCRIBE academic_classes')->fetchAll(\PDO::FETCH_COLUMN);

        if (in_array('capacity', $cols, true)) {
            $this->schema('ALTER TABLE academic_classes DROP COLUMN capacity');
        }
    }
}
