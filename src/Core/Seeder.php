<?php

declare(strict_types=1);

namespace Iterp\Core;

use PDO;

/**
 * Base class for seeders.
 */
abstract class Seeder
{
    public function connection(): PDO
    {
        return Database::pdo();
    }

    abstract public function run(): void;
}