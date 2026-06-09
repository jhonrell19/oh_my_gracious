<?php
session_start();

if (!isset($_SESSION["user_id"])) {
    header("Location: login.html");
    exit;
}

if ($_SESSION["role"] !== "user") {
    header("Location: admin-dashboard.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>User Dashboard</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

  <header class="navbar">
    <div class="logo">User Dashboard</div>
    <nav>
  <a href="user-dashboard.php">Dashboard</a>
  <a href="booking.php">Book</a>
  <a href="booking-history.php">History</a>
  <a href="ar-3d-nails.php">3D AR Nails</a>
  <a href="logout.php" class="logout-btn">Logout</a>
</nav>
  </header>

  <main class="dashboard">
    <h1>Welcome, <?php echo htmlspecialchars($_SESSION["full_name"]); ?></h1>
    <p>You are logged in as a user.</p>

    <div class="cards">
      <div class="card">
        <h3>Book Appointment</h3>
        <p>Booking page will be added next.</p>
      </div>

      <div class="cards">
  <a href="booking.php" class="card">
    <h3>Book Appointment</h3>
    <p>Choose a nail service and pay using test GCash or PayPal.</p>
  </a>

<a href="ar-3d-nails.php" class="card">
  <h3>3D AR Nail Try-On</h3>
  <p>Use real-time hand tracking and 3D nail objects on your fingers.</p>
</a>

  <a href="booking-history.php" class="card">
    <h3>Booking History</h3>
    <p>View your pending, approved, rejected, and completed bookings.</p>
  </a>
</div>
    </div>
  </main>

</body>
</html>