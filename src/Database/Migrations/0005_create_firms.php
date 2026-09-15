<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * firms table.
 */
class CreateFirmsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS firms (
                id              BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                city_id         BIGINT UNSIGNED NOT NULL,
                name            VARCHAR(255) NOT NULL,
                short_name      VARCHAR(50)  NOT NULL,
                code            VARCHAR(50)  NOT NULL,
                registration_no VARCHAR(100) NULL DEFAULT NULL,
                address         TEXT NULL,
                email           VARCHAR(255) NULL DEFAULT NULL,
                phone_no        VARCHAR(50)  NULL DEFAULT NULL,
                fax             VARCHAR(50)  NULL DEFAULT NULL,
                logo            VARCHAR(255) NULL DEFAULT NULL,
                created_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at      TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at      TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_firms_code (code),
                CONSTRAINT fk_firms_city FOREIGN KEY (city_id) REFERENCES cities (id) ON DELETE RESTRICT,
                INDEX idx_firms_city (city_id),
                INDEX idx_firms_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS firms');
    }
}