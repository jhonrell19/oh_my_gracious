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
  <title>Real AR Nail Try-On</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">Real AR Nail Try-On</div>
  <nav>
    <a href="user-dashboard.php">Dashboard</a>
    <a href="booking.php">Book</a>
    <a href="booking-history.php">History</a>
    <a href="ar-camera.php">AR Camera</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="ar-camera-page">
  <h1>Real AR Nail Try-On</h1>
  <p>Show your hand to the camera. The system will detect your fingertips and place nails on them.</p>

  <div class="ar-camera-layout">

    <section class="camera-section">
      <div class="camera-wrapper">
        <video id="video" autoplay playsinline></video>
        <canvas id="outputCanvas"></canvas>
      </div>

      <div class="camera-buttons">
        <button class="btn" id="startCameraBtn">Start Camera</button>
        <button class="btn secondary" id="captureBtn">Capture Preview</button>
      </div>

      <img id="capturedImage" class="captured-image hidden" alt="Captured AR Preview">
    </section>

    <section class="customizer-panel">
      <h2>Nail Customization</h2>

      <label>Nail Color</label>
      <input type="color" id="nailColor" value="#ff4f9a">

      <label>Nail Shape</label>
      <select id="nailShape">
        <option value="round">Round</option>
        <option value="square">Square</option>
        <option value="almond">Almond</option>
        <option value="coffin">Coffin</option>
        <option value="stiletto">Stiletto</option>
      </select>

      <label>Nail Design</label>
      <select id="nailDesign">
        <option value="plain">Plain</option>
        <option value="french">French Tip</option>
        <option value="glitter">Glitter</option>
        <option value="chrome">Chrome</option>
        <option value="flower">Flower Art</option>
      </select>

      <label>Nail Size</label>
      <input type="range" id="nailSize" min="0.7" max="2.0" step="0.1" value="1.1">

      <label>Vertical Adjustment</label>
      <input type="range" id="verticalAdjust" min="-40" max="40" step="1" value="-8">

      <button class="btn full" id="saveDesignBtn">Save AR Design</button>

      <p class="password-rule">
        Tip: Use bright lighting, show only one hand, and spread your fingers slightly.
      </p>
    </section>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js"></script>
<script src="js/ar-camera.js"></script>

</body>
</html>