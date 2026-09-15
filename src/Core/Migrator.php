<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;
use RuntimeException;
use Throwable;

/**
 * Migration runner: discovers Database/Migrations/*.php, applies only those
 * that are not yet recorded, in filename order, within transactions.
 */
class Migrator
{
    public string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: base_path('src/Database/Migrations');
        // Ensure the configured database exists before connecting to it
        // (DDL migrations cannot run against a missing database).
        Database::createDatabaseIfMissing();
        $this->createMigrationsTable();
    }

    public function createMigrationsTable(): void
    {
        Database::pdo()->exec(
            'CREATE TABLE IF NOT EXISTS migrations (
                id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                migration   VARCHAR(255) NOT NULL UNIQUE,
                batch       INT UNSIGNED NOT NULL DEFAULT 1,
                executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'
        );
    }

    public function all(): array
    {
        $files = glob($this->directory . '/*.php');
        sort($files);
        return $files;
    }

    public function pending(): array
    {
        $applied = $this->appliedMigrations();
        $pending = [];

        foreach ($this->all() as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $applied, true)) {
                $pending[] = $file;
            }
        }

        return $pending;
    }

    public function appliedMigrations(): array
    {
        $rows = Database::pdo()->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
        return $rows ?: [];
    }

    public function migrate(): array
    {
        Database::createDatabaseIfMissing();

        $pending = $this->pending();
        $batch   = $this->nextBatchNumber();

        $executed = [];

        foreach ($pending as $file) {
            require_once $file;
            $name = basename($file, '.php');          // e.g. 0001_create_users_table
            $class = $this->classFromFilename($name);

            if (!class_exists($class)) {
                throw new RuntimeException("Migration class not found for {$name}: expected {$class}");
            }

            $pdo = Database::pdo();
            try {
                $migration = new $class();
                $migration->up();

                $stmt = $pdo->prepare('INSERT INTO migrations (migration, batch) VALUES (?, ?)');
                $stmt->execute([$name, $batch]);

                $executed[] = $name;
            } catch (Throwable $e) {
                // DDL (CREATE TABLE / DROP TABLE) implicitly commits in MySQL, so
                // schema changes cannot be wrapped in a transaction. Report the failure.
                throw new RuntimeException("Migration {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $executed;
    }

    public function rollback(int $steps = 1): array
    {
        $pdo = Database::pdo();
        $applied = $this->appliedMigrations();

        if ($applied === []) {
            return [];
        }

        $target = array_slice($applied, -$steps);
        $removed = [];

        foreach (array_reverse($target) as $name) {
            $file = $this->directory . '/' . $name . '.php';
            if (!is_file($file)) {
                continue;
            }
            require_once $file;
            $class = $this->classFromFilename($name);

            try {
                $migration = new $class();
                $migration->down();
                $pdo->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$name]);
                $removed[] = $name;
            } catch (Throwable $e) {
                throw new RuntimeException("Rollback of {$name} failed: " . $e->getMessage(), 0, $e);
            }
        }

        return $removed;
    }

    private function nextBatchNumber(): int
    {
        $batch = Database::pdo()->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();
        return (int) $batch + 1;
    }

    public function classFromFilename(string $name): string
    {
        $parts = explode('_', $name);
        array_shift($parts); // strip the numeric prefix e.g. 0001_

        $class = '';
        foreach ($parts as $part) {
            $class .= ucfirst($part);
        }
        $class .= 'Table';

        return 'Iterp\\Database\\Migrations\\' . $class;
    }
}