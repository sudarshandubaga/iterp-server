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

if (!function_exists('base_url')) {
    /**
     * Resolve the current full application base URL (scheme + host[:port] + optional path).
     * Dynamically detects HTTPS, reverse-proxy headers, and host from incoming request,
     * falling back to config('app.url') when running in CLI/cron.
     */
    function base_url(string $path = ''): string
    {
        $scheme = 'http';
        if (
            (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') ||
            (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
            (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_SSL']) === 'on') ||
            (isset($_SERVER['HTTP_CF_VISITOR']) && str_contains((string) $_SERVER['HTTP_CF_VISITOR'], '"scheme":"https"'))
        ) {
            $scheme = 'https';
        }

        $host = $_SERVER['HTTP_X_FORWARDED_HOST']
            ?? $_SERVER['HTTP_HOST']
            ?? $_SERVER['SERVER_NAME']
            ?? null;

        if ($host) {
            if (str_contains($host, ',')) {
                $host = trim(explode(',', $host)[0]);
            }
            $base = $scheme . '://' . $host;
        } else {
            $base = rtrim((string) config('app.url', 'http://localhost:8001'), '/');
        }

        $base = rtrim($base, '/');

        if ($path === '' || $path === '/') {
            return $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset_url')) {
    /**
     * Return the complete full URL with domain for an asset or image path.
     * Preserves data URLs and external URLs that are not local uploads.
     */
    function asset_url(?string $path): ?string
    {
        if ($path === null || trim($path) === '') {
            return null;
        }

        $path = trim($path);

        if (str_starts_with($path, 'data:') || str_starts_with($path, 'blob:')) {
            return $path;
        }

        // If it's a local /uploads/ asset (even if prefixed with an old/different domain), normalize to current domain
        if (preg_match('~(/uploads/[^\s?#]+)~i', $path, $m)) {
            return base_url($m[1]);
        }

        // If it starts with uploads/ (missing leading slash)
        if (str_starts_with($path, 'uploads/')) {
            return base_url('/' . $path);
        }

        // External absolute URL (e.g. S3, Cloudinary, external CDN)
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        // Other relative asset path
        return base_url('/' . ltrim($path, '/'));
    }
}

if (!function_exists('asset')) {
    /**
     * Alias for asset_url() to match standard Laravel / PHP conventions.
     */
    function asset(?string $path): ?string
    {
        return asset_url($path);
    }
}

if (!function_exists('transform_asset_urls')) {
    /**
     * Recursively traverse an array/payload and expand image/asset paths
     * to complete full URLs with domain.
     */
    function transform_asset_urls(mixed $data): mixed
    {
        if (is_array($data)) {
            $result = [];
            foreach ($data as $key => $value) {
                if (is_string($value) && is_string($key)) {
                    $lowerKey = strtolower($key);
                    $isImageKey = in_array($lowerKey, [
                        'logo',
                        'firm_logo',
                        'photo',
                        'student_photo',
                        'employee_photo',
                        'avatar',
                        'favicon',
                        'image',
                        'file_url',
                        'attachment_url',
                    ], true);

                    if ($isImageKey || str_contains($value, '/uploads/') || str_starts_with($value, 'uploads/')) {
                        $result[$key] = asset_url($value);
                        continue;
                    }
                } elseif (is_string($value)) {
                    if (str_contains($value, '/uploads/') || str_starts_with($value, 'uploads/')) {
                        $result[$key] = asset_url($value);
                        continue;
                    }
                }

                $result[$key] = is_array($value) ? transform_asset_urls($value) : $value;
            }
            return $result;
        }

        if (is_string($data) && (str_contains($data, '/uploads/') || str_starts_with($data, 'uploads/'))) {
            return asset_url($data);
        }

        return $data;
    }
}
