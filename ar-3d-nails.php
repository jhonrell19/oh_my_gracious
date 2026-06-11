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
  <title>3D AR Nail Try-On</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>

<header class="navbar">
  <div class="logo">3D AR Nail Try-On</div>
  <p>Start the camera, show your hand, and nail polish will appear on your fingernails.</p>
  <nav>
    <a href="user-dashboard.php">Dashboard</a>
    <a href="booking.php">Book</a>
    <a href="booking-history.php">History</a>
    <a href="ar-3d-nails.php">3D AR Nails</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="ar-3d-page">
  <h1>3D AR Nail Try-On</h1>
  <p>Start the camera, show your hand, and 3D nails will attach to your fingertips.</p>

  <div class="ar-3d-layout">

    <section class="ar-3d-camera-section">
      <div class="ar-3d-camera-wrapper" id="arWrapper">
  <video id="inputVideo" autoplay playsinline></video>
  <canvas id="threeCanvas"></canvas>
</div>

      <div class="camera-buttons">
        <button class="btn" id="startBtn">Start Camera</button>
        <button class="btn secondary" id="captureBtn">Capture Preview</button>
      </div>

      <img id="capturedImage" class="captured-image hidden" alt="Captured 3D AR Preview">
    </section>

    <section class="customizer-panel">
      <h2>3D Nail Controls</h2>

      <label>Nail Color</label>
      <input type="color" id="nailColor" value="#ff4f9a">

      <label>Nail Shape</label>
      <select id="nailShape">
        <option value="almond">Almond</option>
        <option value="round">Round</option>
        <option value="square">Square</option>
        <option value="coffin">Coffin</option>
        <option value="stiletto">Stiletto</option>
      </select>

      <label>Nail Finish</label>
      <select id="nailFinish">
        <option value="glossy">Glossy</option>
        <option value="chrome">Chrome</option>
        <option value="matte">Matte</option>
        <option value="pearl">Pearl</option>
      </select>

      <label>Nail Size</label>
      <input type="range" id="nailSize" min="0.6" max="2.0" step="0.05" value="1.25">  

      <label>Vertical Position</label>
      <input type="range" id="verticalOffset" min="-80" max="80" step="1" value="0">

      <label>Depth / Lift</label>
      <input type="range" id="nailDepth" min="-30" max="80" step="1" value="25">

      <label>Show Debug Points</label>
      <select id="showDebug">
        <option value="no">No</option>
        <option value="yes">Yes</option>
      </select>

      <button class="btn full" id="saveBtn">Save 3D Design</button>

      <p class="password-rule">
        Best demo: bright light, plain background, one hand only, fingers slightly spread.
      </p>
    </section>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js"></script>
<script src="js/ar-3d-nails.js?v=999"></script>

</body>
</html>

