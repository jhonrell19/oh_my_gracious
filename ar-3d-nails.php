<?php
require_once __DIR__ . '/backend/authorization.php';
require_once __DIR__ . '/backend/csrf.php';
requireCustomer();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="csrf-token" content="<?= h(csrfToken()) ?>">
  <title>AI AR Nail Try-On | Oh My Gracious</title>
  <link rel="stylesheet" href="css/style.css">
</head>
<body>
<header class="navbar">
  <div class="logo">Oh My Gracious</div>
  <nav>
    <a href="user-dashboard.php">Dashboard</a>
    <a href="booking.php">Book</a>
    <a href="booking-history.php">History</a>
    <a class="active" href="ar-3d-nails.php">AR Try-On</a>
    <a href="saved-designs.php">Saved Designs</a>
    <a href="account.php">My Account</a>
    <a href="logout.php" class="logout-btn">Logout</a>
  </nav>
</header>

<main class="page-wrap">
  <div class="page-heading">
    <div>
      <p class="eyebrow">AI NAIL SEGMENTATION + HAND TRACKING</p>
      <h1>Real-Time AR Nail Try-On</h1>
      <p>
        The AI model detects actual nail pixels while hand landmarks keep the mask attached during movement.
        For best results, use bright front lighting, keep the back of one hand facing the camera, and spread your fingers slightly.
      </p>
    </div>
  </div>

  <div class="ar-layout">
    <section class="panel ar-stage-panel">
      <div class="ar-ai-status" id="aiStatusCard">
        <span class="ar-status-dot loading" id="aiStatusDot"></span>
        <div>
          <strong id="aiStatus">Loading AI nail model…</strong>
          <small id="aiMetrics">The model is about 46 MB on first load.</small>
        </div>
      </div>

      <div class="ar-model-progress" id="modelProgressWrap">
        <div class="ar-model-progress-bar" id="modelProgressBar"></div>
      </div>

      <div class="ar-stage">
        <video id="inputVideo" autoplay playsinline muted></video>
        <canvas id="threeCanvas"></canvas>
        <div class="ar-guide" id="arGuide">Keep nails visible, steady, and well-lit</div>
      </div>

      <div class="button-row">
        <button class="btn primary" id="startBtn">Start Camera</button>
        <button class="btn ghost" id="captureBtn" disabled>Capture Preview</button>
      </div>

      <div class="ar-live-stats">
        <span id="trackingStatus">Camera off</span>
        <span id="nailCount">0 nails masked</span>
        <span id="inferenceTime">AI: — ms</span>
      </div>

      <img id="capturedImage" class="captured-image hidden" alt="Captured AR preview">
    </section>

    <aside class="panel controls-panel">
      <h2>Customize</h2>

      <label for="designName">Design Name</label>
      <input id="designName" value="My AR Nail Design" maxlength="120">

      <label for="nailColor">Nail Color</label>
      <input type="color" id="nailColor" value="#d98a9b">

      <label for="nailFinish">Nail Finish</label>
      <select id="nailFinish">
        <option value="natural">Natural Polish</option>
        <option value="glossy" selected>Glossy</option>
        <option value="chrome">Chrome</option>
        <option value="matte">Matte</option>
        <option value="pearl">Pearl</option>
      </select>

      <label for="polishStrength">Polish Strength <span id="strengthValue">82%</span></label>
      <input type="range" id="polishStrength" min="35" max="100" step="1" value="82">

      <div class="control-divider"><span>AI Mask Tuning</span></div>

      <label for="detectionConfidence">Detection Confidence <span id="confidenceValue">20%</span></label>
      <input type="range" id="detectionConfidence" min="10" max="60" step="1" value="20">
      <p class="field-help">Lower this slightly if nails are missed. Very low values may detect skin or objects.</p>

      <label for="maskThreshold">Mask Coverage <span id="thresholdValue">42%</span></label>
      <input type="range" id="maskThreshold" min="28" max="70" step="1" value="42">
      <p class="field-help">Lower values cover more edge pixels; higher values make the nail boundary stricter.</p>

      <label for="coverageBoost">Edge Coverage Boost <span id="boostValue">1 px</span></label>
      <input type="range" id="coverageBoost" min="0" max="4" step="1" value="1">

      <label for="edgeSoftness">Edge Softness <span id="softnessValue">1.2 px</span></label>
      <input type="range" id="edgeSoftness" min="0" max="3" step="0.2" value="1.2">

      <label for="showMask">Diagnostic View</label>
      <select id="showMask">
        <option value="no">Polish Preview</option>
        <option value="yes">Show AI Mask in Green</option>
      </select>

      <details class="ar-advanced-controls">
        <summary>Landmark fallback controls</summary>
        <p class="field-help">The AI mask is preferred. These controls are only used if the model has not produced a usable mask yet.</p>

        <label for="nailShape">Fallback Nail Shape</label>
        <select id="nailShape">
          <option value="almond">Almond</option>
          <option value="round">Round</option>
          <option value="square">Square</option>
          <option value="coffin">Coffin</option>
          <option value="stiletto">Stiletto</option>
        </select>

        <label for="nailSize">Fallback Nail Size</label>
        <input type="range" id="nailSize" min="0.65" max="1.7" step="0.05" value="1.0">

        <label for="verticalOffset">Fallback Vertical Calibration</label>
        <input type="range" id="verticalOffset" min="-60" max="60" step="1" value="0">

        <label for="nailDepth">Fallback Position / Lift</label>
        <input type="range" id="nailDepth" min="-25" max="50" step="1" value="0">

        <label class="check-row">
          <input type="checkbox" id="useFallback">
          <span>Use landmark fallback when AI has no usable nail mask</span>
        </label>

        <label for="showDebug">Debug Hand Landmarks</label>
        <select id="showDebug">
          <option value="no">Off</option>
          <option value="yes">On</option>
        </select>
      </details>

      <button class="btn primary full" id="saveBtn">Save Design to My Account</button>
      <div id="arMessage" class="alert hidden"></div>

      <p class="field-help">
        The AI mask follows the nail pixels detected by the supplied segmentation model. Coverage can still vary with lighting,
        focus, nail length, polish already on the nail, and camera quality.
      </p>
    </aside>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/onnxruntime-web@1.20.1/dist/ort.all.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/camera_utils/camera_utils.js"></script>
<script src="https://cdn.jsdelivr.net/npm/@mediapipe/hands/hands.js"></script>
<script src="js/ar-3d-nails.js?v=3100"></script>
</body>
</html>
