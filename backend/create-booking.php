<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login.html");
    exit;
}

if ($_SESSION["role"] !== "user") {
    header("Location: ../admin-dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];
$email = $_SESSION["email"];

$full_name = trim($_POST["full_name"] ?? "");
$service_type = trim($_POST["service_type"] ?? "");
$booking_date = trim($_POST["booking_date"] ?? "");
$booking_time = trim($_POST["booking_time"] ?? "");
$amount = $_POST["amount"] ?? 0;
$notes = trim($_POST["notes"] ?? "");

if (
    $full_name === "" ||
    $service_type === "" ||
    $booking_date === "" ||
    $booking_time === "" ||
    $amount <= 0
) {
    echo "<script>
        alert('Please complete all booking fields.');
        window.location.href='../booking.php';
    </script>";
    exit;
}

$allowed_times = [
    "10:00",
    "11:00",
    "12:00",
    "13:00",
    "14:00",
    "15:00",
    "16:00",
    "17:00",
    "18:00",
    "19:00",
    "20:00",
    "21:00"
];

$booking_time_short = substr($booking_time, 0, 5);

if (!in_array($booking_time_short, $allowed_times)) {
    echo "<script>
        alert('Invalid booking time. Please choose between 10:00 AM and 9:00 PM.');
        window.location.href='../booking.php?date=" . $booking_date . "';
    </script>";
    exit;
}

/*
  This prevents double booking.
  If another user already picked the same date and time,
  and that booking is still Pending or Approved,
  the new booking will be blocked.
*/
$check = $conn->prepare("
    SELECT id 
    FROM bookings 
    WHERE booking_date = ? 
    AND TIME_FORMAT(booking_time, '%H:%i') = ? 
    AND status IN ('Pending', 'Approved')
");
$check->bind_param("ss", $booking_date, $booking_time_short);
$check->execute();
$check_result = $check->get_result();

if ($check_result->num_rows > 0) {
    echo "<script>
        alert('This time slot is already booked. Please choose another available time.');
        window.location.href='../booking.php?date=" . $booking_date . "';
    </script>";
    exit;
}

$payment_method = "Physical";
$payment_status = "To Pay";
$payment_reference = null;
$status = "Pending";

$stmt = $conn->prepare("
    INSERT INTO bookings 
    (user_id, full_name, email, service_type, booking_date, booking_time, payment_method, payment_status, payment_reference, amount, notes, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "issssssssdss",
    $user_id,
    $full_name,
    $email,
    $service_type,
    $booking_date,
    $booking_time_short,
    $payment_method,
    $payment_status,
    $payment_reference,
    $amount,
    $notes,
    $status
);

if ($stmt->execute()) {
    echo "<script>
        alert('Booking submitted successfully! Payment will be made physically at the salon.');
        window.location.href='../booking-history.php';
    </script>";
} else {
    echo "<script>
        alert('Booking failed. Please try again.');
        window.location.href='../booking.php?date=" . $booking_date . "';
    </script>";
}

$stmt->close();
$conn->close();
?>