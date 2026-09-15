#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Dev-server launcher. Wraps PHP's built-in web server with public/ docroot.
 *
 * Usage:
 *   php scripts/serve.php                # 127.0.0.1:8001
 *   php scripts/serve.php 0.0.0.0 9000   # custom host/port
 */

$host = $argv[1] ?? '127.0.0.1';
$port = $argv[2] ?? (string) (getenv('PORT') ?: 8001);

$docroot = dirname(__DIR__) . '/public';
$router  = $docroot . '/router.php';

passthru("php -S {$host}:{$port} -t {$docroot} {$router}", $exitCode);
exit($exitCode);