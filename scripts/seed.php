<?php

declare(strict_types=1);

/**
 * Seeder CLI.
 *
 * Usage:
 *   php scripts/seed.php                    # run all seeders
 *   php scripts/seed.php UserSeeder         # run a specific seeder
 */

use Iterp\Core\SeederRunner;

require dirname(__DIR__) . '/bootstrap.php';

$only = array_slice($argv, 1);

try {
    $runner  = new SeederRunner();
    $seeded  = $runner->run($only);

    if ($seeded === []) {
        echo "No seeders ran.\n";
        exit(1);
    }

    echo "Seeded " . count($seeded) . " seeder(s):\n";
    foreach ($seeded as $name) {
        echo "  [OK] {$name}\n";
    }
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n");
    exit(1);
}