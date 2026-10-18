<?php

declare(strict_types=1);
require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/helpers.php';

if (APP_ENV === 'production') {
    http_response_code(404);
    exit;
}

$tables = [
    'users','technicians','services','bookings','booking_items','booking_time_slots',
    'booking_slot_inventory','booking_slots','payments','saved_designs','auth_identities',
    'email_verification_tokens','password_reset_tokens','system_settings','audit_logs','login_attempts',
    'payment_checkout_requests','payment_webhook_events'
];
$tableResults = [];
$stmt = $conn->prepare('SELECT COUNT(*) total FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
foreach ($tables as $table) {
    $stmt->bind_param('s', $table);
    $stmt->execute();
    $tableResults[$table] = (int)$stmt->get_result()->fetch_assoc()['total'] === 1;
}

$requiredColumns = [
    'services' => ['category','booking_availability','unavailable_reason','duration_minutes','price_max','pricing_type'],
    'bookings' => ['phone_number','booking_source','total_duration_minutes','amount_is_estimate'],
    'booking_items' => ['quantity','line_total'],
    'users' => ['password_hash','role','account_status','last_login_at'],
];
$columnResults = [];
$colStmt = $conn->prepare('SELECT COUNT(*) total FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
foreach ($requiredColumns as $table => $columns) {
    foreach ($columns as $column) {
        $colStmt->bind_param('ss', $table, $column);
        $colStmt->execute();
        $columnResults[$table . '.' . $column] = (int)$colStmt->get_result()->fetch_assoc()['total'] === 1;
    }
}

$googleUnique = false;
$idxStmt = $conn->prepare("SELECT COUNT(*) total FROM information_schema.statistics WHERE table_schema=DATABASE() AND table_name='auth_identities' AND index_name='uq_user_provider'");
$idxStmt->execute();
$googleUnique = (int)$idxStmt->get_result()->fetch_assoc()['total'] > 0;

$slotCount = 0;
if ($tableResults['booking_time_slots']) {
    $r = $conn->query("SELECT COUNT(*) total FROM booking_time_slots WHERE status='active'");
    $slotCount = (int)$r->fetch_assoc()['total'];
}

$realCatalogCount = 0;
if ($tableResults['services']) {
    $r = $conn->query("SELECT COUNT(*) total FROM services WHERE status='active'");
    $realCatalogCount = (int)$r->fetch_assoc()['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>OMG Health Check</title>
  <link rel="stylesheet" href="../css/style.css">
</head>
<body>
<main class="page-wrap narrow">
  <h1>Local System Health Check</h1>
  <p>This page only works while <code>APP_ENV</code> is not production.</p>

  <section class="panel">
    <h2>PHP</h2>
    <p>PHP <?=h(PHP_VERSION)?></p>
    <p>cURL: <?=function_exists('curl_init')?'✅ Enabled':'❌ Missing (enable extension=curl in php.ini)'?></p>
    <p>Sessions: <?=session_status()===PHP_SESSION_ACTIVE?'✅ Working':'⚠️ Not active'?></p>
  </section>

  <section class="panel">
    <h2>Database Tables</h2>
    <?php foreach ($tableResults as $name => $ok): ?>
      <p><?=$ok?'✅':'❌'?> <?=h($name)?></p>
    <?php endforeach; ?>
  </section>

  <section class="panel">
    <h2>Required Schema</h2>
    <?php foreach ($columnResults as $name => $ok): ?>
      <p><?=$ok?'✅':'❌'?> <?=h($name)?></p>
    <?php endforeach; ?>
    <p><?=$googleUnique?'✅':'❌'?> Google identity duplicate guard</p>
    <p><?=$slotCount > 0?'✅':'❌'?> Active booking times: <?=h((string)$slotCount)?></p>
    <p><?=$realCatalogCount >= 80?'✅':'⚠️'?> Active salon services: <?=h((string)$realCatalogCount)?></p>
  </section>

  <section class="panel">
    <h2>Authentication</h2>
    <p>Email/password: ✅ Built in</p>
    <p>Google customer login/register: <?=GOOGLE_CLIENT_ID!==''?'✅ Configured':'○ Not configured'?></p>
    <p>Google duplicate account protection: ✅ Database + server checks</p>
    <p>Facebook login/register: Removed</p>
    <p>Phone/SMS login/register: Removed (phone remains a contact field)</p>
  </section>

  <section class="panel">
    <h2>Payments</h2>
    <p>Pay at Salon / Cash: ✅ Enabled</p>
    <p>Online gateway: ○ Intentionally deferred until operational testing is complete</p>
  </section>
</main>
</body>
</html>
