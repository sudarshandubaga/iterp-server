<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * fee_bill_schemes and fee_bill_scheme_amounts tables.
 *
 * Manages 2 tables:
 * 1. fee_bill_schemes:
 *    - name: Scheme title (e.g. Regular Installments 2026-27)
 *    - slab: Slab frequency or count (e.g. Quarterly (4 Slabs), Monthly (12 Slabs), 4 Slabs)
 *    - session_id: Academic session (FK to academic_years)
 *    - firm_id: Firm/organization (FK to firms)
 * 2. fee_bill_scheme_amounts (separate table):
 *    - fee_bill_scheme_id: Parent scheme reference
 *    - slab_no: Sequential slab number (1, 2, 3...)
 *    - slab_name: Label (e.g. Slab 1 / April, Quarter 1, Installment 1)
 *    - fee_head_id: Optional fee head breakdown
 *    - amount: Decimal amount for this slab
 *    - due_date: Due date for this installment
 */
class CreateFeeBillSchemesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_bill_schemes (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                firm_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                session_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                name         VARCHAR(255) NOT NULL,
                slab         VARCHAR(100) NOT NULL,
                description  TEXT NULL DEFAULT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL DEFAULT NULL,
                
                CONSTRAINT fk_fee_bill_schemes_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_bill_schemes_session FOREIGN KEY (session_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                INDEX idx_fee_bill_schemes_firm (firm_id),
                INDEX idx_fee_bill_schemes_session (session_id),
                INDEX idx_fee_bill_schemes_name (name),
                INDEX idx_fee_bill_schemes_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_bill_scheme_amounts (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_bill_scheme_id  BIGINT UNSIGNED NOT NULL,
                slab_no             INT NOT NULL DEFAULT 1,
                slab_name           VARCHAR(150) NOT NULL,
                fee_head_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                amount              DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                due_date            DATE NULL DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                CONSTRAINT fk_fbs_amounts_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE CASCADE,
                CONSTRAINT fk_fbs_amounts_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE SET NULL,
                INDEX idx_fbs_amounts_scheme (fee_bill_scheme_id),
                INDEX idx_fbs_amounts_head (fee_head_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_amounts');
        $this->schema('DROP TABLE IF EXISTS fee_bill_schemes');
    }
}
