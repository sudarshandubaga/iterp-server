<?php

declare(strict_types=1);

namespace Iterp\Database\Migrations;

use Iterp\Core\Migration;

/**
 * Auth infrastructure tables: API tokens, email verifications and password
 * resets. These are referenced by the Auth core but were missing migrations.
 */
class CreateAuthTokensTable extends Migration
{
    public function up(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS auth_tokens (
                id           BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id      BIGINT UNSIGNED NOT NULL,
                token_hash   CHAR(64)  NOT NULL,
                expires_at   TIMESTAMP NULL DEFAULT NULL,
                created_at   TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_auth_tokens_hash (token_hash),
                CONSTRAINT fk_auth_tokens_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_auth_tokens_user (user_id),
                INDEX idx_auth_tokens_expires (expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS email_verifications (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id    BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64)  NOT NULL,
                expires_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_email_verifications_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE,
                INDEX idx_email_verifications_user (user_id, expires_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );

        $this->schema(
            'CREATE TABLE IF NOT EXISTS password_resets (
                id         BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email      VARCHAR(255) NOT NULL,
                token_hash CHAR(64)  NOT NULL,
                expires_at TIMESTAMP NULL DEFAULT NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_password_resets_email (email),
                UNIQUE KEY uq_password_resets_email_hash (email, token_hash)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function down(): void
    {
        $this->schema('DROP TABLE IF EXISTS auth_tokens');
        $this->schema('DROP TABLE IF EXISTS email_verifications');
        $this->schema('DROP TABLE IF EXISTS password_resets');
    }
}