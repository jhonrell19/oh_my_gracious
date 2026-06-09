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
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="form-container">
  <h2>Book Your Nail Appointment</h2>

  <form action="backend/create-booking.php" method="POST" onsubmit="return fakePaymentProcess()">

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

    <label>Preferred Date</label>
    <input type="date" name="booking_date" required>

    <label>Preferred Time</label>
    <input type="time" name="booking_time" required>

    <label>Payment Method</label>
    <select name="payment_method" id="paymentMethod" onchange="showPaymentInfo()" required>
      <option value="">Select payment method</option>
      <option value="GCash">GCash</option>
      <option value="PayPal">PayPal</option>
    </select>

    <div id="paymentBox" class="payment-box hidden"></div>

    <label>Payment Reference Number</label>
    <input type="text" name="payment_reference" id="paymentReference" readonly required>

    <label>Additional Notes</label>
    <textarea name="notes" placeholder="Example: pink nails, short length, flower design"></textarea>

    <button type="submit" class="btn full">Pay and Submit Booking</button>
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

function showPaymentInfo() {
  const method = document.getElementById("paymentMethod").value;
  const paymentBox = document.getElementById("paymentBox");

  paymentBox.classList.remove("hidden");

  if (method === "GCash") {
    paymentBox.innerHTML = `
      <h3>GCash Test Payment</h3>
      <p>This is only a test payment.</p>
      <p><strong>GCash Number:</strong> 09XX-XXX-XXXX</p>
      <p><strong>Account Name:</strong> Oh My Gracious</p>
      <p>When you submit, the system will automatically say payment successful.</p>
    `;
  } else if (method === "PayPal") {
    paymentBox.innerHTML = `
      <h3>PayPal Test Payment</h3>
      <p>This is only a test payment.</p>
      <p><strong>PayPal Email:</strong> test-paypal@ohmygracious.com</p>
      <p>When you submit, the system will automatically say payment successful.</p>
    `;
  } else {
    paymentBox.classList.add("hidden");
    paymentBox.innerHTML = "";
  }
}

function fakePaymentProcess() {
  const amount = document.getElementById("amountInput").value;
  const method = document.getElementById("paymentMethod").value;
  const referenceInput = document.getElementById("paymentReference");

  if (amount <= 0) {
    alert("Please select a service first.");
    return false;
  }

  if (method === "") {
    alert("Please select a payment method.");
    return false;
  }

  alert("Processing " + method + " payment...");
  alert("Payment successful!");

  const fakeReference = method.toUpperCase() + "-" + Date.now();
  referenceInput.value = fakeReference;

  return true;
}
</script>

</body>
</html>