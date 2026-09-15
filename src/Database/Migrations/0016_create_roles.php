<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * roles table.
 */
class CreateRolesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS roles (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                parent_id   BIGINT UNSIGNED NULL DEFAULT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_roles_name (name),
                CONSTRAINT fk_roles_parent FOREIGN KEY (parent_id) REFERENCES roles (id) ON DELETE CASCADE,
                INDEX idx_roles_parent (parent_id),
                INDEX idx_roles_name (name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS roles');
    }
}