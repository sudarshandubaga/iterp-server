<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * fee_concessions and fee_concession_items tables.
 *
 * Manages 2 tables:
 * 1. fee_concessions:
 *    - session_id: Academic session (FK to academic_years)
 *    - firm_id: Firm/organization (FK to firms)
 *    - name: Concession name (e.g. Sibling Discount, Staff Ward Concession, Merit Scholarship)
 * 2. fee_concession_items (separate table):
 *    - concession_id: Parent concession reference
 *    - fee_head_id: Fee head reference (FK to fee_heads)
 *    - amount_type: 'Percentage' or 'Value'
 *    - amount_value: The percentage rate (e.g. 25.00) or flat value (e.g. 5000.00)
 */
class CreateFeeConcessionsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_concessions (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                firm_id      BIGINT UNSIGNED NULL DEFAULT NULL,
                session_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                name         VARCHAR(255) NOT NULL,
                description  TEXT NULL DEFAULT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at   TIMESTAMP NULL DEFAULT NULL,
                
                CONSTRAINT fk_fee_concessions_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                CONSTRAINT fk_fee_concessions_session FOREIGN KEY (session_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                INDEX idx_fee_concessions_firm (firm_id),
                INDEX idx_fee_concessions_session (session_id),
                INDEX idx_fee_concessions_name (name),
                INDEX idx_fee_concessions_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS fee_concession_items (
                id             BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                concession_id  BIGINT UNSIGNED NOT NULL,
                fee_head_id    BIGINT UNSIGNED NOT NULL,
                amount_type    ENUM("Percentage", "Value") NOT NULL DEFAULT "Percentage",
                amount_value   DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                
                CONSTRAINT fk_fee_concession_items_parent FOREIGN KEY (concession_id) REFERENCES fee_concessions (id) ON DELETE CASCADE,
                CONSTRAINT fk_fee_concession_items_head FOREIGN KEY (fee_head_id) REFERENCES fee_heads (id) ON DELETE CASCADE,
                INDEX idx_fee_concession_items_parent (concession_id),
                INDEX idx_fee_concession_items_head (fee_head_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS fee_concession_items');
        $this->schema('DROP TABLE IF EXISTS fee_concessions');
    }
}
