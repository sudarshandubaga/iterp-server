<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;
use RuntimeException;

/**
 * Base class for database migrations. Each migration defines up()/down().
 */
abstract class Migration
{
    public function connection(): PDO
    {
        return Database::pdo();
    }

    /**
     * Execute raw schema SQL.
     */
    protected function schema(string $sql): void
    {
        $this->connection()->exec($sql);
    }

    /**
     * Ensure the migrations bookkeeping table exists.
     */
    protected function ensureMigrationsTable(): void
    {
        $this->schema(
            'CREATE TABLE IF NOT EXISTS migrations (
                id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration    VARCHAR(255) NOT NULL UNIQUE,
                batch        INT UNSIGNED NOT NULL DEFAULT 1,
                executed_at  TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    abstract public function up(): void;
    abstract public function down(): void;
}