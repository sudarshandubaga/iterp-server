<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * tenants table.
 */
class CreateTenantsTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS tenants (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name        VARCHAR(255) NOT NULL,
                logo        VARCHAR(255) NULL DEFAULT NULL,
                favicon     VARCHAR(255) NULL DEFAULT NULL,
                domain      VARCHAR(255) NULL DEFAULT NULL,
                email       VARCHAR(255) NULL DEFAULT NULL,
                phone_no    VARCHAR(50)  NULL DEFAULT NULL,
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                INDEX idx_tenants_name (name),
                INDEX idx_tenants_domain (domain)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS tenants');
    }
}