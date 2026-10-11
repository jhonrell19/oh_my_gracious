<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function paymongoMethodTypes(): array
{
    $allowed = ['card', 'gcash', 'paymaya', 'qrph', 'grab_pay', 'shopee_pay'];
    $configured = array_filter(array_map('trim', explode(',', (string)PAYMONGO_PAYMENT_METHODS)));
    $methods = array_values(array_intersect($configured, $allowed));
    return $methods ?: ['card', 'gcash', 'paymaya', 'qrph'];
}

function createPaymongoCheckoutForBooking(mysqli $conn, int $bookingId, int $userId): string
{
    if (!paymongoEnabled()) {
        throw new RuntimeException('PayMongo is not configured yet.');
    }

    $stmt = $conn->prepare(
        "SELECT b.id,b.full_name,b.email,b.amount,b.status,p.status payment_status,p.gateway_session_id,p.checkout_url,p.updated_at payment_updated_at
         FROM bookings b
         JOIN payments p ON p.booking_id=b.id
         WHERE b.id=? AND b.user_id=? AND p.gateway='paymongo'
         LIMIT 1"
    );
    $stmt->bind_param('ii', $bookingId, $userId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();
    if (!$booking) throw new RuntimeException('Online-payment booking was not found.');
    if ($booking['payment_status'] === 'Paid') throw new RuntimeException('This booking is already paid.');
    if (in_array($booking['status'], ['Rejected', 'Cancelled'], true)) throw new RuntimeException('This booking can no longer be paid.');

    // Reuse an existing checkout URL when present to reduce duplicate sessions.
    if (!empty($booking['checkout_url']) && !empty($booking['payment_updated_at']) && strtotime((string)$booking['payment_updated_at']) >= time()-1200) {
        return (string)$booking['checkout_url'];
    }

    $itemStmt = $conn->prepare('SELECT service_name,unit_price FROM booking_items WHERE booking_id=? ORDER BY id');
    $itemStmt->bind_param('i', $bookingId);
    $itemStmt->execute();
    $items = [];
    $result = $itemStmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = [
            'name' => (string)$row['service_name'],
            'amount' => (int)round(((float)$row['unit_price']) * 100),
            'currency' => 'PHP',
            'quantity' => 1,
        ];
    }
    if (!$items) throw new RuntimeException('This booking has no billable services.');

    $reference = 'OMG-BOOKING-' . $bookingId;
    $payload = [
        'data' => [
            'attributes' => [
                'line_items' => $items,
                'payment_method_types' => paymongoMethodTypes(),
                'success_url' => appUrl('payment-return.php?booking_id=' . $bookingId . '&result=success'),
                'cancel_url' => appUrl('payment-return.php?booking_id=' . $bookingId . '&result=cancelled'),
                'reference_number' => $reference,
                'send_email_receipt' => true,
                'metadata' => [
                    'booking_id' => (string)$bookingId,
                    'user_id' => (string)$userId,
                ],
                'billing' => [
                    'name' => (string)$booking['full_name'],
                    'email' => (string)$booking['email'],
                ],
            ],
        ],
    ];

    $response = httpPostJson(
        'https://api.paymongo.com/v2/checkout_sessions',
        $payload,
        [],
        [PAYMONGO_SECRET_KEY, ''],
        'oh-my-gracious-booking-' . $bookingId . '-' . intdiv(time(), 1200)
    );

    $data = $response['data'] ?? [];
    $sessionId = (string)($data['id'] ?? '');
    $url = (string)($data['attributes']['checkout_url'] ?? '');
    if ($sessionId === '' || $url === '') throw new RuntimeException('PayMongo did not return a checkout URL.');

    $update = $conn->prepare(
        "UPDATE payments
         SET gateway_session_id=?,checkout_url=?,reference_number=?,status='Pending Online Payment'
         WHERE booking_id=?"
    );
    $update->bind_param('sssi', $sessionId, $url, $reference, $bookingId);
    $update->execute();

    $sync = $conn->prepare("UPDATE bookings SET payment_reference=?,payment_status='Pending Online Payment' WHERE id=?");
    $sync->bind_param('si', $reference, $bookingId);
    $sync->execute();

    audit($conn, 'paymongo_checkout_created', 'booking', $bookingId, ['session_id' => $sessionId]);
    return $url;
}

function friendlyPaymongoMethod(string $sourceType): string
{
    return match ($sourceType) {
        'gcash' => 'GCash',
        'paymaya' => 'Maya',
        'card' => 'Card',
        'qrph' => 'QRPh',
        'grab_pay' => 'GrabPay',
        'shopee_pay', 'shopeepay' => 'ShopeePay',
        default => 'PayMongo',
    };
}
