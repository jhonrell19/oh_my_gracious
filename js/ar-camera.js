const videoElement = document.getElementById("video");
const canvasElement = document.getElementById("outputCanvas");
const canvasCtx = canvasElement.getContext("2d");

const startCameraBtn = document.getElementById("startCameraBtn");
const captureBtn = document.getElementById("captureBtn");
const saveDesignBtn = document.getElementById("saveDesignBtn");
const capturedImage = document.getElementById("capturedImage");

let hands = null;
let camera = null;
let latestCanvasImage = null;

const fingers = [
  { name: "thumb", tip: 4, base: 3 },
  { name: "index", tip: 8, base: 6 },
  { name: "middle", tip: 12, base: 10 },
  { name: "ring", tip: 16, base: 14 },
  { name: "pinky", tip: 20, base: 18 }
];

startCameraBtn.addEventListener("click", startCamera);
captureBtn.addEventListener("click", capturePreview);
saveDesignBtn.addEventListener("click", saveARDesign);

function getSettings() {
  return {
    color: document.getElementById("nailColor").value,
    shape: document.getElementById("nailShape").value,
    design: document.getElementById("nailDesign").value,
    size: parseFloat(document.getElementById("nailSize").value),
    verticalAdjust: parseInt(document.getElementById("verticalAdjust").value)
  };
}

async function startCamera() {
  try {
    if (hands !== null) {
      alert("Camera is already running.");
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
      minDetectionConfidence: 0.7,
      minTrackingConfidence: 0.7
    });

    hands.onResults(onHandResults);

    camera = new Camera(videoElement, {
      onFrame: async function () {
        await hands.send({
          image: videoElement
        });
      },
      width: 960,
      height: 720
    });

    await camera.start();

  } catch (error) {
    console.error(error);
    alert("Camera failed. Check camera permission or your phone webcam app.");
  }
}

function onHandResults(results) {
  const image = results.image;
  const width = image.width;
  const height = image.height;

  canvasElement.width = width;
  canvasElement.height = height;

  canvasCtx.save();
  canvasCtx.clearRect(0, 0, width, height);

  // Mirror the camera like a selfie camera
  canvasCtx.translate(width, 0);
  canvasCtx.scale(-1, 1);

  canvasCtx.drawImage(image, 0, 0, width, height);

  if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
    const landmarks = results.multiHandLandmarks[0];
    drawNailsOnFingers(landmarks, width, height);
  } else {
    drawInstructionText(width, height);
  }

  canvasCtx.restore();

  latestCanvasImage = canvasElement.toDataURL("image/png");
}

function drawInstructionText(width, height) {
  canvasCtx.save();
  canvasCtx.setTransform(1, 0, 0, 1, 0, 0);
  canvasCtx.fillStyle = "rgba(0, 0, 0, 0.55)";
  canvasCtx.fillRect(20, 20, 360, 55);
  canvasCtx.fillStyle = "white";
  canvasCtx.font = "20px Arial";
  canvasCtx.fillText("Show your hand to detect nails", 35, 55);
  canvasCtx.restore();
}

function drawNailsOnFingers(landmarks, width, height) {
  const settings = getSettings();

  fingers.forEach(function (finger) {
    const tip = landmarks[finger.tip];
    const base = landmarks[finger.base];

    const tipX = tip.x * width;
    const tipY = tip.y * height;

    const baseX = base.x * width;
    const baseY = base.y * height;

    const directionX = tipX - baseX;
    const directionY = tipY - baseY;

    const angle = Math.atan2(directionY, directionX) + Math.PI / 2;
    const fingerLength = Math.sqrt(directionX * directionX + directionY * directionY);

    let nailWidth = fingerLength * 0.48 * settings.size;
    let nailHeight = fingerLength * 0.88 * settings.size;

    if (finger.name === "thumb") {
      nailWidth *= 1.15;
      nailHeight *= 0.9;
    }

    if (finger.name === "pinky") {
      nailWidth *= 0.85;
      nailHeight *= 0.85;
    }

    const centerX = tipX;
    const centerY = tipY + settings.verticalAdjust;

    drawSingleNail(centerX, centerY, nailWidth, nailHeight, angle, settings);
  });
}

function drawSingleNail(x, y, width, height, angle, settings) {
  canvasCtx.save();

  canvasCtx.translate(x, y);
  canvasCtx.rotate(angle);

  drawNailBase(width, height, settings);
  drawNailDesign(width, height, settings);
  drawNailHighlight(width, height);

  canvasCtx.restore();
}

function drawNailBase(width, height, settings) {
  const gradient = canvasCtx.createLinearGradient(0, -height / 2, 0, height / 2);

  if (settings.design === "chrome") {
    gradient.addColorStop(0, "#ffffff");
    gradient.addColorStop(0.25, settings.color);
    gradient.addColorStop(0.55, "#999999");
    gradient.addColorStop(1, settings.color);
  } else {
    gradient.addColorStop(0, lightenColor(settings.color, 45));
    gradient.addColorStop(1, settings.color);
  }

  canvasCtx.fillStyle = gradient;
  canvasCtx.strokeStyle = "rgba(255, 255, 255, 0.75)";
  canvasCtx.lineWidth = 2;

  createNailShapePath(settings.shape, width, height);

  canvasCtx.fill();
  canvasCtx.stroke();
}

function createNailShapePath(shape, width, height) {
  canvasCtx.beginPath();

  if (shape === "square") {
    roundedRectangle(-width / 2, -height / 2, width, height, 6);
    return;
  }

  if (shape === "round") {
    roundedRectangle(-width / 2, -height / 2, width, height, 18);
    return;
  }

  if (shape === "almond") {
    canvasCtx.moveTo(0, -height / 2);
    canvasCtx.quadraticCurveTo(width / 2, -height / 4, width / 2, height / 3);
    canvasCtx.quadraticCurveTo(width / 3, height / 2, 0, height / 2);
    canvasCtx.quadraticCurveTo(-width / 3, height / 2, -width / 2, height / 3);
    canvasCtx.quadraticCurveTo(-width / 2, -height / 4, 0, -height / 2);
    canvasCtx.closePath();
    return;
  }

  if (shape === "coffin") {
    canvasCtx.moveTo(-width * 0.33, -height / 2);
    canvasCtx.lineTo(width * 0.33, -height / 2);
    canvasCtx.lineTo(width / 2, height / 2);
    canvasCtx.lineTo(-width / 2, height / 2);
    canvasCtx.closePath();
    return;
  }

  if (shape === "stiletto") {
    canvasCtx.moveTo(0, -height / 2);
    canvasCtx.lineTo(width / 2, height / 2);
    canvasCtx.lineTo(-width / 2, height / 2);
    canvasCtx.closePath();
    return;
  }

  roundedRectangle(-width / 2, -height / 2, width, height, 18);
}

function drawNailDesign(width, height, settings) {
  if (settings.design === "plain" || settings.design === "chrome") {
    return;
  }

  if (settings.design === "french") {
    canvasCtx.fillStyle = "white";
    roundedRectangle(-width / 2, -height / 2, width, height * 0.25, 8);
    canvasCtx.fill();
    return;
  }

  if (settings.design === "glitter") {
    for (let i = 0; i < 22; i++) {
      const dotX = (Math.random() - 0.5) * width * 0.8;
      const dotY = (Math.random() - 0.5) * height * 0.8;

      canvasCtx.fillStyle = "rgba(255, 255, 255, 0.85)";
      canvasCtx.beginPath();
      canvasCtx.arc(dotX, dotY, 1.6, 0, Math.PI * 2);
      canvasCtx.fill();
    }
    return;
  }

  if (settings.design === "flower") {
    drawFlower(0, 0, Math.min(width, height) * 0.1);
  }
}

function drawNailHighlight(width, height) {
  canvasCtx.fillStyle = "rgba(255, 255, 255, 0.25)";
  canvasCtx.beginPath();
  canvasCtx.ellipse(
    -width * 0.15,
    -height * 0.1,
    width * 0.15,
    height * 0.3,
    0,
    0,
    Math.PI * 2
  );
  canvasCtx.fill();
}

function drawFlower(x, y, radius) {
  canvasCtx.save();
  canvasCtx.translate(x, y);

  canvasCtx.fillStyle = "rgba(255, 255, 255, 0.95)";

  for (let i = 0; i < 5; i++) {
    const angle = (Math.PI * 2 / 5) * i;
    const petalX = Math.cos(angle) * radius * 1.8;
    const petalY = Math.sin(angle) * radius * 1.8;

    canvasCtx.beginPath();
    canvasCtx.arc(petalX, petalY, radius, 0, Math.PI * 2);
    canvasCtx.fill();
  }

  canvasCtx.fillStyle = "#ffd54f";
  canvasCtx.beginPath();
  canvasCtx.arc(0, 0, radius * 0.8, 0, Math.PI * 2);
  canvasCtx.fill();

  canvasCtx.restore();
}

function roundedRectangle(x, y, width, height, radius) {
  canvasCtx.moveTo(x + radius, y);
  canvasCtx.lineTo(x + width - radius, y);
  canvasCtx.quadraticCurveTo(x + width, y, x + width, y + radius);
  canvasCtx.lineTo(x + width, y + height - radius);
  canvasCtx.quadraticCurveTo(x + width, y + height, x + width - radius, y + height);
  canvasCtx.lineTo(x + radius, y + height);
  canvasCtx.quadraticCurveTo(x, y + height, x, y + height - radius);
  canvasCtx.lineTo(x, y + radius);
  canvasCtx.quadraticCurveTo(x, y, x + radius, y);
  canvasCtx.closePath();
}

function lightenColor(hex, amount) {
  const cleanHex = hex.replace("#", "");
  const number = parseInt(cleanHex, 16);

  let red = (number >> 16) + amount;
  let green = ((number >> 8) & 255) + amount;
  let blue = (number & 255) + amount;

  red = Math.min(255, red);
  green = Math.min(255, green);
  blue = Math.min(255, blue);

  return "#" + ((1 << 24) + (red << 16) + (green << 8) + blue).toString(16).slice(1);
}

function capturePreview() {
  if (!latestCanvasImage) {
    alert("Start the camera first.");
    return;
  }

  capturedImage.src = latestCanvasImage;
  capturedImage.classList.remove("hidden");
}

function saveARDesign() {
  const settings = getSettings();
  localStorage.setItem("savedARDesign", JSON.stringify(settings));
  alert("AR nail design saved!");
}