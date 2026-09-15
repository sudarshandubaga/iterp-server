<?php

return [
    'default' => env('MAIL_MAILER', 'smtp'),

    'smtp' => [
        'host'     => env('MAIL_HOST', 'smtp.mailtrap.io'),
        'port'     => (int) env('MAIL_PORT', 2525),
        'username' => env('MAIL_USERNAME', ''),
        'password' => env('MAIL_PASSWORD', ''),
        'encryption'=> 'tls',   // tls (STARTTLS) | ssl | null
    ],

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'no-reply@iterp.local'),
        'name'    => env('MAIL_FROM_NAME', 'Iterp'),
    ],

    // Path the client uses for links embedded in e-mails.
    'client_url' => env('CLIENT_URL', 'http://localhost:5173'),
];