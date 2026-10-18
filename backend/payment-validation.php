<?php
declare(strict_types=1);
// Pure validation functions: no database, sessions, or external requests.
function validPaymongoSignature(string $raw, string $header, string $secret, bool $live, int $now): bool
{
    if ($raw === '' || $secret === '') return false;
    $parts = [];
    foreach (explode(',', $header) as $part) {
        [$key,$value] = array_pad(explode('=', trim($part), 2), 2, '');
        $parts[$key] = $value;
    }
    $t = $parts['t'] ?? '';
    if (!ctype_digit($t) || abs($now - (int)$t) > 600) return false;
    return hash_equals(hash_hmac('sha256', $t.'.'.$raw, $secret), $parts[$live ? 'li' : 'te'] ?? '');
}
function parsePaidCheckout(array $body, bool $live): ?array
{
    $event = $body['data'] ?? [];
    $attrs = ($event['type'] ?? '') === 'event' ? ($event['attributes'] ?? []) : $event;
    if (($attrs['livemode'] ?? null) !== $live) throw new RuntimeException('Wrong payment mode.');
    if (($attrs['type'] ?? '') !== 'checkout_session.payment.paid') return null;
    $session = $attrs['data'] ?? [];
    $details = $session['attributes'] ?? [];
    if (empty($session['id']) || !preg_match('/^OMG-BOOKING-([1-9][0-9]*)$/', (string)($details['reference_number'] ?? ''), $match)) {
        throw new RuntimeException('Invalid checkout identity.');
    }
    $paid = array_values(array_filter($details['payments'] ?? [], fn($p) => ($p['attributes']['status'] ?? '') === 'paid'));
    if (count($paid) !== 1 || empty($paid[0]['id'])) throw new RuntimeException('Missing unique paid payment.');
    $payment = $paid[0]; $p = $payment['attributes'];
    if (($p['currency'] ?? '') !== 'PHP' || !is_int($p['amount'] ?? null) || $p['amount'] <= 0) {
        throw new RuntimeException('Invalid payment currency or amount.');
    }
    return ['booking_id'=>(int)$match[1], 'session_id'=>(string)$session['id'],
        'payment_id'=>(string)$payment['id'], 'amount'=>$p['amount'],
        'method'=>(string)($p['source']['type'] ?? ''), 'reference'=>$details['reference_number'],
        // Some documented envelopes omit an event ID. Payment ID is a stable fallback.
        'event_key'=>(string)($event['id'] ?? ('payment:'.$payment['id']))];
}

function paymongoLineItems(array $rows, int $bookingCents, int $paymentCents): array
{
    $items = []; $sum = 0;
    foreach ($rows as $row) {
        $cents = (int)round((float)$row['unit_price'] * 100);
        $quantity = (int)$row['quantity'];
        if ($cents <= 0 || $quantity < 1 || $cents * $quantity !== (int)round((float)$row['line_total'] * 100)) {
            throw new RuntimeException('Invalid booking item price.');
        }
        $sum += $cents * $quantity;
        $items[] = ['name'=>$row['service_name'],'amount'=>$cents,'quantity'=>$quantity,'currency'=>'PHP'];
    }
    if (!$items || $sum !== $bookingCents || $sum !== $paymentCents) {
        throw new RuntimeException('Booking total does not match payment.');
    }
    return $items;
}
