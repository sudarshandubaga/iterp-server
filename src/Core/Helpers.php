<?php

declare(strict_types=1);

use Iterp\Core\Database;

if (!function_exists('base_path')) {
    function base_path(string $path = ''): string
    {
        $root = dirname(__DIR__, 2); // server/
        return $path === '' ? $root : $root . '/' . ltrim($path, '/');
    }
}

if (!function_exists('config')) {
    /**
     * Get a "." dotted config value: config('database.connections.mysql.host').
     */
    function config(string $key = '', $default = null)
    {
        static $loaded = null;

        if ($loaded === null) {
            $loaded = [];
            foreach (glob(base_path('config') . '/*.php') ?: [] as $file) {
                $name = basename($file, '.php');
                $loaded[$name] = (array) require $file;
            }
        }

        if ($key === '') {
            return $loaded;
        }

        $value = $loaded;
        foreach (explode('.', $key) as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
            } else {
                return $default;
            }
        }

        return $value;
    }
}

if (!function_exists('env')) {
    function env(string $key, $default = null)
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        $lower = strtolower($value);
        if (in_array($lower, ['true', '(true)', '1'], true)) {
            return true;
        }
        if (in_array($lower, ['false', '(false)', '0'], true)) {
            return false;
        }
        if ($lower === 'null' || $value === '') {
            return $value === '' ? '' : null;
        }
        return $value;
    }
}

if (!function_exists('e')) {
    function e($value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('redirect')) {
    function redirect(string $url)
    {
        header('Location: ' . $url);
        exit;
    }
}

if (!function_exists('render')) {
    /**
     * Render a plain-PHP template under resources/views. Dot notation = sub-dirs.
     */
    function render(string $view, array $data = []): string
    {
        $file = base_path('resources/views/' . str_replace('.', '/', $view) . '.php');
        if (!is_file($file)) {
            throw new RuntimeException("View not found: {$view}");
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;
        return (string) ob_get_clean();
    }
}

if (!function_exists('json_response')) {
    /**
     * Convenience shortcut to build and send a JSON response.
     */
    function json_response(int $status, mixed $data = [], array $headers = []): \Iterp\Core\Response
    {
        return \Iterp\Core\Response::json($status, $data, $headers)->send();
    }
}

if (!function_exists('db')) {
    function db(): \PDO
    {
        return Database::pdo();
    }
}

if (!function_exists('str_random')) {
    function str_random(int $length = 40): string
    {
        return bin2hex(random_bytes((int) ceil($length / 2)));
    }
}