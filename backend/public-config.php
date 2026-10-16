<?php

declare(strict_types=1);
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'status' => 'success',
    'csrf_token' => csrfToken(),
    'google_enabled' => GOOGLE_CLIENT_ID !== '',
    'google_client_id' => GOOGLE_CLIENT_ID,
]);
