<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * user_roles table.
 *
 * Junction table to allow a single user to be assigned multiple roles.
 */
class CreateUserRolesTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS user_roles (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    BIGINT UNSIGNED NOT NULL,
                role_id    BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_user_roles (user_id, role_id),
                CONSTRAINT fk_user_roles_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                CONSTRAINT fk_user_roles_role FOREIGN KEY (role_id) REFERENCES roles (id) ON DELETE CASCADE,
                INDEX idx_user_roles_user (user_id),
                INDEX idx_user_roles_role (role_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        // Migrate existing user primary roles into the user_roles junction table.
        $this->schema(
            'INSERT IGNORE INTO user_roles (user_id, role_id, created_at)
             SELECT id, role_id, NOW() FROM users WHERE role_id IS NOT NULL'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS user_roles');
    }
}
