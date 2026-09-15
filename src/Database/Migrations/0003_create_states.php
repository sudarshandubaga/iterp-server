<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * states table.
 */
class CreateStatesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS states (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                country_id  BIGINT UNSIGNED NOT NULL,
                name        VARCHAR(255) NOT NULL,
                short_name  VARCHAR(50)  NOT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                CONSTRAINT fk_states_country FOREIGN KEY (country_id) REFERENCES countries (id) ON DELETE CASCADE,
                INDEX idx_states_country (country_id),
                INDEX idx_states_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS states');
    }
}