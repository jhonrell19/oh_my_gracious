<?php

declare(strict_types=1);
require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/booking-helper.php';
require_once __DIR__ . '/paymongo-helper.php';

requireCustomer();
verifyCsrf();

$userId = (int)$_SESSION['user_id'];
$date = trim((string)($_POST['booking_date'] ?? ''));
$time = trim((string)($_POST['booking_time'] ?? ''));
$serviceIds = $_POST['service_ids'] ?? [];
$notes = trim((string)($_POST['notes'] ?? ''));
$paymentMethod = trim((string)($_POST['payment_method'] ?? 'Cash'));
$designIdRaw = trim((string)($_POST['selected_design_id'] ?? ''));

if (!is_array($serviceIds) || !$serviceIds || $date === '' || $time === '') {
    redirectTo('booking.php?error=' . urlencode('Complete all booking fields.'));
}
if (!validBookingDate($conn, $date)) {
    redirectTo('booking.php?error=' . urlencode('Choose a valid booking date.'));
}
if (!in_array($paymentMethod, ['Cash', 'PayMongo'], true)) {
    $paymentMethod = 'Cash';
}
if ($paymentMethod === 'PayMongo' && !paymongoEnabled()) {
    redirectTo('booking.php?error=' . urlencode('Online payment is not configured yet. Please choose Cash at Salon.'));
}

$services = loadServicesByIds($conn, $serviceIds);
if (count($services) !== count(array_unique(array_map('intval', $serviceIds)))) {
    redirectTo('booking.php?error=' . urlencode('One or more services are unavailable.'));
}
$totals = bookingTotals($services);
$available = array_column(candidateStartTimes($conn, $date, $totals['duration']), 'value');
if (!in_array(substr($time, 0, 5), $available, true)) {
    redirectTo('booking.php?date=' . urlencode($date) . '&error=' . urlencode('That time is no longer available.'));
}

$stmt = $conn->prepare('SELECT full_name,email FROM users WHERE id=?');
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
if (!$profile) redirectTo('logout.php');

$designId = null;
if ($designIdRaw !== '' && ctype_digit($designIdRaw)) {
    $d = (int)$designIdRaw;
    $s = $conn->prepare('SELECT id FROM saved_designs WHERE id=? AND user_id=?');
    $s->bind_param('ii', $d, $userId);
    $s->execute();
    if ($s->get_result()->num_rows === 1) $designId = $d;
}

$paymentStatus = $paymentMethod === 'PayMongo' ? 'Pending Online Payment' : 'To Pay';
$gateway = $paymentMethod === 'PayMongo' ? 'paymongo' : 'manual';
$status = 'Pending';
$serviceSummary = implode(', ', array_column($services, 'service_name'));
$interval = max(15, (int)getSetting($conn, 'slot_interval_minutes', '30'));
$slots = requiredSlotTimes($time, $totals['duration'], $interval);

try {
    $conn->begin_transaction();

    $b = $conn->prepare(
        'INSERT INTO bookings(user_id,full_name,email,service_type,booking_date,booking_time,total_duration_minutes,amount,payment_method,payment_status,payment_reference,notes,selected_design_id,status)
         VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );
    $reference = '';
    $b->bind_param(
        'isssssidssssis',
        $userId,
        $profile['full_name'],
        $profile['email'],
        $serviceSummary,
        $date,
        $time,
        $totals['duration'],
        $totals['amount'],
        $paymentMethod,
        $paymentStatus,
        $reference,
        $notes,
        $designId,
        $status
    );
    $b->execute();
    $bookingId = (int)$conn->insert_id;

    foreach ($services as $service) {
        $i = $conn->prepare('INSERT INTO booking_items(booking_id,service_id,service_name,unit_price,duration_minutes) VALUES (?,?,?,?,?)');
        $sid = (int)$service['id'];
        $price = (float)$service['price'];
        $dur = (int)$service['duration_minutes'];
        $i->bind_param('iisdi', $bookingId, $sid, $service['service_name'], $price, $dur);
        $i->execute();
    }

    foreach ($slots as $slot) {
        $s = $conn->prepare('INSERT INTO booking_slots(booking_id,slot_date,slot_time) VALUES (?,?,?)');
        $s->bind_param('iss', $bookingId, $date, $slot);
        $s->execute();
    }

    $p = $conn->prepare('INSERT INTO payments(booking_id,method,status,amount,reference_number,gateway) VALUES (?,?,?,?,?,?)');
    $p->bind_param('issdss', $bookingId, $paymentMethod, $paymentStatus, $totals['amount'], $reference, $gateway);
    $p->execute();

    audit($conn, 'booking_created', 'booking', $bookingId, [
        'services' => $serviceSummary,
        'date' => $date,
        'time' => $time,
        'payment' => $paymentMethod,
    ]);
    $conn->commit();

    if ($paymentMethod === 'PayMongo') {
        try {
            $url = createPaymongoCheckoutForBooking($conn, $bookingId, $userId);
            header('Location: ' . $url);
            exit;
        } catch (Throwable $checkoutError) {
            error_log('Initial PayMongo checkout: ' . $checkoutError->getMessage());
            redirectTo('booking-history.php?created=1&payment_error=' . urlencode('Appointment reserved, but online checkout could not start. Use “Pay Online” to try again.'));
        }
    }

    redirectTo('booking-history.php?created=1');
} catch (mysqli_sql_exception $e) {
    try { $conn->rollback(); } catch (Throwable $ignored) {}
    if ((int)$e->getCode() === 1062) {
        redirectTo('booking.php?date=' . urlencode($date) . '&error=' . urlencode('That time was just booked by another customer. Choose another slot.'));
    }
    error_log('Booking create: ' . $e->getMessage());
    redirectTo('booking.php?error=' . urlencode('Booking could not be created.'));
}
