<?php

declare(strict_types=1);

/**
 * Framework-free bootstrap: loads the .env, registers the autoloader,
 * wires global helpers, applies the configured timezone and error handling.
 */
use Iterp\Core\Autoloader;
use Iterp\Core\Env;

// 1. Bootstrap the PSR-4-style autoloader (Iterp\ => src/).
//    The autoloader must be required directly - it can't load itself.
require_once __DIR__ . '/src/Core/Autoloader.php';
$autoloader = new Iterp\Core\Autoloader();
$autoloader->register();

// 2. Load environment variables (must precede config()/getenv()).
Env::load(__DIR__ . '/.env');

// 3. Require global helper functions (functions aren't autoloaded).
require_once __DIR__ . '/src/Core/Helpers.php';

// 4. Apply timezone + error handling.
date_default_timezone_set((string) config('app.timezone', 'UTC'));

error_reporting(E_ALL);
ini_set('display_errors', config('app.debug', false) ? '1' : '0');

// Converts uncaught Throwables into JSON responses (API consistency).
set_exception_handler(function (Throwable $e) {
    $debug = config('app.debug', false);

    $payload = [
        'success' => false,
        'message'  => 'Internal server error.',
    ];

    if ($debug) {
        $payload['message'] = $e->getMessage();
        $payload['file']    = $e->getFile() . ':' . $e->getLine();
        $payload['trace']    = $e->getTraceAsString();
    }

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit(1);
});