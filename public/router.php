<?php

declare(strict_types=1);

/**
 * Router script for PHP's built-in development server.
 *
 * Requests for real files (assets/css/js) are served directly; everything
 * else is forwarded to the API front controller.
 *
 * Launch with:
 *   php -S localhost:8001 -t public router.php
 */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$file = __DIR__ . '/' . ltrim($path, '/');

if ($path !== '/' && $file !== false && is_file($file)) {
    return false; // let the built-in server serve the static file.
}

require __DIR__ . '/index.php';