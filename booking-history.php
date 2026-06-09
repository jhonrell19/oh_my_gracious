<?php
session_start();
include "backend/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

if ($_SESSION["role"] !== "user") {
    header("Location: admin-dashboard.php");
    exit;
}

$user_id = $_SESSION["user_id"];

$stmt = $conn->prepare("SELECT * FROM bookings WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$bookings = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Booking History</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">Booking History</div>
  <nav>
    <a href="user-dashboard.php">Dashboard</a>
    <a href="booking.php">Book</a>
    <a href="booking-history.php">History</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="dashboard">
  <h1>Your Booking History</h1>

  <table>
    <thead>
      <tr>
        <th>Service</th>
        <th>Date</th>
        <th>Time</th>
        <th>Payment</th>
        <th>Payment Status</th>
        <th>Amount</th>
        <th>Reference</th>
        <th>Booking Status</th>
      </tr>
    </thead>

    <tbody>
      <?php if ($bookings->num_rows > 0) { ?>
        <?php while ($row = $bookings->fetch_assoc()) { ?>
          <tr>
            <td><?php echo htmlspecialchars($row["service_type"]); ?></td>
            <td><?php echo htmlspecialchars($row["booking_date"]); ?></td>
            <td><?php echo htmlspecialchars($row["booking_time"]); ?></td>
            <td><?php echo htmlspecialchars($row["payment_method"]); ?></td>
            <td>
              <span class="status paid">
                <?php echo htmlspecialchars($row["payment_status"]); ?>
              </span>
            </td>
            <td>₱<?php echo htmlspecialchars($row["amount"]); ?></td>
            <td><?php echo htmlspecialchars($row["payment_reference"]); ?></td>
            <td>
              <span class="status <?php echo strtolower($row["status"]); ?>">
                <?php echo htmlspecialchars($row["status"]); ?>
              </span>
            </td>
          </tr>
        <?php } ?>
      <?php } else { ?>
        <tr>
          <td colspan="8">No bookings yet.</td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</main>

</body>
</html>