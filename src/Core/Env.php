<?php

declare(strict_types=1);

namespace Iterp\Core;

/**
 * Loads and parses a simple KEY=VALUE .env file into $_ENV / getenv().
 */
class Env
{
    public static function load(string $path): array
    {
        $values = [];
        if (!is_file($path)) {
            return $values;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key   = trim($key);
            $value = trim($value);

            // Strip surrounding quotes.
            if ((str_starts_with($value, '"') && str_ends_with($value, '"'))
                || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
            $_ENV[$key] = $value;
            putenv($key . '=' . $value);
        }

        return $values;
    }
}