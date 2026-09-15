<?php

return [
    'name'    => env('APP_NAME', 'iterp'),
    'env'     => env('APP_ENV', 'production'),
    'debug'   => env('APP_DEBUG', false) === true || env('APP_DEBUG', '') === 'true',
    'url'     => env('APP_URL', 'http://localhost:8001'),
    'timezone'=> env('APP_TIMEZONE', 'UTC'),
];