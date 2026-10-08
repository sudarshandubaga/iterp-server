<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * fee_collections, fee_collection_items, and fee_collection_slabs tables.
 *
 * Records fee collection/charges for students against fee bill schemes and slabs.
 */
class CreateFeeCollectionsTable extends Migration
{
    public function up(): void
    {
        // 1. fee_collections table
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_collections (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                firm_id             BIGINT UNSIGNED NULL DEFAULT NULL,
                session_id          BIGINT UNSIGNED NULL DEFAULT NULL,
                student_id          BIGINT UNSIGNED NOT NULL,
                receipt_no          VARCHAR(100) NOT NULL UNIQUE,
                payment_date        DATE NOT NULL,
                payment_mode        VARCHAR(50) NOT NULL DEFAULT "Cash",
                reference_no        VARCHAR(100) NULL DEFAULT NULL,
                fee_bill_scheme_id  BIGINT UNSIGNED NULL DEFAULT NULL,
                concession_id       BIGINT UNSIGNED NULL DEFAULT NULL,
                subtotal_amount     DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                concession_amount   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_type       ENUM("percentage", "fixed") NULL DEFAULT NULL,
                discount_value      DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_amount     DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_reason     VARCHAR(255) NULL DEFAULT NULL,
                total_amount        DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                paid_amount         DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                balance_amount      DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                remarks             TEXT NULL DEFAULT NULL,
                created_by          BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at          TIMESTAMP NULL DEFAULT NULL,

                CONSTRAINT fk_fee_col_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_col_session FOREIGN KEY (session_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_col_student FOREIGN KEY (student_id) REFERENCES students (id) ON DELETE CASCADE,
                CONSTRAINT fk_fee_col_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_col_concession FOREIGN KEY (concession_id) REFERENCES fee_concessions (id) ON DELETE SET NULL,
                INDEX idx_fee_col_student (student_id),
                INDEX idx_fee_col_session (session_id),
                INDEX idx_fee_col_payment_date (payment_date),
                INDEX idx_fee_col_receipt_no (receipt_no),
                INDEX idx_fee_col_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 2. fee_collection_items table
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_collection_items (
                id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_collection_id        BIGINT UNSIGNED NOT NULL,
                fee_bill_scheme_slab_id  BIGINT UNSIGNED NULL DEFAULT NULL,
                fee_head_id              BIGINT UNSIGNED NOT NULL,
                amount                   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                concession               DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                total                    DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                paid_amount              DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_fee_col_items_parent FOREIGN KEY (fee_collection_id) REFERENCES fee_collections (id) ON DELETE CASCADE,
                CONSTRAINT fk_fee_col_items_slab FOREIGN KEY (fee_bill_scheme_slab_id) REFERENCES fee_bill_scheme_slabs (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_col_items_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE RESTRICT,
                INDEX idx_fee_col_items_parent (fee_collection_id),
                INDEX idx_fee_col_items_slab (fee_bill_scheme_slab_id),
                INDEX idx_fee_col_items_head (fee_head_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 3. fee_collection_slabs table
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_collection_slabs (
                id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_collection_id        BIGINT UNSIGNED NOT NULL,
                fee_bill_scheme_slab_id  BIGINT UNSIGNED NOT NULL,
                created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,

                CONSTRAINT fk_fee_col_slabs_parent FOREIGN KEY (fee_collection_id) REFERENCES fee_collections (id) ON DELETE CASCADE,
                CONSTRAINT fk_fee_col_slabs_slab FOREIGN KEY (fee_bill_scheme_slab_id) REFERENCES fee_bill_scheme_slabs (id) ON DELETE CASCADE,
                INDEX idx_fee_col_slabs_parent (fee_collection_id),
                INDEX idx_fee_col_slabs_slab (fee_bill_scheme_slab_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // 4. Ensure students table has fee_bill_scheme_id and fee_concession_id columns
        $pdo = $this->connection();
        $cols = $pdo->query("SHOW COLUMNS FROM students LIKE 'fee_bill_scheme_id'")->fetchAll();
        if (empty($cols)) {
            $this->schema('ALTER TABLE students ADD COLUMN fee_bill_scheme_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER academic_year_id,
                ADD CONSTRAINT fk_students_fee_bill_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE SET NULL,
                ADD INDEX idx_students_fee_bill_scheme (fee_bill_scheme_id)');
        }
        $colsConc = $pdo->query("SHOW COLUMNS FROM students LIKE 'fee_concession_id'")->fetchAll();
        if (empty($colsConc)) {
            $this->schema('ALTER TABLE students ADD COLUMN fee_concession_id BIGINT UNSIGNED NULL DEFAULT NULL AFTER fee_bill_scheme_id,
                ADD CONSTRAINT fk_students_fee_concession FOREIGN KEY (fee_concession_id) REFERENCES fee_concessions (id) ON DELETE SET NULL,
                ADD INDEX idx_students_fee_concession (fee_concession_id)');
        }
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS fee_collection_slabs');
        $this->schema('DROP TABLE IF EXISTS fee_collection_items');
        $this->schema('DROP TABLE IF EXISTS fee_collections');
    }
}
