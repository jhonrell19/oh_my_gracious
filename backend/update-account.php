<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';

requireCustomer();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectTo('account.php');
}

verifyCsrf();

$userId = (int) $_SESSION['user_id'];

$full = trim((string) ($_POST['full_name'] ?? ''));
$phone = normalizePhilippinePhone(
    (string) ($_POST['phone_number'] ?? '')
);

$dob = trim((string) ($_POST['date_of_birth'] ?? ''));
$techRaw = trim(
    (string) ($_POST['preferred_technician_id'] ?? '')
);

$prefs = trim(
    (string) ($_POST['nail_preferences'] ?? '')
);

$allergies = trim(
    (string) ($_POST['allergy_notes'] ?? '')
);


/*
|--------------------------------------------------------------------------
| Validate required fields
|--------------------------------------------------------------------------
*/

if ($full === '' || $phone === null) {
    redirectTo(
        'account.php?error=' .
        urlencode('Full name and a valid phone number are required.')
    );
}


/*
|--------------------------------------------------------------------------
| Validate date of birth
|--------------------------------------------------------------------------
*/

$dobValue = null;

if ($dob !== '') {

    $date = DateTime::createFromFormat('Y-m-d', $dob);

    if (
        !$date ||
        $date->format('Y-m-d') !== $dob ||
        $date > new DateTime('today')
    ) {
        redirectTo(
            'account.php?error=' .
            urlencode('Invalid date of birth.')
        );
    }

    $dobValue = $dob;
}


/*
|--------------------------------------------------------------------------
| Validate preferred technician
|--------------------------------------------------------------------------
*/

$tech = null;

if ($techRaw !== '' && ctype_digit($techRaw)) {

    $tech = (int) $techRaw;

    $stmt = $conn->prepare(
        "SELECT id
         FROM technicians
         WHERE id = ?
         AND status = 'active'
         LIMIT 1"
    );

    $stmt->bind_param('i', $tech);
    $stmt->execute();

    if ($stmt->get_result()->num_rows !== 1) {
        $tech = null;
    }

    $stmt->close();
}


/*
|--------------------------------------------------------------------------
| Check duplicate phone number
|--------------------------------------------------------------------------
*/

$duplicate = $conn->prepare(
    "SELECT id
     FROM users
     WHERE phone_number = ?
     AND id <> ?
     LIMIT 1"
);

$duplicate->bind_param(
    'si',
    $phone,
    $userId
);

$duplicate->execute();

if ($duplicate->get_result()->num_rows > 0) {

    $duplicate->close();

    redirectTo(
        'account.php?error=' .
        urlencode('That phone number is already used by another account.')
    );
}

$duplicate->close();


/*
|--------------------------------------------------------------------------
| Update customer account
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare(
    "UPDATE users
     SET
        full_name = ?,
        phone_number = ?,
        date_of_birth = ?,
        preferred_technician_id = ?,
        nail_preferences = ?,
        allergy_notes = ?
     WHERE id = ?"
);

$stmt->bind_param(
    'sssissi',
    $full,
    $phone,
    $dobValue,
    $tech,
    $prefs,
    $allergies,
    $userId
);

$stmt->execute();
$stmt->close();


/*
|--------------------------------------------------------------------------
| Update session
|--------------------------------------------------------------------------
*/

$_SESSION['full_name'] = $full;


/*
|--------------------------------------------------------------------------
| Audit log
|--------------------------------------------------------------------------
*/

audit(
    $conn,
    'profile_updated',
    'user',
    $userId
);


/*
|--------------------------------------------------------------------------
| Return to account page
|--------------------------------------------------------------------------
*/

redirectTo('account.php?saved=1');