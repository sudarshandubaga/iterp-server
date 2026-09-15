<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * academic_classes table.
 */
class CreateAcademicClassesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS academic_classes (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                short_name  VARCHAR(50)  NOT NULL,
                description TEXT NULL,
                sort_order  INT NOT NULL DEFAULT 0,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_academic_classes_name (name),
                INDEX idx_academic_classes_sort_order (sort_order)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS academic_classes');
    }
}