<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/csrf.php';
verifyCsrf();

$fullName = trim((string)($_POST['full_name'] ?? ''));
$email = strtolower(trim((string)($_POST['email'] ?? '')));
$phone = normalizePhilippinePhone((string)($_POST['phone_number'] ?? ''));
$password = (string)($_POST['password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
$dob = trim((string)($_POST['date_of_birth'] ?? ''));
$techIdRaw = trim((string)($_POST['preferred_technician_id'] ?? ''));
$nailPreferences = trim((string)($_POST['nail_preferences'] ?? ''));
$allergyNotes = trim((string)($_POST['allergy_notes'] ?? ''));

if ($fullName === '' || $email === '' || $phone === null || $password === '' || $confirm === '') {
    jsonResponse('error', 'Please complete all required registration fields.', [], 422);
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) jsonResponse('error', 'Please enter a valid email address.', [], 422);
if ($password !== $confirm) jsonResponse('error', 'The passwords do not match.', [], 422);
if (strlen($password) < 8 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
    jsonResponse('error', 'Password must have at least 8 characters, uppercase, lowercase, a number, and a special character.', [], 422);
}

$dobValue = null;
if ($dob !== '') {
    $date = DateTime::createFromFormat('Y-m-d', $dob);
    if (!$date || $date->format('Y-m-d') !== $dob || $date > new DateTime('today')) jsonResponse('error', 'Invalid date of birth.', [], 422);
    $dobValue = $dob;
}

$techId = null;
if ($techIdRaw !== '') {
    if (!ctype_digit($techIdRaw)) jsonResponse('error', 'Invalid technician.', [], 422);
    $techId = (int)$techIdRaw;
    $stmt = $conn->prepare("SELECT id FROM technicians WHERE id=? AND status='active'");
    $stmt->bind_param('i', $techId);
    $stmt->execute();
    if ($stmt->get_result()->num_rows !== 1) jsonResponse('error', 'Selected technician is unavailable.', [], 422);
}

$check = $conn->prepare('SELECT id FROM users WHERE email=? OR phone_number=? LIMIT 1');
$check->bind_param('ss', $email, $phone);
$check->execute();
if ($check->get_result()->num_rows > 0) jsonResponse('error', 'An account already exists with that email or phone number.', [], 409);

$passwordHash = password_hash($password, PASSWORD_DEFAULT);
$role = 'user';
$status = 'pending_verification';
$rawToken = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $rawToken);
$expires = date('Y-m-d H:i:s', time() + 3600);

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare('INSERT INTO users (full_name,email,phone_number,password_hash,date_of_birth,preferred_technician_id,nail_preferences,allergy_notes,role,account_status) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->bind_param('sssssissss', $fullName, $email, $phone, $passwordHash, $dobValue, $techId, $nailPreferences, $allergyNotes, $role, $status);
    $stmt->execute();
    $userId = (int)$conn->insert_id;

    $id = $conn->prepare("INSERT INTO auth_identities (user_id,provider,provider_user_id) VALUES (?,'password',?)");
    $id->bind_param('is', $userId, $email);
    $id->execute();

    $tok = $conn->prepare('INSERT INTO email_verification_tokens (user_id,token_hash,expires_at) VALUES (?,?,?)');
    $tok->bind_param('iss', $userId, $tokenHash, $expires);
    $tok->execute();
    audit($conn, 'customer_registered', 'user', $userId, ['email' => $email]);
    $conn->commit();

    $verificationUrl = appUrl('backend/verify-email.php?token=' . urlencode($rawToken));
    $extra = [];
    if (APP_ENV !== 'production') $extra['verification_url'] = $verificationUrl;
    else {
        $subject = 'Verify your Oh My Gracious account';
        $message = "Welcome to Oh My Gracious. Verify your account here:\n\n" . $verificationUrl;
        @mail($email, $subject, $message, 'From: ' . env('MAIL_FROM', 'no-reply@localhost'));
    }
    jsonResponse('success', 'Account created. Please verify your email before signing in.', $extra);
} catch (Throwable $e) {
    $conn->rollback();
    error_log('Registration error: ' . $e->getMessage());
    jsonResponse('error', 'Registration could not be completed.', [], 500);
}
