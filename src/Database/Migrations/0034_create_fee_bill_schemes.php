<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * fee_bill_schemes, fee_bill_scheme_slabs, and fee_bill_scheme_amounts tables.
 *
 * 1. fee_bill_schemes:
 *    - firm_id
 *    - session_id
 *    - name
 *    - slab (numeric: count of slabs)
 *    - description
 *
 * 2. fee_bill_scheme_slabs:
 *    - fee_bill_scheme_id
 *    - slab_no
 *    - due_date
 *
 * 3. fee_bill_scheme_amounts:
 *    - fee_bill_scheme_slab_id
 *    - fee_head_id
 *    - amount
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
                slab         INT NOT NULL DEFAULT 1,
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
            'CREATE TABLE IF NOT EXISTS fee_bill_scheme_slabs (
                id                  BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_bill_scheme_id  BIGINT UNSIGNED NOT NULL,
                slab_no             INT NOT NULL DEFAULT 1,
                due_date            DATE NULL DEFAULT NULL,
                created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

                CONSTRAINT fk_fbs_slabs_scheme FOREIGN KEY (fee_bill_scheme_id) REFERENCES fee_bill_schemes (id) ON DELETE CASCADE,
                INDEX idx_fbs_slabs_scheme (fee_bill_scheme_id),
                INDEX idx_fbs_slabs_no (slab_no)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_bill_scheme_amounts (
                id                       BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                fee_bill_scheme_slab_id  BIGINT UNSIGNED NOT NULL,
                fee_head_id              BIGINT UNSIGNED NULL DEFAULT NULL,
                amount                   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at               TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                CONSTRAINT fk_fbs_amt_slab FOREIGN KEY (fee_bill_scheme_slab_id) REFERENCES fee_bill_scheme_slabs (id) ON DELETE CASCADE,
                CONSTRAINT fk_fbs_amt_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE SET NULL,
                INDEX idx_fbs_amt_slab (fee_bill_scheme_slab_id),
                INDEX idx_fbs_amt_head (fee_head_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_amounts');
        $this->schema('DROP TABLE IF EXISTS fee_bill_scheme_slabs');
        $this->schema('DROP TABLE IF EXISTS fee_bill_schemes');
    }
}
