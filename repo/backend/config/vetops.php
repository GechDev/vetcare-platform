<?php

return [
    'timezone' => env('VETOPS_API_TIMEZONE', 'UTC'),

    'currency' => [
        'code' => env('VETOPS_CURRENCY_CODE', 'USD'),
        'amount_format' => 'integer_cents',
    ],

    'auth' => [
        'inactivity_timeout_minutes' => (int) env('VETOPS_AUTH_INACTIVITY_TIMEOUT_MINUTES', 15),
        'login_max_attempts' => (int) env('VETOPS_AUTH_LOGIN_MAX_ATTEMPTS', 10),
        'login_decay_seconds' => (int) env('VETOPS_AUTH_LOGIN_DECAY_SECONDS', 600),
    ],
];
