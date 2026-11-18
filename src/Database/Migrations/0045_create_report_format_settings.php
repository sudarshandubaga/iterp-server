<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Report format settings table:
 * Stores the active admin-selected template/format per report type
 * (marksheet, student_id_card, teacher_id_card, admit_card, fee_receipt, certificate)
 * optionally scoped per firm_id (0 = global default).
 */
class CreateReportFormatSettingsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS report_format_settings (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id    BIGINT UNSIGNED NULL DEFAULT NULL,
                firm_id      BIGINT UNSIGNED NOT NULL DEFAULT 0,
                report_key   VARCHAR(100) NOT NULL,
                format_key   VARCHAR(100) NOT NULL,
                options_json TEXT NULL DEFAULT NULL,
                updated_by   BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_report_format_firm_key (firm_id, report_key),
                INDEX idx_report_format_tenant (tenant_id),
                INDEX idx_report_format_firm (firm_id),
                INDEX idx_report_format_key (report_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS report_format_settings');
    }
}

