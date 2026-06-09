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
$payment_method = trim($_POST["payment_method"] ?? "");
$payment_reference = trim($_POST["payment_reference"] ?? "");
$amount = $_POST["amount"] ?? 0;
$notes = trim($_POST["notes"] ?? "");

if (
    $full_name === "" ||
    $service_type === "" ||
    $booking_date === "" ||
    $booking_time === "" ||
    $payment_method === "" ||
    $payment_reference === "" ||
    $amount <= 0
) {
    echo "<script>
        alert('Please complete all booking fields.');
        window.location.href='../booking.php';
    </script>";
    exit;
}

if ($payment_method !== "GCash" && $payment_method !== "PayPal") {
    echo "<script>
        alert('Invalid payment method.');
        window.location.href='../booking.php';
    </script>";
    exit;
}

$payment_status = "Paid";
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
    $booking_time,
    $payment_method,
    $payment_status,
    $payment_reference,
    $amount,
    $notes,
    $status
);

if ($stmt->execute()) {
    echo "<script>
        alert('Booking submitted successfully! Payment successful. Please wait for admin approval.');
        window.location.href='../booking-history.php';
    </script>";
} else {
    echo "<script>
        alert('Booking failed. Please try again.');
        window.location.href='../booking.php';
    </script>";
}

$stmt->close();
$conn->close();
?>