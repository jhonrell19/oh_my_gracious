<?php

declare(strict_types=1);

require_once __DIR__ . '/authorization.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/paymongo-helper.php';

requireCustomer();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }
verifyCsrf();

$bookingId = (int)($_POST['booking_id'] ?? 0);
if ($bookingId <= 0) redirectTo('booking-history.php?error=' . urlencode('Invalid booking.'));

try {
    $url = createPaymongoCheckoutForBooking($conn, $bookingId, (int)$_SESSION['user_id']);
    header('Location: ' . $url);
    exit;
} catch (Throwable $e) {
    error_log('PayMongo checkout: ' . $e->getMessage());
    redirectTo('booking-history.php?error=' . urlencode('Checkout could not be opened. Please retry from Booking History. If this persists, contact the salon; do not create another booking to retry payment.'));
}
