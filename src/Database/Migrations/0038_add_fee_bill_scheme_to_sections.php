<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Add fee_bill_scheme_id to sections table and ensure students table has fee_bill_scheme_id.
 */
class AddFeeBillSchemeToSectionsTable extends Migration
{
    public function up(): void
    {
        $pdo = $this->connection();

        // 1. Add fee_bill_scheme_id to sections table if not present
        $secCols = $pdo->query("SHOW COLUMNS FROM sections LIKE 'fee_bill_scheme_id'")->fetchAll();
        if (empty($secCols)) {
            $this->schema('ALTER TABLE sections ADD COLUMN fee_bill_scheme_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER academic_year_id,
                ADD CONSTRAINT fk_sections_fee_bill_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE SET NULL,
                ADD INDEX idx_sections_fee_bill_scheme (fee_bill_scheme_id)');
        }

        // 2. Ensure students table has fee_bill_scheme_id
        $stuCols = $pdo->query("SHOW COLUMNS FROM students LIKE 'fee_bill_scheme_id'")->fetchAll();
        if (empty($stuCols)) {
            $this->schema('ALTER TABLE students ADD COLUMN fee_bill_scheme_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER academic_year_id,
                ADD CONSTRAINT fk_students_fee_bill_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE SET NULL,
                ADD INDEX idx_students_fee_bill_scheme (fee_bill_scheme_id)');
        }
    }

    public function down(): void
    {
        $pdo = $this->connection();

        $secCols = $pdo->query("SHOW COLUMNS FROM sections LIKE 'fee_bill_scheme_id'")->fetchAll();
        if (!empty($secCols)) {
            // Drop foreign key if exists
            try {
                $this->schema('ALTER TABLE sections DROP FOREIGN KEY fk_sections_fee_bill_scheme');
            } catch (\Throwable $e) {
                // ignore if foreign key name differs
            }
            $this->schema('ALTER TABLE sections DROP COLUMN fee_bill_scheme_id');
        }
    }
}
