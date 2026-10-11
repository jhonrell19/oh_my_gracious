<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli(
        (string)env('DB_HOST', '127.0.0.1'),
        (string)env('DB_USER', 'root'),
        (string)env('DB_PASSWORD', ''),
        (string)env('DB_NAME', 'oh_my_gracious'),
        (int)env('DB_PORT', '3306')
    );
    $conn->set_charset('utf8mb4');
} catch (Throwable $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')) {
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => 'Database connection failed.']);
    } else {
        echo 'The application could not connect to the database.';
    }
    exit;
}
