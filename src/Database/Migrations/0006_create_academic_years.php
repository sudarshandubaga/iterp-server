<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * academic_years table.
 */
class CreateAcademicYearsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS academic_years (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                start_date  DATE NOT NULL,
                end_date    DATE NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_academic_years_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS academic_years');
    }
}