<?php

declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();require_once __DIR__.'/security.php';try{securityThrottle($conn,'google-ip',(string)($_SERVER['REMOTE_ADDR']??''),60);}catch(Throwable $e){jsonResponse('error','Too many requests. Wait 15 minutes.',[],429);}

if (GOOGLE_CLIENT_ID === '') jsonResponse('error', 'Google sign-in is not configured.', [], 503);
$credential = trim((string)($_POST['credential'] ?? ''));
if ($credential === '') jsonResponse('error', 'Google credential missing.', [], 422);

try {
    // Validate the Google Identity Services ID token against the configured Web Client ID.
    require_once dirname(__DIR__) . '/vendor/autoload.php';
    $client = new Google\Client(['client_id' => GOOGLE_CLIENT_ID]);
    $profile = $client->verifyIdToken($credential);
    if (!is_array($profile)) throw new RuntimeException('Invalid Google token.');
    $audience = (string)($profile['aud'] ?? '');
    $issuer = (string)($profile['iss'] ?? '');
    $verified = in_array((string)($profile['email_verified'] ?? ''), ['true', '1'], true);
    $expires = (int)($profile['exp'] ?? 0);
    if ($audience !== GOOGLE_CLIENT_ID || !in_array($issuer, ['accounts.google.com', 'https://accounts.google.com'], true) || !$verified || $expires <= time()) {
        throw new RuntimeException('Google token verification failed.');
    }

    $providerId = trim((string)($profile['sub'] ?? ''));
    $email = strtolower(trim((string)($profile['email'] ?? '')));
    $name = trim((string)($profile['name'] ?? ''));
    if ($providerId === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Google profile is incomplete.');
    if ($name === '') $name = $email;

    $conn->begin_transaction();

    // First, the exact Google identity. This makes repeated Google login deterministic.
    $stmt = $conn->prepare("SELECT u.* FROM auth_identities ai JOIN users u ON u.id=ai.user_id WHERE ai.provider='google' AND ai.provider_user_id=? LIMIT 1 FOR UPDATE");
    $stmt->bind_param('s', $providerId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && ($user['role'] ?? '') !== 'user') {
        $conn->rollback();
        jsonResponse('error', 'Google sign-in is available for customer accounts only. Staff and owner accounts must use password sign-in.', [], 403);
    }

    if (!$user) {
        // Then match by the verified Google email so the same person cannot create a duplicate customer record.
        $stmt = $conn->prepare('SELECT * FROM users WHERE email=? LIMIT 1 FOR UPDATE');
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $existing = $stmt->get_result()->fetch_assoc();

        if ($existing) {
            if (!str_ends_with($email, '@gmail.com') && empty($profile['hd'])) {
                $conn->rollback();
                jsonResponse('error', 'Use your password to sign in to this existing account. Automatic linking requires a Google-hosted email.', [], 403);
            }
            // Google auto-linking is customer-only. Staff/owner accounts must keep deliberate password/admin provisioning.
            if (($existing['role'] ?? '') !== 'user') {
                $conn->rollback();
                jsonResponse('error', 'This email belongs to a staff account. Please use the normal password sign-in for staff access.', [], 403);
            }
            if (($existing['account_status'] ?? '') === 'suspended') {
                $conn->rollback();
                jsonResponse('error', 'This account is suspended.', [], 403);
            }

            $userId = (int)$existing['id'];
            $link = $conn->prepare("INSERT INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'google',?)");
            $link->bind_param('is', $userId, $providerId);
            try {
                $link->execute();
            } catch (mysqli_sql_exception $e) {
                if ((int)$e->getCode() !== 1062) throw $e;
                // A duplicate provider constraint means Google is already linked somewhere; never create another account.
                $find = $conn->prepare("SELECT u.* FROM auth_identities ai JOIN users u ON u.id=ai.user_id WHERE ai.provider='google' AND ai.provider_user_id=? LIMIT 1");
                $find->bind_param('s', $providerId);
                $find->execute();
                $linked = $find->get_result()->fetch_assoc();
                if (!$linked || (int)$linked['id'] !== $userId) throw new RuntimeException('Google identity is already linked to another account.');
            }
            $activate = $conn->prepare("UPDATE users SET email_verified_at=COALESCE(email_verified_at,NOW()), account_status=CASE WHEN account_status='pending_verification' THEN 'active' ELSE account_status END WHERE id=?");
            $activate->bind_param('i', $userId);
            $activate->execute();
            $user = $existing;
            $user['account_status'] = $existing['account_status'] === 'pending_verification' ? 'active' : $existing['account_status'];
        } else {
            $role = 'user';
            $status = 'active';
            $create = $conn->prepare('INSERT INTO users(full_name,email,role,account_status,email_verified_at) VALUES (?,?,?,?,NOW())');
            $create->bind_param('ssss', $name, $email, $role, $status);
            $create->execute();
            $userId = (int)$conn->insert_id;
            $link = $conn->prepare("INSERT INTO auth_identities(user_id,provider,provider_user_id) VALUES (?,'google',?)");
            $link->bind_param('is', $userId, $providerId);
            $link->execute();
            audit($conn, 'customer_registered_google', 'user', $userId, ['email' => $email]);
            $user = ['id'=>$userId,'full_name'=>$name,'email'=>$email,'phone_number'=>null,'role'=>'user','account_status'=>'active'];
        }
    }

    $touch = $conn->prepare('UPDATE users SET last_login_at=NOW() WHERE id=?');
    $uid = (int)$user['id'];
    $touch->bind_param('i', $uid);
    $touch->execute();
    $conn->commit();

    $result = createLoginSession($user);
    if ($result['status'] === 'error') jsonResponse('error', $result['message'], [], 403);
    jsonResponse('success', 'Google sign-in successful.', ['redirect' => $result['redirect']]);
} catch (mysqli_sql_exception $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Google auth database error: ' . $e->getMessage());
    if ((int)$e->getCode() === 1062) jsonResponse('error', 'That Google account or email is already linked to an existing account. Please sign in instead.', [], 409);
    jsonResponse('error', 'Google sign-in could not be completed.', [], 500);
} catch (Throwable $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('Google auth: ' . $e->getMessage());
    jsonResponse('error', 'Google sign-in could not be completed.', [], 401);
}
