const video = document.getElementById("inputVideo");
const canvas = document.getElementById("threeCanvas");
const ctx = canvas.getContext("2d");

const startBtn = document.getElementById("startBtn");
const captureBtn = document.getElementById("captureBtn");
const saveBtn = document.getElementById("saveBtn");
const capturedImage = document.getElementById("capturedImage");

const nailColorInput = document.getElementById("nailColor");
const nailShapeInput = document.getElementById("nailShape");
const nailFinishInput = document.getElementById("nailFinish");
const nailSizeInput = document.getElementById("nailSize");
const verticalOffsetInput = document.getElementById("verticalOffset");
const showDebugInput = document.getElementById("showDebug");
const nailDepthInput = document.getElementById("nailDepth");
const designNameInput = document.getElementById("designName");
const arMessage = document.getElementById("arMessage");

let hands = null;
let camera = null;
let latestImage = null;

const fingers = [
  { name: "thumb", tip: 4, dip: 3, pip: 2, widthBoost: 1.45, heightBoost: 1.10, shift: 0.22 },
  { name: "index", tip: 8, dip: 7, pip: 6, widthBoost: 1.25, heightBoost: 1.15, shift: 0.25 },
  { name: "middle", tip: 12, dip: 11, pip: 10, widthBoost: 1.30, heightBoost: 1.20, shift: 0.25 },
  { name: "ring", tip: 16, dip: 15, pip: 14, widthBoost: 1.20, heightBoost: 1.15, shift: 0.25 },
  { name: "pinky", tip: 20, dip: 19, pip: 18, widthBoost: 1.05, heightBoost: 1.00, shift: 0.25 }
];

const smoothNails = new Array(5).fill(null).map(() => ({
  x: 0,
  y: 0,
  w: 0,
  h: 0,
  angle: 0,
  ready: false
}));

startBtn.addEventListener("click", startCamera);
captureBtn.addEventListener("click", capturePreview);
saveBtn.addEventListener("click", saveDesign);

function getSettings() {
  return {
    color: nailColorInput.value,
    shape: nailShapeInput.value,
    finish: nailFinishInput.value,
    size: parseFloat(nailSizeInput.value),
    verticalOffset: parseFloat(verticalOffsetInput.value),
    depth: parseFloat(nailDepthInput.value),
    showDebug: showDebugInput.value === "yes"
  };
}

async function startCamera() {
  try {
    if (hands !== null) {
      alert("Camera is already running.");
      return;
    }

    if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      alert("Camera access is not supported in this browser.");
      return;
    }

    if (typeof Hands === "undefined") {
      alert("MediaPipe Hands did not load. Check your internet connection.");
      return;
    }

    if (typeof Camera === "undefined") {
      alert("MediaPipe Camera Utils did not load. Check your internet connection.");
      return;
    }

    hands = new Hands({
      locateFile: function (file) {
        return "https://cdn.jsdelivr.net/npm/@mediapipe/hands/" + file;
      }
    });

    hands.setOptions({
      maxNumHands: 1,
      modelComplexity: 1,
      minDetectionConfidence: 0.75,
      minTrackingConfidence: 0.75
    });

    hands.onResults(onResults);

    camera = new Camera(video, {
      onFrame: async function () {
        await hands.send({ image: video });
      },
      width: 960,
      height: 720
    });

    await camera.start();

    console.log("Camera started successfully.");
  } catch (error) {
    console.error("Camera error:", error);
    alert("Camera failed: " + error.message);
  }
}

function onResults(results) {
  const image = results.image;

  canvas.width = image.width;
  canvas.height = image.height;

  ctx.save();
  ctx.clearRect(0, 0, canvas.width, canvas.height);

  ctx.translate(canvas.width, 0);
  ctx.scale(-1, 1);

  ctx.drawImage(image, 0, 0, canvas.width, canvas.height);

  if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
    drawNailPolish(results.multiHandLandmarks[0]);
  } else {
    drawNoHandMessage();
  }

  ctx.restore();

  latestImage = canvas.toDataURL("image/png");
}

function drawNailPolish(landmarks) {
  const settings = getSettings();

  fingers.forEach((finger, index) => {
    const tip = toCanvasPoint(landmarks[finger.tip]);
    const dip = toCanvasPoint(landmarks[finger.dip]);
    const pip = toCanvasPoint(landmarks[finger.pip]);

    const dx = tip.x - dip.x;
    const dy = tip.y - dip.y;

    const upperLength = Math.sqrt(dx * dx + dy * dy);

    if (upperLength < 5) {
      return;
    }

    const unitX = dx / upperLength;
    const unitY = dy / upperLength;

    const jointLength = distance(dip, pip);

    let nailWidth = jointLength * 0.55 * settings.size * finger.widthBoost;
    let nailHeight = upperLength * 0.95 * settings.size * finger.heightBoost; 

    if (finger.name === "thumb") {
      nailWidth *= 1.15;
      nailHeight *= 0.9;
    }

    const depthAdjust = settings.depth * 0.004 * upperLength;
    const targetX = tip.x - unitX * upperLength * finger.shift - unitX * depthAdjust;
    const targetY = tip.y - unitY * upperLength * finger.shift - unitY * depthAdjust + settings.verticalOffset;

    const targetAngle = Math.atan2(dy, dx) - Math.PI / 2;

    const smooth = smoothNails[index];

    if (!smooth.ready) {
      smooth.x = targetX;
      smooth.y = targetY;
      smooth.w = nailWidth;
      smooth.h = nailHeight;
      smooth.angle = targetAngle;
      smooth.ready = true;
    } else {
      smooth.x = lerp(smooth.x, targetX, 0.45);
      smooth.y = lerp(smooth.y, targetY, 0.45);
      smooth.w = lerp(smooth.w, nailWidth, 0.35);
      smooth.h = lerp(smooth.h, nailHeight, 0.35);
      smooth.angle = lerpAngle(smooth.angle, targetAngle, 0.25);
    }

    drawSinglePolishMask(
      smooth.x,
      smooth.y,
      smooth.w,
      smooth.h,
      smooth.angle,
      finger.name,
      settings
    );

    if (settings.showDebug) {
      drawDebugPoint(tip.x, tip.y);
      drawDebugPoint(dip.x, dip.y);
      drawDebugPoint(pip.x, pip.y);
    }
  });
}

function toCanvasPoint(landmark) {
  return {
    x: landmark.x * canvas.width,
    y: landmark.y * canvas.height
  };
}

function drawSinglePolishMask(x, y, w, h, angle, fingerName, settings) {
  ctx.save();

  ctx.translate(x, y);
  ctx.rotate(angle);

  if (fingerName === "thumb") {
    ctx.rotate(-0.25);
  }

  ctx.fillStyle = "rgba(0, 0, 0, 0.10)";
  createNailPath(2, 3, w * 1.04, h * 1.04, settings.shape);
  ctx.fill();

  const gradient = ctx.createLinearGradient(0, -h / 2, 0, h / 2);

  if (settings.finish === "chrome") {
    gradient.addColorStop(0, "#ffffff");
    gradient.addColorStop(0.25, lightenColor(settings.color, 45));
    gradient.addColorStop(0.55, settings.color);
    gradient.addColorStop(1, darkenColor(settings.color, 35));
  } else if (settings.finish === "matte") {
    gradient.addColorStop(0, lightenColor(settings.color, 15));
    gradient.addColorStop(1, darkenColor(settings.color, 15));
  } else if (settings.finish === "pearl") {
    gradient.addColorStop(0, "#fff3f8");
    gradient.addColorStop(0.45, lightenColor(settings.color, 30));
    gradient.addColorStop(1, settings.color);
  } else {
    gradient.addColorStop(0, lightenColor(settings.color, 45));
    gradient.addColorStop(0.45, settings.color);
    gradient.addColorStop(1, darkenColor(settings.color, 30));
  }

  ctx.fillStyle = gradient;
  createNailPath(0, 0, w, h, settings.shape);
  ctx.fill();

  if (settings.finish !== "matte") {
    ctx.fillStyle = "rgba(255, 255, 255, 0.32)";
    ctx.beginPath();
    ctx.ellipse(
      -w * 0.16,
      -h * 0.12,
      w * 0.13,
      h * 0.30,
      0.18,
      0,
      Math.PI * 2
    );
    ctx.fill();

    ctx.strokeStyle = "rgba(255, 255, 255, 0.28)";
    ctx.lineWidth = Math.max(1, w * 0.035);
    ctx.beginPath();
    ctx.moveTo(-w * 0.18, h * 0.30);
    ctx.quadraticCurveTo(0, h * 0.42, w * 0.18, h * 0.30);
    ctx.stroke();
  }

  ctx.strokeStyle = "rgba(255,255,255,0.30)";
  ctx.lineWidth = Math.max(1, w * 0.025);
  createNailPath(0, 0, w, h, settings.shape);
  ctx.stroke();

  ctx.restore();
}

function createNailPath(x, y, w, h, shape) {
  ctx.beginPath();

  if (shape === "square") {
    roundedRect(x - w / 2, y - h / 2, w, h, w * 0.12);
    return;
  }

  if (shape === "coffin") {
    ctx.moveTo(x - w * 0.32, y - h / 2);
    ctx.lineTo(x + w * 0.32, y - h / 2);
    ctx.lineTo(x + w * 0.50, y + h / 2);
    ctx.lineTo(x - w * 0.50, y + h / 2);
    ctx.closePath();
    return;
  }

  if (shape === "stiletto") {
    ctx.moveTo(x, y - h / 2);
    ctx.lineTo(x + w * 0.48, y + h / 2);
    ctx.lineTo(x - w * 0.48, y + h / 2);
    ctx.closePath();
    return;
  }

  if (shape === "round") {
    ctx.moveTo(x, y - h / 2);
    ctx.bezierCurveTo(
      x + w * 0.45,
      y - h * 0.46,
      x + w * 0.50,
      y - h * 0.05,
      x + w * 0.45,
      y + h * 0.28
    );
    ctx.bezierCurveTo(
      x + w * 0.35,
      y + h * 0.55,
      x - w * 0.35,
      y + h * 0.55,
      x - w * 0.45,
      y + h * 0.28
    );
    ctx.bezierCurveTo(
      x - w * 0.50,
      y - h * 0.05,
      x - w * 0.45,
      y - h * 0.46,
      x,
      y - h / 2
    );
    ctx.closePath();
    return;
  }

  ctx.moveTo(x, y - h / 2);
  ctx.bezierCurveTo(
    x + w * 0.46,
    y - h * 0.30,
    x + w * 0.42,
    y + h * 0.28,
    x + w * 0.25,
    y + h * 0.45
  );
  ctx.bezierCurveTo(
    x + w * 0.10,
    y + h * 0.55,
    x - w * 0.10,
    y + h * 0.55,
    x - w * 0.25,
    y + h * 0.45
  );
  ctx.bezierCurveTo(
    x - w * 0.42,
    y + h * 0.28,
    x - w * 0.46,
    y - h * 0.30,
    x,
    y - h / 2
  );
  ctx.closePath();
}

function roundedRect(x, y, w, h, r) {
  ctx.moveTo(x + r, y);
  ctx.lineTo(x + w - r, y);
  ctx.quadraticCurveTo(x + w, y, x + w, y + r);
  ctx.lineTo(x + w, y + h - r);
  ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
  ctx.lineTo(x + r, y + h);
  ctx.quadraticCurveTo(x, y + h, x, y + h - r);
  ctx.lineTo(x, y + r);
  ctx.quadraticCurveTo(x, y, x + r, y);
  ctx.closePath();
}

function drawDebugPoint(x, y) {
  ctx.fillStyle = "#00ff99";
  ctx.beginPath();
  ctx.arc(x, y, 4.5, 0, Math.PI * 2);
  ctx.fill();
}

function drawNoHandMessage() {
  ctx.save();
  ctx.setTransform(1, 0, 0, 1, 0, 0);
  ctx.fillStyle = "rgba(0,0,0,0.55)";
  ctx.fillRect(25, 25, 420, 55);
  ctx.fillStyle = "white";
  ctx.font = "22px Arial";
  ctx.fillText("Show your hand to try nail polish", 45, 60);
  ctx.restore();
}

function capturePreview() {
  if (!latestImage) {
    alert("Start the camera first.");
    return;
  }

  capturedImage.src = latestImage;
  capturedImage.classList.remove("hidden");
}

async function saveDesign() {
  const settings = getSettings();
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const form = new FormData();
  form.append('csrf_token', csrf);
  form.append('design_name', designNameInput.value.trim() || 'Saved AR Design');
  form.append('nail_color', settings.color);
  form.append('nail_shape', settings.shape);
  form.append('nail_finish', settings.finish);
  form.append('nail_size', String(settings.size));
  form.append('vertical_offset', String(settings.verticalOffset));
  form.append('depth_value', String(settings.depth));
  if (latestImage) form.append('preview_image', latestImage);
  try {
    const response = await fetch('backend/save-design.php', { method: 'POST', body: form, headers: { Accept: 'application/json' } });
    const result = await response.json();
    arMessage.className = 'alert ' + result.status;
    arMessage.textContent = result.message;
    if (result.status === 'success') {
      localStorage.setItem('savedNailTryOnDesign', JSON.stringify(settings));
    }
  } catch (error) {
    arMessage.className = 'alert error';
    arMessage.textContent = 'Design could not be saved.';
  }
}

function distance(a, b) {
  return Math.sqrt(
    Math.pow(a.x - b.x, 2) +
    Math.pow(a.y - b.y, 2)
  );
}

function lerp(a, b, t) {
  return a + (b - a) * t;
}

function lerpAngle(a, b, t) {
  let diff = b - a;

  while (diff > Math.PI) diff -= Math.PI * 2;
  while (diff < -Math.PI) diff += Math.PI * 2;

  return a + diff * t;
}

function lightenColor(hex, amount) {
  const color = parseInt(hex.replace("#", ""), 16);

  let r = (color >> 16) + amount;
  let g = ((color >> 8) & 255) + amount;
  let b = color & 255;

  b += amount;

  r = Math.min(255, r);
  g = Math.min(255, g);
  b = Math.min(255, b);

  return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}

function darkenColor(hex, amount) {
  const color = parseInt(hex.replace("#", ""), 16);

  let r = (color >> 16) - amount;
  let g = ((color >> 8) & 255) - amount;
  let b = (color & 255) - amount;

  r = Math.max(0, r);
  g = Math.max(0, g);
  b = Math.max(0, b);

  return "#" + ((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1);
}