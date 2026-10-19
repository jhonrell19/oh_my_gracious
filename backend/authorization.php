<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function isLoggedIn(): bool { return isset($_SESSION['user_id'], $_SESSION['role']); }
function isCustomer(): bool { return isLoggedIn() && $_SESSION['role'] === 'user'; }
function isRegularAdmin(): bool { return isLoggedIn() && $_SESSION['role'] === 'admin'; }
function isSuperAdmin(): bool { return isLoggedIn() && $_SESSION['role'] === 'super_admin'; }

function requireLogin(bool $allowMfaSetup = false): void
{
    if (!isLoggedIn()) redirectTo('login.html');
    global $conn;
    require_once __DIR__ . '/db.php';
    $id = (int)$_SESSION['user_id'];
    $s = $conn->prepare('SELECT role,account_status,password_hash FROM users WHERE id=?');
    $s->bind_param('i', $id); $s->execute(); $user = $s->get_result()->fetch_assoc();
    if (!$user || $user['account_status'] !== 'active' || $user['role'] !== $_SESSION['role']) {
        $_SESSION = []; session_destroy(); redirectTo('login.html');
    }
    require_once __DIR__.'/security.php';
    $security=securityRow($conn,$id);
    $staff=in_array($user['role'],['admin','super_admin'],true);
    $idle=$staff?1800:7200; $absolute=$staff?28800:86400;
    if (!isset($_SESSION['signed_in_at'],$_SESSION['last_active_at'],$_SESSION['password_fingerprint'],$_SESSION['security_version'])
        || time()-(int)$_SESSION['last_active_at']>$idle || time()-(int)$_SESSION['signed_in_at']>$absolute
        || !hash_equals($_SESSION['password_fingerprint'],hash('sha256',(string)($user['password_hash']??'')))
        || (int)$_SESSION['security_version']!==(int)$security['session_version']) {
        $_SESSION=[];session_destroy();redirectTo('login.html');
    }
    $_SESSION['last_active_at']=time();
    header('Cache-Control: no-store, private');
    if($staff && !$allowMfaSetup && (empty($security['totp_secret']) || empty($_SESSION['mfa_verified'])))redirectTo('staff-security.php');

}

function requireCustomer(): void
{
    requireLogin();
    if (!isCustomer()) redirectTo(isSuperAdmin() ? 'super-admin-dashboard.php' : 'admin-dashboard.php');
}

function requireRegularAdmin(): void
{
    requireLogin();
    if (!isRegularAdmin()) redirectTo(isSuperAdmin() ? 'super-admin-dashboard.php' : 'user-dashboard.php');
}

function requireSuperAdmin(): void
{
    requireLogin();
    if (!isSuperAdmin()) redirectTo(isRegularAdmin() ? 'admin-dashboard.php' : 'user-dashboard.php');
}
