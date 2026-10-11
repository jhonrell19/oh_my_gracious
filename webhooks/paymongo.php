<?php

declare(strict_types=1);

require_once __DIR__ . '/../backend/db.php';
require_once __DIR__ . '/../backend/helpers.php';
require_once __DIR__ . '/../backend/paymongo-helper.php';

header('Content-Type: application/json; charset=utf-8');

$raw = file_get_contents('php://input') ?: '';
$signatureHeader = (string)($_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '');
if (PAYMONGO_WEBHOOK_SECRET === '' || $signatureHeader === '' || $raw === '') {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

$parts = [];
foreach (explode(',', $signatureHeader) as $part) {
    [$key, $value] = array_pad(explode('=', trim($part), 2), 2, '');
    if ($key !== '') $parts[$key] = $value;
}
$timestamp = $parts['t'] ?? '';
if ($timestamp === '' || !ctype_digit($timestamp) || abs(time() - (int)$timestamp) > 600) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}
$expected = hash_hmac('sha256', $timestamp . '.' . $raw, PAYMONGO_WEBHOOK_SECRET);
$provided = ($parts['li'] ?? '') !== '' ? $parts['li'] : ($parts['te'] ?? '');
if ($provided === '' || !hash_equals($expected, $provided)) {
    http_response_code(401);
    echo json_encode(['ok'=>false]);
    exit;
}

$body = json_decode($raw, true);
if (!is_array($body)) {
    http_response_code(400);
    echo json_encode(['ok'=>false]);
    exit;
}

// PayMongo has used more than one event envelope over time. Accept both documented shapes.
$eventData = $body['data'] ?? [];
$eventType = (string)($eventData['type'] ?? '');
if ($eventType === 'event' || $eventType === '') {
    $eventType = (string)($eventData['attributes']['type'] ?? $body['type'] ?? '');
}
if ($eventType !== 'checkout_session.payment.paid') {
    http_response_code(200);
    echo json_encode(['ok'=>true,'ignored'=>true]);
    exit;
}

$session = $eventData['data'] ?? ($eventData['attributes']['data'] ?? []);
$attributes = $session['attributes'] ?? [];
$sessionId = (string)($session['id'] ?? '');
$reference = (string)($attributes['reference_number'] ?? '');
if (!preg_match('/^OMG-BOOKING-(\d+)$/', $reference, $m)) {
    http_response_code(200);
    echo json_encode(['ok'=>true,'ignored'=>true]);
    exit;
}
$bookingId = (int)$m[1];
$payments = $attributes['payments'] ?? [];
$latest = is_array($payments) && $payments ? end($payments) : [];
$paymentId = (string)($latest['id'] ?? '');
$payAttrs = is_array($latest) ? ($latest['attributes'] ?? []) : [];
$sourceType = (string)($payAttrs['source']['type'] ?? '');
$method = friendlyPaymongoMethod($sourceType);

try {
    $conn->begin_transaction();
    $stmt = $conn->prepare(
        "SELECT p.status AS payment_status, b.status AS booking_status
         FROM payments p
         JOIN bookings b ON b.id=p.booking_id
         WHERE p.booking_id=? AND p.gateway='paymongo'
         FOR UPDATE"
    );
    $stmt->bind_param('i', $bookingId);
    $stmt->execute();
    $payment = $stmt->get_result()->fetch_assoc();
    if (!$payment) {
        $conn->rollback();
        http_response_code(200);
        echo json_encode(['ok'=>true,'ignored'=>true]);
        exit;
    }

    // A hosted checkout can be completed after the customer has already cancelled or
    // front desk has rejected the appointment. Never count that as normal salon revenue.
    // Flag it for owner reconciliation/refund instead of silently reopening the booking.
    if (in_array((string)$payment['booking_status'], ['Cancelled', 'Rejected'], true)) {
        if ((string)$payment['payment_status'] !== 'Refund Required') {
            $u = $conn->prepare(
                "UPDATE payments
                 SET method=?,status='Refund Required',paid_at=NOW(),
                     gateway_session_id=COALESCE(NULLIF(gateway_session_id,''),?),
                     gateway_payment_id=?
                 WHERE booking_id=?"
            );
            $u->bind_param('sssi', $method, $sessionId, $paymentId, $bookingId);
            $u->execute();

            $b = $conn->prepare(
                "UPDATE bookings
                 SET payment_method=?,payment_status='Refund Required',payment_reference=?
                 WHERE id=?"
            );
            $b->bind_param('ssi', $method, $reference, $bookingId);
            $b->execute();

            audit($conn, 'paymongo_payment_refund_required', 'booking', $bookingId, [
                'session_id'=>$sessionId,
                'payment_id'=>$paymentId,
                'method'=>$method,
                'booking_status'=>$payment['booking_status'],
            ]);
        }
    } elseif ((string)$payment['payment_status'] !== 'Paid') {
        $u = $conn->prepare("UPDATE payments SET method=?,status='Paid',paid_at=NOW(),gateway_session_id=COALESCE(NULLIF(gateway_session_id,''),?),gateway_payment_id=? WHERE booking_id=?");
        $u->bind_param('sssi', $method, $sessionId, $paymentId, $bookingId);
        $u->execute();
        $b = $conn->prepare("UPDATE bookings SET payment_method=?,payment_status='Paid',payment_reference=? WHERE id=?");
        $b->bind_param('ssi', $method, $reference, $bookingId);
        $b->execute();
        audit($conn, 'paymongo_payment_paid', 'booking', $bookingId, ['session_id'=>$sessionId,'payment_id'=>$paymentId,'method'=>$method]);
    }
    $conn->commit();
    http_response_code(200);
    echo json_encode(['ok'=>true]);
} catch (Throwable $e) {
    if ($conn->errno === 0) { /* keep static analyzers quiet */ }
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    error_log('PayMongo webhook: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['ok'=>false]);
}
