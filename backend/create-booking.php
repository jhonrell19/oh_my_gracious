<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/booking-helper.php';

requireCustomer();
verifyCsrf();

$userId = (int)$_SESSION['user_id'];
$date = trim((string)($_POST['booking_date'] ?? ''));
$time = normalizeSlotTime((string)($_POST['booking_time'] ?? ''));
$serviceIds = $_POST['service_ids'] ?? [];
$quantityRaw = $_POST['service_quantities'] ?? [];
$notes = trim((string)($_POST['notes'] ?? ''));
$designIdRaw = trim((string)($_POST['selected_design_id'] ?? ''));

if (!is_array($serviceIds) || !$serviceIds || $date === '' || $time === '') {
    redirectTo('booking.php?error=' . urlencode('Complete all booking fields.'));
}

if (!is_array($quantityRaw)) {
    $quantityRaw = [];
}

if (!validBookingDate($conn, $date)) {
    redirectTo('booking.php?error=' . urlencode('Choose a valid booking date.'));
}

$stmt = $conn->prepare(
    'SELECT full_name,email,phone_number
     FROM users
     WHERE id=? AND role="user" AND account_status="active"'
);
$stmt->bind_param('i', $userId);
$stmt->execute();
$profile = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$profile) {
    redirectTo('logout.php');
}

if (empty($profile['phone_number'])) {
    redirectTo(
        'account.php?complete=1&error=' .
        urlencode('Add a contact number before booking.')
    );
}

$designId = null;
if ($designIdRaw !== '' && ctype_digit($designIdRaw)) {
    $candidate = (int)$designIdRaw;
    $designStmt = $conn->prepare(
        'SELECT id FROM saved_designs WHERE id=? AND user_id=?'
    );
    $designStmt->bind_param('ii', $candidate, $userId);
    $designStmt->execute();
    if ($designStmt->get_result()->num_rows === 1) {
        $designId = $candidate;
    }
    $designStmt->close();
}

$status = 'Pending';
$paymentMethod = 'Cash';
$paymentStatus = 'To Pay';
$reference = '';
$source = 'customer';

try {
    $conn->begin_transaction();

    $services = loadServicesByIds($conn, $serviceIds, true);
    $uniqueIds = array_values(array_unique(array_map('intval', $serviceIds)));

    if (
        count($services) !== count($uniqueIds) ||
        !selectedServicesAreBookable($services)
    ) {
        throw new RuntimeException('One or more selected services are unavailable.');
    }

    $quantities = normalizeServiceQuantities($services, $quantityRaw);
    $totals = bookingTotals($services, $quantities);
    $summary = bookingServiceSummary($services, $quantities);

    $duration = (int)$totals['duration'];
    $amount = (float)$totals['amount'];
    $amountIsEstimate = !empty($totals['estimated']) ? 1 : 0;

    $bookingStmt = $conn->prepare(
        'INSERT INTO bookings(
            user_id,full_name,email,phone_number,service_type,
            booking_date,booking_time,total_duration_minutes,amount,
            amount_is_estimate,payment_method,payment_status,payment_reference,
            notes,selected_design_id,status,booking_source
        ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
    );

    $bookingStmt->bind_param(
        'issssssidissssiss',
        $userId,
        $profile['full_name'],
        $profile['email'],
        $profile['phone_number'],
        $summary,
        $date,
        $time,
        $duration,
        $amount,
        $amountIsEstimate,
        $paymentMethod,
        $paymentStatus,
        $reference,
        $notes,
        $designId,
        $status,
        $source
    );
    $bookingStmt->execute();
    $bookingId = (int)$conn->insert_id;
    $bookingStmt->close();

    foreach ($services as $service) {
        $serviceId = (int)$service['id'];
        $price = (float)$service['price'];
        $quantity = (int)($quantities[$serviceId] ?? 1);
        $lineTotal = $price * $quantity;
        $serviceDuration = (int)$service['duration_minutes'];

        $itemStmt = $conn->prepare(
            'INSERT INTO booking_items(
                booking_id,service_id,service_name,unit_price,quantity,line_total,duration_minutes
             ) VALUES (?,?,?,?,?,?,?)'
        );
        $itemStmt->bind_param(
            'iisdidi',
            $bookingId,
            $serviceId,
            $service['service_name'],
            $price,
            $quantity,
            $lineTotal,
            $serviceDuration
        );
        $itemStmt->execute();
        $itemStmt->close();
    }

    reserveCapacitySlot($conn, $date, $time, $bookingId);

    $paymentStmt = $conn->prepare(
        "INSERT INTO payments(booking_id,method,status,amount,reference_number,gateway)
         VALUES (?,'Cash','To Pay',?,?,'manual')"
    );
    $paymentStmt->bind_param('ids', $bookingId, $amount, $reference);
    $paymentStmt->execute();
    $paymentStmt->close();

    audit(
        $conn,
        'booking_created',
        'booking',
        $bookingId,
        [
            'services' => $summary,
            'date' => $date,
            'time' => $time,
            'source' => $source,
            'estimated_amount' => $amountIsEstimate === 1,
        ]
    );

    $conn->commit();
    redirectTo('booking-history.php?created=1');
} catch (Throwable $e) {
    try {
        $conn->rollback();
    } catch (Throwable $ignored) {
    }

    error_log('Booking create: ' . $e->getMessage());

    $lower = strtolower($e->getMessage());
    $message = (
        str_contains($lower, 'fully booked') ||
        str_contains($lower, 'closed')
    )
        ? $e->getMessage()
        : 'Booking could not be created.';

    redirectTo(
        'booking.php?date=' . urlencode($date) .
        '&error=' . urlencode($message)
    );
}
