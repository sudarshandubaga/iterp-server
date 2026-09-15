<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * cities table.
 */
class CreateCitiesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS cities (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                state_id    BIGINT UNSIGNED NOT NULL,
                name        VARCHAR(255) NOT NULL,
                short_name  VARCHAR(50)  NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_cities_state FOREIGN KEY (state_id) REFERENCES states (id) ON DELETE CASCADE,
                INDEX idx_cities_state (state_id),
                INDEX idx_cities_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS cities');
    }
}