<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
$token = trim((string)($_GET['token'] ?? ''));
if ($token === '') exit('Invalid verification link.');
$hash = hash('sha256', $token);
$stmt = $conn->prepare('SELECT id,user_id FROM email_verification_tokens WHERE token_hash=? AND used_at IS NULL AND expires_at>NOW() LIMIT 1');
$stmt->bind_param('s', $hash);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
if (!$row) exit('This verification link is invalid or expired.');
try {
    $conn->begin_transaction();
    $u = $conn->prepare("UPDATE users SET email_verified_at=NOW(), account_status=CASE WHEN account_status='pending_verification' THEN 'active' ELSE account_status END WHERE id=?");
    $u->bind_param('i', $row['user_id']); $u->execute();
    $t = $conn->prepare('UPDATE email_verification_tokens SET used_at=NOW() WHERE id=?');
    $t->bind_param('i', $row['id']); $t->execute();
    $conn->commit();
    redirectTo('login.html?verified=1');
} catch (Throwable $e) { $conn->rollback(); error_log($e->getMessage()); exit('Verification failed.'); }
