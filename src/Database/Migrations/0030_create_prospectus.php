<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * prospectus table.
 *
 * Stores prospectus sales/issues for admission inquiries:
 * - prospectus_no (unique serial number, e.g. PR-2026-0001)
 * - candidate_name, father_name, mother_name, mobile_no, email
 * - class_id (applying for academic class)
 * - academic_year_id, firm_id
 * - amount (cost of prospectus), payment_mode, reference_no
 * - issue_date, status ('issued', 'registered', 'cancelled')
 */
class CreateProspectusTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS prospectus (
                id               BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                prospectus_no    VARCHAR(100) NOT NULL,
                candidate_name   VARCHAR(255) NOT NULL,
                father_name      VARCHAR(255) NULL DEFAULT NULL,
                mother_name      VARCHAR(255) NULL DEFAULT NULL,
                mobile_no        VARCHAR(50) NULL DEFAULT NULL,
                email            VARCHAR(255) NULL DEFAULT NULL,
                class_id         BIGINT UNSIGNED NULL DEFAULT NULL,
                academic_year_id BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id          BIGINT UNSIGNED NULL DEFAULT NULL,
                amount           DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                payment_mode     VARCHAR(50) NOT NULL DEFAULT "Cash",
                reference_no     VARCHAR(100) NULL DEFAULT NULL,
                issue_date       DATE NOT NULL,
                status           ENUM("issued", "registered", "cancelled") NOT NULL DEFAULT "issued",
                remarks          TEXT NULL DEFAULT NULL,
                created_by       BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at       TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_prospectus_no (prospectus_no),
                CONSTRAINT fk_prospectus_class FOREIGN KEY (class_id) REFERENCES academic_classes (id) ON DELETE SET NULL,
                CONSTRAINT fk_prospectus_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE SET NULL,
                CONSTRAINT fk_prospectus_firm FOREIGN KEY (firm_id) REFERENCES firms (id) ON DELETE SET NULL,
                INDEX idx_prospectus_no (prospectus_no),
                INDEX idx_prospectus_class (class_id),
                INDEX idx_prospectus_academic_year (academic_year_id),
                INDEX idx_prospectus_firm (firm_id),
                INDEX idx_prospectus_issue_date (issue_date),
                INDEX idx_prospectus_status (status),
                INDEX idx_prospectus_deleted_at (deleted_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS prospectus');
    }
}
