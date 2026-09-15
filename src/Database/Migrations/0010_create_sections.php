<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * sections table.
 */
class CreateSectionsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS sections (
                id                BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name              VARCHAR(255) NOT NULL,
                class_id          BIGINT UNSIGNED NOT NULL,
                academic_year_id  BIGINT UNSIGNED NOT NULL,
                created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at        TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_sections_class FOREIGN KEY (class_id) REFERENCES academic_classes (id) ON DELETE CASCADE,
                CONSTRAINT fk_sections_academic_year FOREIGN KEY (academic_year_id) REFERENCES academic_years (id) ON DELETE CASCADE,
                INDEX idx_sections_class (class_id),
                INDEX idx_sections_academic_year (academic_year_id),
                INDEX idx_sections_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS sections');
    }
}