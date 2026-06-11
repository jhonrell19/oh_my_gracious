<?php
session_start();
include "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../admin-login.html");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: ../user-dashboard.php");
    exit;
}

$booking_id = $_POST["booking_id"] ?? "";
$status = $_POST["status"] ?? "";

$allowed_status = ["Pending", "Approved", "Rejected", "Completed"];

if ($booking_id === "" || !in_array($status, $allowed_status)) {
    echo "<script>
        alert('Invalid booking update.');
        window.location.href='../admin-bookings.php';
    </script>";
    exit;
}

/*
  If the admin marks the booking as Completed,
  the payment is considered physically paid at the salon.
*/
if ($status === "Completed") {
    $stmt = $conn->prepare("UPDATE bookings SET status = ?, payment_status = 'Paid' WHERE id = ?");
} else {
    $stmt = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
}

$stmt->bind_param("si", $status, $booking_id);

if ($stmt->execute()) {
    echo "<script>
        alert('Booking status updated successfully.');
        window.location.href='../admin-bookings.php';
    </script>";
} else {
    echo "<script>
        alert('Failed to update booking status.');
        window.location.href='../admin-bookings.php';
    </script>";
}

$stmt->close();
$conn->close();
?>