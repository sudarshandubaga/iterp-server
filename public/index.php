<?php

declare(strict_types=1);

use Iterp\Core\Request;
use Iterp\Core\Router;

/**
 * HTTP front controller.
 *
 * Bootstraps the app, applies CORS headers, loads the API route table and
 * dispatches the incoming request to the matching handler.
 */

// 1. Bootstrap (env, autoloader, helpers, error handling).
require dirname(__DIR__) . '/bootstrap.php';

// 2. CORS (allow the Vite/React client on a different port).
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 86400');

// 3. Load routes (returns a Router instance).
/** @var Router $router */
$router = require dirname(__DIR__) . '/routes/api.php';

// 4. Dispatch.
$router->dispatch(Request::capture());