<?php
session_start();
include "backend/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: admin-login.html");
    exit;
}

if ($_SESSION["role"] !== "admin") {
    header("Location: user-dashboard.php");
    exit;
}

$bookings = $conn->query("SELECT * FROM bookings ORDER BY booking_date DESC, booking_time DESC");

$total = $conn->query("SELECT COUNT(*) AS total FROM bookings")->fetch_assoc()["total"];
$pending = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Pending'")->fetch_assoc()["total"];
$approved = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Approved'")->fetch_assoc()["total"];
$completed = $conn->query("SELECT COUNT(*) AS total FROM bookings WHERE status = 'Completed'")->fetch_assoc()["total"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Bookings</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">Admin Bookings</div>
  <nav>
    <a href="admin-dashboard.php">Dashboard</a>
    <a href="admin-bookings.php">Bookings</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="dashboard">
  <h1>Booking Requests</h1>
  <p>Salon schedule is 10:00 AM to 10:00 PM. Payment is physical only.</p>

  <div class="cards">
    <div class="card">
      <h3>Total Bookings</h3>
      <p><?php echo $total; ?> total</p>
    </div>

    <div class="card">
      <h3>Pending</h3>
      <p><?php echo $pending; ?> pending</p>
    </div>

    <div class="card">
      <h3>Approved</h3>
      <p><?php echo $approved; ?> approved</p>
    </div>

    <div class="card">
      <h3>Completed</h3>
      <p><?php echo $completed; ?> completed</p>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th>Customer</th>
        <th>Email</th>
        <th>Service</th>
        <th>Date</th>
        <th>Time</th>
        <th>Payment</th>
        <th>Payment Status</th>
        <th>Amount</th>
        <th>Booking Status</th>
        <th>Action</th>
      </tr>
    </thead>

    <tbody>
      <?php if ($bookings->num_rows > 0) { ?>
        <?php while ($row = $bookings->fetch_assoc()) { ?>
          <tr>
            <td><?php echo htmlspecialchars($row["full_name"]); ?></td>
            <td><?php echo htmlspecialchars($row["email"]); ?></td>
            <td><?php echo htmlspecialchars($row["service_type"]); ?></td>
            <td><?php echo htmlspecialchars($row["booking_date"]); ?></td>
            <td><?php echo date("g:i A", strtotime($row["booking_time"])); ?></td>
            <td><?php echo htmlspecialchars($row["payment_method"]); ?></td>
            <td>
              <span class="status topay">
                <?php echo htmlspecialchars($row["payment_status"]); ?>
              </span>
            </td>
            <td>₱<?php echo htmlspecialchars($row["amount"]); ?></td>
            <td>
              <span class="status <?php echo strtolower($row["status"]); ?>">
                <?php echo htmlspecialchars($row["status"]); ?>
              </span>
            </td>
            <td>
              <form action="backend/update-booking-status.php" method="POST" class="action-form">
                <input type="hidden" name="booking_id" value="<?php echo $row["id"]; ?>">
                <input type="hidden" name="status" value="Approved">
                <button type="submit" class="small-btn approve">Approve</button>
              </form>

              <form action="backend/update-booking-status.php" method="POST" class="action-form">
                <input type="hidden" name="booking_id" value="<?php echo $row["id"]; ?>">
                <input type="hidden" name="status" value="Rejected">
                <button type="submit" class="small-btn reject">Reject</button>
              </form>

              <form action="backend/update-booking-status.php" method="POST" class="action-form">
                <input type="hidden" name="booking_id" value="<?php echo $row["id"]; ?>">
                <input type="hidden" name="status" value="Completed">
                <button type="submit" class="small-btn complete">Done</button>
              </form>
            </td>
          </tr>
        <?php } ?>
      <?php } else { ?>
        <tr>
          <td colspan="10">No booking requests yet.</td>
        </tr>
      <?php } ?>
    </tbody>
  </table>
</main>

</body>
</html>