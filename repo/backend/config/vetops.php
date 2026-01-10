<?php

return [
    /**
     * API Timezone Configuration
     * Facility-local timezone for all timestamp displays and business hour calculations.
     * Stored internally as UTC in database.
     */
    'timezone' => env('VETCARE_API_TIMEZONE', 'UTC'),

    /**
     * Currency and Money Configuration
     * USD is the primary currency for all financial transactions.
     * All monetary values are stored as integer cents to avoid floating-point errors.
     */
    'currency' => [
        'code' => env('VETCARE_CURRENCY_CODE', 'USD'),
        'amount_format' => 'integer_cents', // All amounts stored as cents: $99.99 = 9999
    ],

    /**
     * Authentication and Session Management
     * Configurable security policies for login attempts, session timeouts, and CAPTCHA challenges.
     */
    'auth' => [
        // Session inactivity timeout in minutes. Default: 15 minutes per policy.
        'inactivity_timeout_minutes' => (int) env('VETCARE_AUTH_INACTIVITY_TIMEOUT_MINUTES', 15),
        
        // Maximum failed login attempts before temporary lockout. Default: 10 per 10 minutes.
        'login_max_attempts' => (int) env('VETCARE_AUTH_LOGIN_MAX_ATTEMPTS', 10),
        
        // Lockout duration after max attempts exceeded (seconds). Default: 600 (10 minutes).
        'login_decay_seconds' => (int) env('VETCARE_AUTH_LOGIN_DECAY_SECONDS', 600),
        
        // Number of failed attempts before CAPTCHA is required. Default: 5
        // Empty env becomes (int) '' === 0, which would require CAPTCHA on every login; treat as default.
        'captcha_after_failures' => (function (): int {
            $raw = env('VETCARE_AUTH_CAPTCHA_AFTER_FAILURES');
            if ($raw === null || $raw === '') {
                return 5;
            }
            $n = (int) $raw;
            return $n > 0 ? $n : 5;
        })(),
        
        // CAPTCHA challenge validity period in minutes. Default: 5 minutes
        // After TTL expires, user must request new challenge from login form.
        'captcha_challenge_ttl_minutes' => (int) env('VETCARE_AUTH_CAPTCHA_TTL_MINUTES', 5),
    ],

    /**
     * Audit and Compliance Configuration
     * Immutable audit logs are retained for regulatory compliance and security investigation.
     */
    'audit' => [
        // Audit log retention period in years. Default: 7 years per compliance requirements.
        // Includes: login/logout, master data changes, exports, approvals, configuration changes.
        'retention_years' => (int) env('VETCARE_AUDIT_RETENTION_YEARS', 7),
    ],
];

