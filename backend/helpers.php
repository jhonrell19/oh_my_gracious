<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/session.php';

function h(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function appUrl(string $path = ''): string
{
    return APP_URL . ($path !== '' ? '/' . ltrim($path, '/') : '');
}

function redirectTo(string $path): never
{
    header('Location: ' . (preg_match('#^https?://#', $path) ? $path : appUrl($path)));
    exit;
}

function jsonResponse(string $status, string $message, array $extra = [], int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra), JSON_UNESCAPED_SLASHES);
    exit;
}

function normalizePhilippinePhone(string $phone): ?string
{
    $phone = preg_replace('/[^0-9+]/', '', trim($phone)) ?? '';
    if (preg_match('/^09[0-9]{9}$/', $phone)) return '+63' . substr($phone, 1);
    if (preg_match('/^639[0-9]{9}$/', $phone)) return '+' . $phone;
    if (preg_match('/^\+639[0-9]{9}$/', $phone)) return $phone;
    return null;
}

function roleRedirect(string $role): string
{
    return match ($role) {
        'user' => 'user-dashboard.php',
        'admin' => 'admin-dashboard.php',
        'super_admin' => 'super-admin-dashboard.php',
        default => 'login.html',
    };
}

function createLoginSession(array $user): array
{
    if (($user['account_status'] ?? '') === 'suspended') {
        return ['status' => 'error', 'message' => 'This account is suspended.'];
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['full_name'] = (string)$user['full_name'];
    $_SESSION['email'] = (string)($user['email'] ?? '');
    $_SESSION['role'] = (string)$user['role'];
    $redirect = roleRedirect((string)$user['role']);
    if (($user['role'] ?? '') === 'user' && empty($user['phone_number'])) {
        $redirect = 'account.php?complete=1';
    }
    return ['status' => 'success', 'redirect' => $redirect];
}

function getSetting(mysqli $conn, string $key, string $default = ''): string
{
    $stmt = $conn->prepare('SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1');
    $stmt->bind_param('s', $key);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    return $row ? (string)$row['setting_value'] : $default;
}

function setSetting(mysqli $conn, string $key, string $value, ?int $userId = null): void
{
    $stmt = $conn->prepare('INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_by = VALUES(updated_by)');
    $stmt->bind_param('ssi', $key, $value, $userId);
    $stmt->execute();
}

function audit(mysqli $conn, string $action, ?string $entityType = null, ?int $entityId = null, array $details = []): void
{
    $actor = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
    $json = $details ? json_encode($details, JSON_UNESCAPED_SLASHES) : null;
    $ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
    $stmt = $conn->prepare('INSERT INTO audit_logs (actor_user_id, action, entity_type, entity_id, details, ip_address) VALUES (?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('ississ', $actor, $action, $entityType, $entityId, $json, $ip);
    $stmt->execute();
}

function httpGetJson(string $url, array $headers = []): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for external authentication.');
    }
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 15, CURLOPT_HTTPHEADER => $headers]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($body === false || $code >= 400) {
        throw new RuntimeException($error !== '' ? $error : 'External authentication request failed.');
    }
    $data = json_decode((string)$body, true);
    if (!is_array($data)) throw new RuntimeException('Invalid external authentication response.');
    return $data;
}

function httpPostFormJson(string $url, array $fields, ?array $basicAuth = null): array
{
    if (!function_exists('curl_init')) throw new RuntimeException('PHP cURL extension is required.');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($fields),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
    ]);
    if ($basicAuth) curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . $basicAuth[1]);
    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    $data = json_decode((string)$body, true);
    if ($body === false || $code >= 400 || !is_array($data)) {
        throw new RuntimeException($data['message'] ?? ($error !== '' ? $error : 'External request failed.'));
    }
    return $data;
}


function httpPostJson(string $url, array $payload, array $headers = [], ?array $basicAuth = null, ?string $idempotencyKey = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required.');
    }

    $headers = array_merge(['Accept: application/json', 'Content-Type: application/json'], $headers);
    if ($idempotencyKey !== null && $idempotencyKey !== '') {
        $headers[] = 'Idempotency-Key: ' . $idempotencyKey;
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_SLASHES),
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($basicAuth) {
        curl_setopt($ch, CURLOPT_USERPWD, $basicAuth[0] . ':' . ($basicAuth[1] ?? ''));
    }

    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    $data = json_decode((string)$body, true);
    if ($body === false || $code >= 400 || !is_array($data)) {
        $message = is_array($data)
            ? (string)($data['errors'][0]['detail'] ?? $data['message'] ?? 'External request failed.')
            : ($error !== '' ? $error : 'External request failed.');
        throw new RuntimeException($message);
    }
    return $data;
}
