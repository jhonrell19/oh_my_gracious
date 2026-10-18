<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/config.php';
require_once __DIR__ . '/../backend/payment-validation.php';
header('Content-Type: application/json; charset=utf-8');
function respond(int $code, bool $ok): never { http_response_code($code); echo json_encode(['ok'=>$ok]); exit; }
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') respond(405, false);
$raw = file_get_contents('php://input', false, null, 0, 1048577) ?: '';
if (strlen($raw) > 1048576) respond(413, false);
$live = str_starts_with(PAYMONGO_SECRET_KEY, 'sk_live_');
if (PAYMONGO_SECRET_KEY === '' || !validPaymongoSignature($raw, (string)($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? ''), PAYMONGO_WEBHOOK_SECRET, $live, time())) respond(401, false);
try {
    $e = parsePaidCheckout(json_decode($raw, true, 512, JSON_THROW_ON_ERROR), $live);
} catch (Throwable $error) { respond(400, false); }
if ($e === null) respond(200, true);
define('OMG_SERVER_WEBHOOK', true);
require_once __DIR__ . '/../backend/paymongo-helper.php';
// Server-to-server notifications do not open a browser session.
try {
    $conn->begin_transaction();
    $s = $conn->prepare('SELECT id,status,amount FROM bookings WHERE id=? FOR UPDATE');
    $s->bind_param('i', $e['booking_id']); $s->execute(); $b = $s->get_result()->fetch_assoc();
    if (!$b) { $conn->rollback(); respond(200, true); }
    $s = $conn->prepare('SELECT * FROM payments WHERE booking_id=? FOR UPDATE');
    $s->bind_param('i', $e['booking_id']); $s->execute(); $p = $s->get_result()->fetch_assoc();
    $s = $conn->prepare('SELECT session_id FROM payment_checkout_requests WHERE booking_id=?');
    $s->bind_param('i', $e['booking_id']); $s->execute(); $request = $s->get_result()->fetch_assoc();
    if (!$request || !$request['session_id']) {
        // Delivery may race the checkout API response. Ask the provider to retry.
        $conn->rollback(); respond(503, false);
    }
    if (!$p || $p['gateway'] !== 'paymongo' || $request['session_id'] !== $e['session_id']
        || (int)round((float)$p['amount']*100) !== $e['amount']
        || (int)round((float)$b['amount']*100) !== $e['amount']) {
        throw new RuntimeException('Checkout/session/amount mismatch.');
    }
    $s = $conn->prepare('SELECT payment_id,booking_id FROM payment_webhook_events WHERE event_key=? OR payment_id=?');
    $s->bind_param('ss', $e['event_key'], $e['payment_id']); $s->execute(); $previous = $s->get_result()->fetch_assoc();
    if ($previous) {
        if ((int)$previous['booking_id'] !== $e['booking_id'] || $previous['payment_id'] !== $e['payment_id']) throw new RuntimeException('Event identity mismatch.');
        $conn->commit(); respond(200, true);
    }
    if (in_array($p['status'], ['Paid','Refund Required','Refunded'], true)) throw new RuntimeException('Unexpected additional payment.');
    $s = $conn->prepare('INSERT INTO payment_webhook_events(event_key,booking_id,session_id,payment_id,amount_centavos) VALUES (?,?,?,?,?)');
    $s->bind_param('sissi', $e['event_key'], $e['booking_id'], $e['session_id'], $e['payment_id'], $e['amount']); $s->execute();
    $status = in_array($b['status'], ['Cancelled','Rejected','No-show'], true) ? 'Refund Required' : 'Paid';
    $method = friendlyPaymongoMethod($e['method']);
    $s = $conn->prepare('UPDATE payments SET method=?,status=?,paid_at=NOW(),reference_number=? WHERE booking_id=?');
    $s->bind_param('sssi', $method, $status, $e['payment_id'], $e['booking_id']); $s->execute();
    $s = $conn->prepare('UPDATE bookings SET payment_method=?,payment_status=?,payment_reference=? WHERE id=?');
    $s->bind_param('sssi', $method, $status, $e['payment_id'], $e['booking_id']); $s->execute();
    audit($conn, 'paymongo_payment_confirmed', 'booking', $e['booking_id'], ['payment_id'=>$e['payment_id'],'status'=>$status]);
    $conn->commit(); respond(200, true);
} catch (Throwable $error) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('PayMongo webhook reconciliation failed: '.$error->getMessage());
    respond(500, false);
}
