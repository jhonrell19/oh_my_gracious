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

$selected_date = $_GET["date"] ?? date("Y-m-d");

$booked_times = [];

$stmt = $conn->prepare("
    SELECT booking_time 
    FROM bookings 
    WHERE booking_date = ? 
    AND status IN ('Pending', 'Approved')
");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $booked_times[] = substr($row["booking_time"], 0, 5);
}

$time_slots = [
    "10:00" => "10:00 AM",
    "11:00" => "11:00 AM",
    "12:00" => "12:00 PM",
    "13:00" => "1:00 PM",
    "14:00" => "2:00 PM",
    "15:00" => "3:00 PM",
    "16:00" => "4:00 PM",
    "17:00" => "5:00 PM",
    "18:00" => "6:00 PM",
    "19:00" => "7:00 PM",
    "20:00" => "8:00 PM",
    "21:00" => "9:00 PM"
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Book Appointment</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">Book Appointment</div>
  <nav>
    <a href="user-dashboard.php">Dashboard</a>
    <a href="booking.php">Book</a>
    <a href="booking-history.php">History</a>
    <a href="ar-3d-nails.php">3D AR Nails</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="form-container">
  <h2>Book Your Nail Appointment</h2>

  <div class="payment-box">
    <h3>Salon Schedule</h3>
    <p><strong>Open:</strong> 10:00 AM</p>
    <p><strong>Close:</strong> 10:00 PM</p>
    <p><strong>Payment:</strong> Physical payment at the salon only.</p>
    <p>Note: Booked time slots are disabled until the admin marks the booking as Completed or Rejected.</p>
  </div>

  <form method="GET" action="booking.php">
    <label>Choose Date First</label>
    <input type="date" name="date" value="<?php echo htmlspecialchars($selected_date); ?>" required>
    <button type="submit" class="btn full secondary">Check Available Times</button>
  </form>

  <hr>

  <form action="backend/create-booking.php" method="POST">

    <input type="hidden" name="booking_date" value="<?php echo htmlspecialchars($selected_date); ?>">

    <label>Full Name</label>
    <input type="text" name="full_name" value="<?php echo htmlspecialchars($_SESSION["full_name"]); ?>" required>

    <label>Service Type</label>
    <select name="service_type" id="serviceType" onchange="updateAmount()" required>
      <option value="">Select service</option>
      <option value="Manicure" data-price="250">Manicure - ₱250</option>
      <option value="Pedicure" data-price="300">Pedicure - ₱300</option>
      <option value="Gel Polish" data-price="500">Gel Polish - ₱500</option>
      <option value="Nail Extension" data-price="800">Nail Extension - ₱800</option>
      <option value="Nail Art" data-price="600">Nail Art - ₱600</option>
    </select>

    <label>Amount</label>
    <input type="text" id="amountDisplay" value="₱0" readonly>
    <input type="hidden" name="amount" id="amountInput" value="0">

    <label>Selected Date</label>
    <input type="text" value="<?php echo htmlspecialchars($selected_date); ?>" readonly>

    <label>Available Time</label>
    <select name="booking_time" required>
      <option value="">Select available time</option>

      <?php foreach ($time_slots as $value => $label) { ?>
        <?php if (in_array($value, $booked_times)) { ?>
          <option value="<?php echo $value; ?>" disabled>
            <?php echo $label; ?> - Already Booked
          </option>
        <?php } else { ?>
          <option value="<?php echo $value; ?>">
            <?php echo $label; ?> - Available
          </option>
        <?php } ?>
      <?php } ?>
    </select>

    <label>Payment Method</label>
    <input type="text" value="Physical Payment at Salon" readonly>

    <label>Additional Notes</label>
    <textarea name="notes" placeholder="Example: pink nails, short length, flower design"></textarea>

    <button type="submit" class="btn full">Submit Booking Request</button>
  </form>
</main>

<script>
function updateAmount() {
  const serviceSelect = document.getElementById("serviceType");
  const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
  const price = selectedOption.getAttribute("data-price") || 0;

  document.getElementById("amountDisplay").value = "₱" + price;
  document.getElementById("amountInput").value = price;
}
</script>

</body>
</html>