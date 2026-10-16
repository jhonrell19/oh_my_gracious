<?php

declare(strict_types=1);

function loadEnvFile(string $path): void
{
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [] as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) continue;
        [$key, $value] = array_map('trim', explode('=', $line, 2));
        if ($key === '') continue;
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

// Google is the only external/social authentication provider supported by this build.
define('GOOGLE_CLIENT_ID', env('GOOGLE_CLIENT_ID', ''));
