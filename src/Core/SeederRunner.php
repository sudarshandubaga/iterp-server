<?php

declare(strict_types=1);

namespace Iterp\Core;

use RuntimeException;

/**
 * Seeder runner: discovers Database/Seeders/*.php and runs run() on each.
 */
class SeederRunner
{
    private string $directory;

    public function __construct(?string $directory = null)
    {
        $this->directory = $directory ?: base_path('src/Database/Seeders');
    }

    /**
     * @param string[] $only Restrict to a subset (basename without .php).
     */
    public function run(array $only = []): array
    {
        $files = glob($this->directory . '/*.php') ?: [];
        sort($files);

        // When seeding "everything", run only DatabaseSeeder — it orchestrates the
        // individual seeders in the correct foreign-key dependency order.
        if ($only === []) {
            $only = ['DatabaseSeeder'];
        } else {
            // For idempotent single-run, run request order exactly as given.
            $only = array_values(array_intersect($only, array_map(static fn ($f) => basename($f, '.php'), $files)));
        }

        $seeded = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (!in_array($name, $only, true)) {
                continue;
            }

            require_once $file;
            $class = 'Iterp\\Database\\Seeders\\' . $name;

            if (!class_exists($class)) {
                throw new RuntimeException("Seeder class not found: {$class}");
            }

            (new $class())->run();
            $seeded[] = $name;
        }

        return $seeded;
    }
}