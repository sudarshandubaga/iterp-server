<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * fee_heads table.
 *
 * Stores fee heads/categories:
 * - name: Name of fee head (e.g. Tuition Fee, Admission Fee, Examination Fee, Sports Fee, Library Caution Money)
 * - is_admission_fee: Flag indicating if it is an admission fee
 * - is_refundable_fee: Flag indicating if refundable (e.g. Caution Money)
 * - is_once_a_year: Flag indicating fee billed once in an academic year
 * - is_once_a_career: Flag indicating fee billed once in student's entire tenure/career
 * - student_category_ids: JSON array of applicable student category IDs (e.g. [1, 2])
 * - firm_id: Firm/organization reference
 */
class CreateFeeHeadsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_heads (
                id                    BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                firm_id               BIGINT UNSIGNED NULL DEFAULT NULL,
                name                  VARCHAR(255) NOT NULL,
                is_admission_fee      TINYINT(1) NOT NULL DEFAULT 0,
                is_refundable_fee     TINYINT(1) NOT NULL DEFAULT 0,
                is_once_a_year        TINYINT(1) NOT NULL DEFAULT 0,
                is_once_a_career      TINYINT(1) NOT NULL DEFAULT 0,
                student_category_ids  TEXT NULL DEFAULT NULL,
                description           TEXT NULL DEFAULT NULL,
                created_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at            TIMESTAMP NULL DEFAULT NULL,
                
                CONSTRAINT fk_fee_heads_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_fee_heads_firm (firm_id),
                INDEX idx_fee_heads_name (name),
                INDEX idx_fee_heads_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS fee_heads');
    }
}
