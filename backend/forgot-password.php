<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();
$email = strtolower(trim((string)($_POST['email'] ?? '')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse('error', 'Enter a valid email address.', [], 422);
$stmt = $conn->prepare("SELECT id FROM users WHERE email=? AND account_status<>'suspended' LIMIT 1");
$stmt->bind_param('s', $email); $stmt->execute(); $row = $stmt->get_result()->fetch_assoc();
$extra = [];
if ($row) {
    $raw = bin2hex(random_bytes(32)); $hash = hash('sha256', $raw); $expires = date('Y-m-d H:i:s', time()+3600);
    $conn->query('DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
    $ins = $conn->prepare('INSERT INTO password_reset_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)');
    $ins->bind_param('iss', $row['id'], $hash, $expires); $ins->execute();
    $url = appUrl('reset-password.php?token=' . urlencode($raw));
    if (APP_ENV !== 'production') $extra['reset_url'] = $url;
    else @mail($email, 'Reset your Oh My Gracious password', "Reset your password here:\n\n".$url, 'From: '.env('MAIL_FROM','no-reply@localhost'));
}
jsonResponse('success', 'If that email exists, a password reset link has been prepared.', $extra);
