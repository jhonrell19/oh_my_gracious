<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();

$identifier = strtolower(trim((string)($_POST['identifier'] ?? '')));
$password = (string)($_POST['password'] ?? '');
$ip = substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 64);
if (strlen($identifier)>190 || strlen($password)>1024) jsonResponse('error','Invalid email or password.',[],422);
if ($identifier === '' || $password === '') jsonResponse('error', 'Please enter your email and password.', [], 422);
$hash = hash('sha256', $identifier);
require_once __DIR__.'/security.php';
try {securityThrottle($conn,'password-account',$identifier,10);securityThrottle($conn,'password-ip',$ip,60);}
catch(Throwable $e){jsonResponse('error','Too many attempts. Wait 15 minutes.',[],429);}

$limit = $conn->prepare("SELECT COUNT(*) AS total FROM login_attempts WHERE identifier_hash=? AND ip_address=? AND was_successful=0 AND created_at >= (NOW() - INTERVAL 15 MINUTE)");
$limit->bind_param('ss', $hash, $ip);
$limit->execute();
if ((int)$limit->get_result()->fetch_assoc()['total'] >= 5) jsonResponse('error', 'Too many failed attempts. Please wait 15 minutes and try again.', [], 429);

$stmt = $conn->prepare('SELECT id,full_name,email,phone_number,password_hash,role,account_status FROM users WHERE email=? LIMIT 1');
$stmt->bind_param('s', $identifier);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$success = $user && !empty($user['password_hash']) && password_verify($password, (string)$user['password_hash']);
$attempt = $conn->prepare('INSERT INTO login_attempts (identifier_hash,ip_address,was_successful) VALUES (?,?,?)');
$ok = $success ? 1 : 0;
$attempt->bind_param('ssi', $hash, $ip, $ok);
$attempt->execute();
if (!$success) jsonResponse('error', 'Invalid email or password.', [], 401);
if ($user['account_status'] === 'pending_verification') jsonResponse('error', 'Please verify your email before signing in.', [], 403);
if ($user['account_status'] === 'suspended') jsonResponse('error', 'This account is suspended.', [], 403);
if (!in_array($user['role'], ['user','admin','super_admin'], true)) jsonResponse('error', 'This account has an invalid role.', [], 403);

clearSecurityThrottle($conn,'password-account',$identifier);
$update = $conn->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
$update->bind_param('i', $user['id']);
$update->execute();
audit($conn, 'login_success', 'user', (int)$user['id']);
$result = createLoginSession($user);
jsonResponse('success', 'Login successful.', ['redirect' => $result['redirect']]);
