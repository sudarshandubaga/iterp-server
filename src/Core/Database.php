<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;
use PDOException;
use RuntimeException;

/**
 * Framework-free PDO connection manager (MySQL).
 */
class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            self::$pdo = self::makeConnection();
        }
        return self::$pdo;
    }

    private static function makeConnection(bool $loadDatabase = true): PDO
    {
        $config = config('database.connections.' . config('database.default'), []);

        if ($config === []) {
            throw new RuntimeException('Database configuration not found.');
        }

        $dsn = sprintf(
            'mysql:host=%s;port=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['charset'] ?? 'utf8mb4'
        );

        if ($loadDatabase) {
            $dsn .= ';dbname=' . $config['database'];
        }

        try {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $pdo = new PDO($dsn, $config['username'], $config['password'], $options);
            $pdo->exec('SET NAMES ' . $config['charset']);
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException(
                'Database connection failed: ' . $e->getMessage(),
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Ensure the configured database exists (create-on-demand).
     */
    public static function createDatabaseIfMissing(): void
    {
        $config = config('database.connections.' . config('database.default'), []);
        $server = self::makeConnection(false);

        $exists = $server->query(
            "SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = "
            . $server->quote($config['database'])
        )->fetch();

        if (!$exists) {
            $sql = sprintf(
                'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET `%s` COLLATE `%s`',
                $config['database'],
                $config['charset'] ?? 'utf8mb4',
                $config['collation'] ?? 'utf8mb4_unicode_ci'
            );
            $server->exec($sql);
        }
    }

    public static function reconnect(): PDO
    {
        self::$pdo = null;
        return self::pdo();
    }
}