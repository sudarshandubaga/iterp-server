<?php

declare(strict_types=1);

/**
 * Migration CLI.
 *
 * Usage:
 *   php scripts/migrate.php            # run all pending migrations
 *   php scripts/migrate.php status      # list applied/pending
 *   php scripts/migrate.php rollback     # rollback the last batch
 *   php scripts/migrate.php fresh        # drop-all + re-migrate
 */

use Iterp\Core\Database;
use Iterp\Core\Migrator;

require dirname(__DIR__) . '/bootstrap.php';

$command = $argv[1] ?? 'migrate';

try {
    switch ($command) {
        case 'status':
            status();
            break;

        case 'migrate':
        case 'up':
            migrateUp();
            break;

        case 'rollback':
            $steps = (int) ($argv[2] ?? 1);
            rollback($steps);
            break;

        case 'fresh':
            fresh();
            break;

        default:
            fwrite(STDERR, "Unknown command: {$command}\n");
            showHelp();
            exit(1);
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(1);
}

function showHelp(): void
{
    echo "Usage:\n"
        . "  php scripts/migrate.php            run all pending migrations\n"
        . "  php scripts/migrate.php status     list applied/pending\n"
        . "  php scripts/migrate.php rollback   rollback the last batch\n"
        . "  php scripts/migrate.php fresh      drop-all + re-migrate\n";
}

function migrateUp(): void
{
    $migrator = new Migrator();
    $pending  = $migrator->pending();

    if ($pending === []) {
        echo "Nothing to migrate.\n";
        return;
    }

    echo 'Running ' . count($pending) . " migration(s)...\n";
    foreach ($migrator->migrate() as $name) {
        echo "  [OK] {$name}\n";
    }
}

function status(): void
{
    $migrator   = new Migrator();
    $applied    = $migrator->appliedMigrations();
    $allFiles   = array_map(static fn ($f) => basename($f, '.php'), $migrator->all());

    echo str_pad('Migration', 60) . "Status\n";
    echo str_repeat('-', 68) . "\n";

    foreach ($allFiles as $name) {
        $state = in_array($name, $applied, true) ? 'applied' : 'pending';
        echo str_pad($name, 60) . $state . "\n";
    }
}

function rollback(int $steps): void
{
    $migrator = new Migrator();
    $removed  = $migrator->rollback($steps);

    if ($removed === []) {
        echo "Nothing to roll back.\n";
        return;
    }

    echo "Rolled back:\n";
    foreach ($removed as $name) {
        echo "  [OK] {$name}\n";
    }
}

function fresh(): void
{
    $migrator = new Migrator();

    // Drop every tracked table (reverse order via apply-down on reversed list),
    // then re-run all migrations.
    $applied = array_reverse($migrator->appliedMigrations());
    foreach ($applied as $name) {
        require_once $migrator->directory . '/' . $name . '.php';
        $class = $migrator->classFromFilename($name);
        (new $class())->down();
        Database::pdo()->prepare('DELETE FROM migrations WHERE migration = ?')->execute([$name]);
        echo "  dropped {$name}\n";
    }

    migrateUp();
}