<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/payment-validation.php';

function paymongoMethodTypes(): array
{
    $allowed = ['card', 'gcash', 'paymaya', 'qrph', 'grab_pay', 'shopee_pay'];
    $configured = array_map('trim', explode(',', PAYMONGO_PAYMENT_METHODS));
    $methods = array_values(array_intersect($configured, $allowed));
    if (!$methods) throw new RuntimeException('No supported payment methods configured.');
    return $methods;
}

function createPaymongoCheckoutForBooking(mysqli $conn, int $bookingId, int $userId): string
{
    $l=$conn->query("SELECT GET_LOCK('omg_payment_config',10) ok")->fetch_assoc();
    if((int)$l['ok']!==1)throw new RuntimeException('Payment settings are busy. Try again shortly.');
    try {
        $current=savedPaymentConfig();
        if($current && ($current['key']!==PAYMONGO_SECRET_KEY || $current['webhook']!==PAYMONGO_WEBHOOK_SECRET || !$current['enabled']))throw new RuntimeException('Payment settings changed. Reload the page.');
        return createPaymongoCheckoutLocked($conn,$bookingId,$userId);
    } finally {$conn->query("SELECT RELEASE_LOCK('omg_payment_config')");}
}
function createPaymongoCheckoutLocked(mysqli $conn, int $bookingId, int $userId): string
{
    if (!paymongoEnabled()) throw new RuntimeException('Online payment is not configured.');
    // Serialize checkout calls across tabs and PHP workers, including the API request.
    $lockName = 'omg-checkout-' . $bookingId;
    $lock = $conn->prepare('SELECT GET_LOCK(?, 5) acquired');
    $lock->bind_param('s', $lockName); $lock->execute();
    if ((int)$lock->get_result()->fetch_assoc()['acquired'] !== 1) {
        throw new RuntimeException('Checkout is already being prepared.');
    }
    try {
        $conn->begin_transaction();
        $s = $conn->prepare('SELECT * FROM bookings WHERE id=? AND user_id=? FOR UPDATE');
        $s->bind_param('ii', $bookingId, $userId); $s->execute();
        $b = $s->get_result()->fetch_assoc();
        if (!$b || !in_array($b['status'], ['Pending','Approved'], true)
            || $b['booking_date'] < date('Y-m-d') || !empty($b['amount_is_estimate'])) {
            throw new RuntimeException('This appointment is not eligible for online payment.');
        }
        $s = $conn->prepare('SELECT * FROM payments WHERE booking_id=? FOR UPDATE');
        $s->bind_param('i', $bookingId); $s->execute(); $p = $s->get_result()->fetch_assoc();
        if (!$p || !in_array($p['status'], ['To Pay','Pending Online Payment'], true)) {
            throw new RuntimeException('This payment cannot be collected.');
        }
        $s = $conn->prepare('SELECT *, TIMESTAMPDIFF(SECOND,created_at,NOW()) age_seconds FROM payment_checkout_requests WHERE booking_id=?');
        $s->bind_param('i', $bookingId); $s->execute(); $request = $s->get_result()->fetch_assoc();
        if ($request && $request['checkout_url']) {
            $conn->commit();
            // Never replace a payable session just because time has passed.
            return (string)$request['checkout_url'];
        }
        if ($request && (int)$request['age_seconds'] >= 23 * 3600) {
            // Provider idempotency retention is 24h. An ambiguous old request needs
            // reconciliation in the provider dashboard, not another possible charge.
            throw new RuntimeException('Checkout needs owner reconciliation before retrying.');
        }
        if (!$request) {
            $s = $conn->prepare('SELECT service_name,unit_price,quantity,line_total FROM booking_items WHERE booking_id=? ORDER BY id');
            $s->bind_param('i', $bookingId); $s->execute(); $rows = $s->get_result();
            $items = paymongoLineItems($rows->fetch_all(MYSQLI_ASSOC),
                (int)round((float)$b['amount']*100), (int)round((float)$p['amount']*100));
            $reference = 'OMG-BOOKING-' . $bookingId;
            $payload = ['data'=>['attributes'=>[
                'line_items'=>$items, 'payment_method_types'=>paymongoMethodTypes(),
                'success_url'=>appUrl('payment-return.php?booking_id='.$bookingId.'&result=success'),
                'cancel_url'=>appUrl('payment-return.php?booking_id='.$bookingId.'&result=cancelled'),
                'reference_number'=>$reference, 'send_email_receipt'=>true, 'pass_on_fees'=>false,
                'metadata'=>['booking_id'=>(string)$bookingId,'user_id'=>(string)$userId],
                'billing'=>['name'=>$b['full_name'],'email'=>$b['email']],
            ]]];
            $key = bin2hex(random_bytes(24));
            $json = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $s = $conn->prepare('INSERT INTO payment_checkout_requests(booking_id,request_key,request_json) VALUES (?,?,?)');
            $s->bind_param('iss', $bookingId, $key, $json); $s->execute();
            $request = ['request_key'=>$key,'request_json'=>$json];
            $s = $conn->prepare("UPDATE payments SET gateway='paymongo',method='PayMongo',status='Pending Online Payment',reference_number=? WHERE booking_id=?");
            $s->bind_param('si', $reference, $bookingId); $s->execute();
            $s = $conn->prepare("UPDATE bookings SET payment_method='PayMongo',payment_status='Pending Online Payment',payment_reference=? WHERE id=?");
            $s->bind_param('si', $reference, $bookingId); $s->execute();
        }
        // Persist request and key before any network call. Retry uses identical body/key.
        $conn->commit();
        $response = httpPostJson('https://api.paymongo.com/v2/checkout_sessions',
            json_decode($request['request_json'], true, 512, JSON_THROW_ON_ERROR), [],
            [PAYMONGO_SECRET_KEY, ''], $request['request_key']);
        $data = $response['data'] ?? [];
        $session = (string)($data['id'] ?? '');
        $url = (string)($data['attributes']['checkout_url'] ?? '');
        $host = parse_url($url, PHP_URL_HOST);
        if ($session === '' || parse_url($url, PHP_URL_SCHEME) !== 'https'
            || !is_string($host) || !($host === 'paymongo.com' || str_ends_with($host, '.paymongo.com'))) {
            throw new RuntimeException('Invalid checkout response.');
        }
        $s = $conn->prepare('UPDATE payment_checkout_requests SET session_id=?,checkout_url=? WHERE booking_id=?');
        $s->bind_param('ssi', $session, $url, $bookingId); $s->execute();
        audit($conn, 'paymongo_checkout_created', 'booking', $bookingId, ['session_id'=>$session]);
        return $url;
    } catch (Throwable $e) {
        try { $conn->rollback(); } catch (Throwable $ignored) {}
        throw $e;
    } finally {
        $s = $conn->prepare('SELECT RELEASE_LOCK(?)'); $s->bind_param('s', $lockName); $s->execute();
    }
}

function friendlyPaymongoMethod(string $sourceType): string
{
    return match ($sourceType) {
        'gcash'=>'GCash', 'paymaya'=>'Maya', 'card'=>'Card', 'qrph'=>'QRPh',
        'grab_pay'=>'GrabPay', 'shopee_pay','shopeepay'=>'ShopeePay', default=>'PayMongo',
    };
}
