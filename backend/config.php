<?php

declare(strict_types=1);

function loadEnvFile(string $path): void
{
    if (!is_file($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') {
            continue;
        }
        if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
            $value = substr($value, 1, -1);
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

loadEnvFile(dirname(__DIR__) . '/.env');

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    return ($value === false || $value === null || $value === '') ? $default : (string)$value;
}

define('APP_ENV', env('APP_ENV', 'local'));
define('APP_URL', rtrim((string)env('APP_URL', 'http://localhost/oh_my_gracious'), '/'));
define('APP_TIMEZONE', env('APP_TIMEZONE', 'Asia/Manila'));
date_default_timezone_set((string)APP_TIMEZONE);

define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
define('FACEBOOK_APP_ID', env('FACEBOOK_APP_ID', ''));
define('FACEBOOK_APP_SECRET', env('FACEBOOK_APP_SECRET', ''));
define('FACEBOOK_GRAPH_VERSION', env('FACEBOOK_GRAPH_VERSION', 'v25.0'));
define('TWILIO_ACCOUNT_SID', env('TWILIO_ACCOUNT_SID', ''));
define('TWILIO_AUTH_TOKEN', env('TWILIO_AUTH_TOKEN', ''));
define('TWILIO_VERIFY_SERVICE_SID', env('TWILIO_VERIFY_SERVICE_SID', ''));

define('PAYMONGO_SECRET_KEY', env('PAYMONGO_SECRET_KEY', ''));
define('PAYMONGO_WEBHOOK_SECRET', env('PAYMONGO_WEBHOOK_SECRET', ''));
define('PAYMONGO_PAYMENT_METHODS', env('PAYMONGO_PAYMENT_METHODS', 'card,gcash,paymaya,qrph'));
