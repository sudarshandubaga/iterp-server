<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * users table.
 */
class CreateUsersTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS users (
                id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                first_name  VARCHAR(255) NOT NULL,
                middle_name VARCHAR(255) NULL DEFAULT NULL,
                last_name   VARCHAR(255) NULL DEFAULT NULL,
                username    VARCHAR(150) NOT NULL,
                password    VARCHAR(255) NOT NULL,
                title_id    BIGINT UNSIGNED NULL DEFAULT NULL,
                gender      ENUM("m","f") NULL DEFAULT NULL,
                role_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                dob         DATE NULL DEFAULT NULL COMMENT \'date of birth\',
                doj         DATE NULL DEFAULT NULL COMMENT \'date of joining\',
                email       VARCHAR(255) NULL DEFAULT NULL,
                mobile_no   VARCHAR(50)  NULL DEFAULT NULL,
                city_id     BIGINT UNSIGNED NULL DEFAULT NULL,
                is_active   ENUM("y","n") NOT NULL DEFAULT "y",
                created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at  TIMESTAMP NULL DEFAULT NULL,
                UNIQUE KEY uq_users_username (username),
                UNIQUE KEY uq_users_email (email),
                CONSTRAINT fk_users_title FOREIGN KEY (title_id) REFERENCES titles (id) ON DELETE SET NULL,
                CONSTRAINT fk_users_role  FOREIGN KEY (role_id)  REFERENCES roles (id)  ON DELETE SET NULL,
                CONSTRAINT fk_users_city  FOREIGN KEY (city_id)  REFERENCES cities (id) ON DELETE SET NULL,
                INDEX idx_users_title (title_id),
                INDEX idx_users_role (role_id),
                INDEX idx_users_city (city_id),
                INDEX idx_users_name (first_name, middle_name, last_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS users');
    }
}