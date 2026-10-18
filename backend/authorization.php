<?php

declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

function isLoggedIn(): bool { return isset($_SESSION['user_id'], $_SESSION['role']); }
function isCustomer(): bool { return isLoggedIn() && $_SESSION['role'] === 'user'; }
function isRegularAdmin(): bool { return isLoggedIn() && $_SESSION['role'] === 'admin'; }
function isSuperAdmin(): bool { return isLoggedIn() && $_SESSION['role'] === 'super_admin'; }

function requireLogin(): void
{
    if (!isLoggedIn()) redirectTo('login.html');
    global $conn;
    require_once __DIR__ . '/db.php';
    $id = (int)$_SESSION['user_id'];
    $s = $conn->prepare('SELECT role,account_status FROM users WHERE id=?');
    $s->bind_param('i', $id); $s->execute(); $user = $s->get_result()->fetch_assoc();
    if (!$user || $user['account_status'] !== 'active' || $user['role'] !== $_SESSION['role']) {
        $_SESSION = []; session_destroy(); redirectTo('login.html');
    }
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
