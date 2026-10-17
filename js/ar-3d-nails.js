'use strict';

const video = document.getElementById('inputVideo');
const canvas = document.getElementById('threeCanvas');
const ctx = canvas.getContext('2d', { alpha: false });

const startBtn = document.getElementById('startBtn');
const captureBtn = document.getElementById('captureBtn');
const saveBtn = document.getElementById('saveBtn');
const capturedImage = document.getElementById('capturedImage');

const designNameInput = document.getElementById('designName');
const nailColorInput = document.getElementById('nailColor');
const nailFinishInput = document.getElementById('nailFinish');
const polishStrengthInput = document.getElementById('polishStrength');
const detectionConfidenceInput = document.getElementById('detectionConfidence');
const maskThresholdInput = document.getElementById('maskThreshold');
const coverageBoostInput = document.getElementById('coverageBoost');
const edgeSoftnessInput = document.getElementById('edgeSoftness');
const showMaskInput = document.getElementById('showMask');

const nailShapeInput = document.getElementById('nailShape');
const nailSizeInput = document.getElementById('nailSize');
const verticalOffsetInput = document.getElementById('verticalOffset');
const nailDepthInput = document.getElementById('nailDepth');
const useFallbackInput = document.getElementById('useFallback');
const showDebugInput = document.getElementById('showDebug');

const arMessage = document.getElementById('arMessage');
const arGuide = document.getElementById('arGuide');
const aiStatus = document.getElementById('aiStatus');
const aiMetrics = document.getElementById('aiMetrics');
const aiStatusDot = document.getElementById('aiStatusDot');
const modelProgressWrap = document.getElementById('modelProgressWrap');
const modelProgressBar = document.getElementById('modelProgressBar');
const trackingStatus = document.getElementById('trackingStatus');
const nailCount = document.getElementById('nailCount');
const inferenceTime = document.getElementById('inferenceTime');

const strengthValue = document.getElementById('strengthValue');
const confidenceValue = document.getElementById('confidenceValue');
const thresholdValue = document.getElementById('thresholdValue');
const boostValue = document.getElementById('boostValue');
const softnessValue = document.getElementById('softnessValue');

const MODEL_SIZE = 768;
const MODEL_PATH = 'models/nail-seg.onnx';
const AI_INTERVAL_MS = 180;
const MAX_DETECTIONS = 8;
const NMS_THRESHOLD = 0.45;

const frameCanvas = document.createElement('canvas');
const frameCtx = frameCanvas.getContext('2d');
const inferenceCanvas = document.createElement('canvas');
const inferenceCtx = inferenceCanvas.getContext('2d', { willReadFrequently: true });
const liveMaskCanvas = document.createElement('canvas');
const liveMaskCtx = liveMaskCanvas.getContext('2d');
const expandedMaskCanvas = document.createElement('canvas');
const expandedMaskCtx = expandedMaskCanvas.getContext('2d');
const featherMaskCanvas = document.createElement('canvas');
const featherMaskCtx = featherMaskCanvas.getContext('2d');
const polishCanvas = document.createElement('canvas');
const polishCtx = polishCanvas.getContext('2d');

const modelMaskCanvas = document.createElement('canvas');
modelMaskCanvas.width = MODEL_SIZE;
modelMaskCanvas.height = MODEL_SIZE;
const modelMaskCtx = modelMaskCanvas.getContext('2d');

const smallMaskCanvas = document.createElement('canvas');
const smallMaskCtx = smallMaskCanvas.getContext('2d');

const originalMaskCanvas = document.createElement('canvas');
const originalMaskCtx = originalMaskCanvas.getContext('2d');

let hands = null;
let camera = null;
let ortSession = null;
let aiReady = false;
let aiBusy = false;
let aiComponents = [];
let lastAiRunAt = 0;
let latestImage = null;
let lastLandmarks = null;
let currentFrameWidth = 0;
let currentFrameHeight = 0;
let lastModelError = '';

const fingers = [
  { name: 'thumb', tip: 4, dip: 3, pip: 2, widthBoost: 1.45, heightBoost: 1.10, shift: 0.22 },
  { name: 'index', tip: 8, dip: 7, pip: 6, widthBoost: 1.25, heightBoost: 1.15, shift: 0.25 },
  { name: 'middle', tip: 12, dip: 11, pip: 10, widthBoost: 1.30, heightBoost: 1.20, shift: 0.25 },
  { name: 'ring', tip: 16, dip: 15, pip: 14, widthBoost: 1.20, heightBoost: 1.15, shift: 0.25 },
  { name: 'pinky', tip: 20, dip: 19, pip: 18, widthBoost: 1.05, heightBoost: 1.00, shift: 0.25 }
];

const smoothNails = new Array(5).fill(null).map(() => ({
  x: 0, y: 0, w: 0, h: 0, angle: 0, ready: false
}));

startBtn.addEventListener('click', startCamera);
captureBtn.addEventListener('click', capturePreview);
saveBtn.addEventListener('click', saveDesign);

for (const element of [
  nailColorInput,
  nailFinishInput,
  polishStrengthInput,
  detectionConfidenceInput,
  maskThresholdInput,
  coverageBoostInput,
  edgeSoftnessInput,
  showMaskInput,
  nailShapeInput,
  nailSizeInput,
  verticalOffsetInput,
  nailDepthInput,
  useFallbackInput,
  showDebugInput
]) {
  element?.addEventListener('input', syncControlLabels);
  element?.addEventListener('change', syncControlLabels);
}

syncControlLabels();
loadAiModel();

function syncControlLabels() {
  strengthValue.textContent = `${polishStrengthInput.value}%`;
  confidenceValue.textContent = `${detectionConfidenceInput.value}%`;
  thresholdValue.textContent = `${maskThresholdInput.value}%`;
  boostValue.textContent = `${coverageBoostInput.value} px`;
  softnessValue.textContent = `${Number(edgeSoftnessInput.value).toFixed(1)} px`;
}

function setAiState(state, title, detail = '') {
  aiStatus.textContent = title;
  aiMetrics.textContent = detail;
  aiStatusDot.className = `ar-status-dot ${state}`;
}

async function loadAiModel() {
  if (typeof ort === 'undefined') {
    setAiState('error', 'AI runtime failed to load', 'Check your internet connection, then refresh the page.');
    lastModelError = 'ONNX Runtime Web did not load.';
    return;
  }

  try {
    setAiState('loading', 'Loading AI nail model…', 'Downloading the local 46 MB segmentation model.');
    modelProgressWrap.classList.remove('hidden');
    modelProgressBar.style.width = '3%';

    ort.env.wasm.wasmPaths = 'https://cdn.jsdelivr.net/npm/onnxruntime-web@1.20.1/dist/';
    ort.env.wasm.numThreads = Math.max(1, Math.min(4, navigator.hardwareConcurrency || 2));

    const response = await fetch(MODEL_PATH, { cache: 'force-cache' });
    if (!response.ok) {
      throw new Error(`Model request failed (${response.status}).`);
    }

    const total = Number(response.headers.get('content-length')) || 0;
    let modelBuffer;

    if (response.body && total > 0) {
      const reader = response.body.getReader();
      const chunks = [];
      let received = 0;

      while (true) {
        const { done, value } = await reader.read();
        if (done) break;
        chunks.push(value);
        received += value.length;
        const percent = Math.min(82, Math.max(4, Math.round((received / total) * 82)));
        modelProgressBar.style.width = `${percent}%`;
        aiMetrics.textContent = `Loading model… ${Math.round(received / 1024 / 1024)} / ${Math.round(total / 1024 / 1024)} MB`;
      }

      const joined = new Uint8Array(received);
      let offset = 0;
      for (const chunk of chunks) {
        joined.set(chunk, offset);
        offset += chunk.length;
      }
      modelBuffer = joined.buffer;
    } else {
      modelBuffer = await response.arrayBuffer();
      modelProgressBar.style.width = '82%';
    }

    let providers = ['wasm'];
    if (navigator.gpu) {
      providers = ['webgpu', 'wasm'];
    }

    try {
      ortSession = await ort.InferenceSession.create(modelBuffer, {
        executionProviders: providers,
        graphOptimizationLevel: 'all'
      });
    } catch (gpuError) {
      console.warn('WebGPU model load failed; retrying with WASM.', gpuError);
      ortSession = await ort.InferenceSession.create(modelBuffer, {
        executionProviders: ['wasm'],
        graphOptimizationLevel: 'all'
      });
    }

    modelProgressBar.style.width = '100%';
    aiReady = true;
    setTimeout(() => modelProgressWrap.classList.add('hidden'), 450);
    setAiState('ready', 'AI nail mask ready', navigator.gpu ? 'GPU acceleration available; automatic nail masking is enabled.' : 'Running in browser on CPU/WASM; automatic nail masking is enabled.');
  } catch (error) {
    console.error('AI model load error:', error);
    lastModelError = error.message || String(error);
    modelProgressWrap.classList.add('hidden');
    setAiState('error', 'AI nail model unavailable', 'Landmark fallback can still be enabled below.');
  }
}

function getSettings() {
  return {
    color: nailColorInput.value,
    finish: nailFinishInput.value,
    strength: Number(polishStrengthInput.value) / 100,
    confidence: Number(detectionConfidenceInput.value) / 100,
    threshold: Number(maskThresholdInput.value) / 100,
    coverageBoost: Number(coverageBoostInput.value),
    edgeSoftness: Number(edgeSoftnessInput.value),
    showMask: showMaskInput.value === 'yes',
    shape: nailShapeInput.value,
    size: Number(nailSizeInput.value),
    verticalOffset: Number(verticalOffsetInput.value),
    depth: Number(nailDepthInput.value),
    useFallback: useFallbackInput.checked,
    showDebug: showDebugInput.value === 'yes'
  };
}

async function startCamera() {
  try {
    if (hands !== null) {
      alert('Camera is already running.');
      return;
    }

    if (!navigator.mediaDevices?.getUserMedia) {
      alert('Camera access is not supported in this browser.');
      return;
    }

    if (typeof Hands === 'undefined' || typeof Camera === 'undefined') {
      alert('MediaPipe did not load. Check your internet connection and refresh the page.');
      return;
    }

    startBtn.disabled = true;
    startBtn.textContent = 'Camera Running';
    trackingStatus.textContent = 'Starting camera…';

    hands = new Hands({
      locateFile(file) {
        return `https://cdn.jsdelivr.net/npm/@mediapipe/hands/${file}`;
      }
    });

    hands.setOptions({
      maxNumHands: 1,
      modelComplexity: 1,
      minDetectionConfidence: 0.70,
      minTrackingConfidence: 0.70
    });

    hands.onResults(onResults);

    camera = new Camera(video, {
      onFrame: async () => {
        await hands.send({ image: video });
      },
      width: 960,
      height: 720
    });

    await camera.start();
    captureBtn.disabled = false;
    trackingStatus.textContent = 'Camera live';
  } catch (error) {
    console.error('Camera error:', error);
    startBtn.disabled = false;
    startBtn.textContent = 'Start Camera';
    trackingStatus.textContent = 'Camera error';
    alert(`Camera failed: ${error.message}`);
  }
}

function onResults(results) {
  const image = results.image;
  const width = image.width || video.videoWidth || 960;
  const height = image.height || video.videoHeight || 720;
  ensureCanvasSize(width, height);

  const landmarks = results.multiHandLandmarks?.[0] || null;
  lastLandmarks = landmarks ? cloneLandmarks(landmarks) : null;

  frameCtx.setTransform(1, 0, 0, 1, 0, 0);
  frameCtx.clearRect(0, 0, width, height);
  frameCtx.drawImage(image, 0, 0, width, height);

  if (landmarks) {
    trackingStatus.textContent = 'Hand tracked';
    if (aiReady && !aiBusy && performance.now() - lastAiRunAt >= AI_INTERVAL_MS) {
      lastAiRunAt = performance.now();
      scheduleAiSegmentation(image, landmarks, width, height);
    }
  } else {
    trackingStatus.textContent = 'Show the back of one hand';
    arGuide.textContent = 'Show the back of one hand with nails facing the camera';
  }

  renderCurrentFrame(image, landmarks, width, height);

  ctx.save();
  ctx.setTransform(-1, 0, 0, 1, width, 0);
  ctx.drawImage(frameCanvas, 0, 0, width, height);
  ctx.restore();

  latestImage = canvas.toDataURL('image/png');
}

function ensureCanvasSize(width, height) {
  if (currentFrameWidth === width && currentFrameHeight === height) return;
  currentFrameWidth = width;
  currentFrameHeight = height;

  for (const c of [canvas, frameCanvas, liveMaskCanvas, expandedMaskCanvas, featherMaskCanvas, polishCanvas, originalMaskCanvas]) {
    c.width = width;
    c.height = height;
  }
}

function renderCurrentFrame(image, landmarks, width, height) {
  const settings = getSettings();
  const hasAiMask = landmarks && buildTrackedAiMask(landmarks, width, height);

  if (hasAiMask) {
    applyCoverageAndFeather(settings.coverageBoost, settings.edgeSoftness, width, height);
    drawMaskedPolish(image, settings, width, height);
    frameCtx.drawImage(polishCanvas, 0, 0, width, height);
    arGuide.textContent = `${aiComponents.length} AI nail mask${aiComponents.length === 1 ? '' : 's'} attached to tracked fingers`;
  } else if (landmarks && settings.useFallback) {
    drawLandmarkFallback(landmarks, settings);
    arGuide.textContent = 'AI mask not ready — using landmark fallback';
  } else if (landmarks) {
    arGuide.textContent = aiReady ? 'AI is looking for visible nail pixels…' : 'AI model loading — keep your hand steady';
  }

  if (landmarks && settings.showDebug) {
    drawDebugLandmarksOnFrame(landmarks);
  }
}

function applyCoverageAndFeather(boost, softness, width, height) {
  expandedMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  expandedMaskCtx.clearRect(0, 0, width, height);

  if (boost <= 0) {
    expandedMaskCtx.drawImage(liveMaskCanvas, 0, 0);
  } else {
    for (let y = -boost; y <= boost; y++) {
      for (let x = -boost; x <= boost; x++) {
        if (x * x + y * y <= boost * boost + 0.5) {
          expandedMaskCtx.drawImage(liveMaskCanvas, x, y);
        }
      }
    }
  }

  featherMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  featherMaskCtx.clearRect(0, 0, width, height);
  featherMaskCtx.filter = softness > 0 ? `blur(${softness}px)` : 'none';
  featherMaskCtx.drawImage(expandedMaskCanvas, 0, 0);
  featherMaskCtx.filter = 'none';
}

function drawMaskedPolish(image, settings, width, height) {
  polishCtx.setTransform(1, 0, 0, 1, 0, 0);
  polishCtx.clearRect(0, 0, width, height);

  polishCtx.globalCompositeOperation = 'source-over';
  polishCtx.globalAlpha = 1;
  polishCtx.filter = 'none';


  /*
  |--------------------------------------------------------------------------
  | DEBUG AI MASK
  |--------------------------------------------------------------------------
  |
  | If "Show AI Mask in Green" is enabled, display the segmentation mask.
  |
  */

  if (settings.showMask) {
    polishCtx.fillStyle = 'rgba(20, 255, 90, 0.92)';
    polishCtx.fillRect(0, 0, width, height);

    polishCtx.globalCompositeOperation = 'destination-in';
    polishCtx.drawImage(featherMaskCanvas, 0, 0);

    polishCtx.globalCompositeOperation = 'source-over';
    return;
  }


  /*
  |--------------------------------------------------------------------------
  | SOLID BASE POLISH
  |--------------------------------------------------------------------------
  |
  | IMPORTANT:
  |
  | Previously:
  |
  |     globalAlpha = settings.strength;
  |
  | If strength was 82%, the original nail/skin remained visible.
  |
  | Now the slider controls COLOR INTENSITY rather than transparency.
  | The nail stays almost completely opaque.
  |
  */

  let baseOpacity;

  if (settings.finish === 'natural') {
    baseOpacity = clamp(
      0.86 + settings.strength * 0.12,
      0.88,
      0.98
    );
  } else {
    baseOpacity = clamp(
      0.91 + settings.strength * 0.09,
      0.93,
      1.00
    );
  }


  /*
  |--------------------------------------------------------------------------
  | 1. OPAQUE BASE COAT
  |--------------------------------------------------------------------------
  */

  polishCtx.globalCompositeOperation = 'source-over';
  polishCtx.globalAlpha = baseOpacity;

  polishCtx.fillStyle = settings.color;
  polishCtx.fillRect(
    0,
    0,
    width,
    height
  );


  /*
  |--------------------------------------------------------------------------
  | 2. SMALL AMOUNT OF NATURAL SHADING
  |--------------------------------------------------------------------------
  |
  | We only use a very tiny part of the original camera image.
  |
  | This lets the nail retain a little natural curvature without making
  | the polish transparent.
  |
  */

  polishCtx.globalCompositeOperation = 'multiply';

  if (settings.finish === 'matte') {

    polishCtx.globalAlpha = 0.08;

  } else if (settings.finish === 'natural') {

    polishCtx.globalAlpha = 0.06;

  } else {

    polishCtx.globalAlpha = 0.045;
  }

  polishCtx.drawImage(
    image,
    0,
    0,
    width,
    height
  );


  /*
  |--------------------------------------------------------------------------
  | 3. DEPTH / CURVATURE
  |--------------------------------------------------------------------------
  |
  | Adds subtle brightness on top and darkness toward the bottom.
  |
  | This helps prevent the solid color from looking like flat paint.
  |
  */

  polishCtx.globalCompositeOperation = 'source-over';
  polishCtx.globalAlpha = 1;

  const depthGradient =
    polishCtx.createLinearGradient(
      0,
      0,
      0,
      height
    );

  depthGradient.addColorStop(
    0.00,
    'rgba(255,255,255,0.045)'
  );

  depthGradient.addColorStop(
    0.48,
    'rgba(255,255,255,0.00)'
  );

  depthGradient.addColorStop(
    1.00,
    'rgba(0,0,0,0.075)'
  );

  polishCtx.fillStyle = depthGradient;

  polishCtx.fillRect(
    0,
    0,
    width,
    height
  );


  /*
  |--------------------------------------------------------------------------
  | 4. GLOSS / CHROME / PEARL HIGHLIGHTS
  |--------------------------------------------------------------------------
  */

  drawPerNailFinishHighlights(settings);


  /*
  |--------------------------------------------------------------------------
  | 5. CLIP EVERYTHING TO AI NAIL MASK
  |--------------------------------------------------------------------------
  |
  | Nothing outside the detected nail region will remain visible.
  |
  */

  polishCtx.globalAlpha = 1;

  polishCtx.globalCompositeOperation =
    'destination-in';

  polishCtx.drawImage(
    featherMaskCanvas,
    0,
    0
  );


  /*
  |--------------------------------------------------------------------------
  | RESET CANVAS
  |--------------------------------------------------------------------------
  */

  polishCtx.globalCompositeOperation =
    'source-over';

  polishCtx.globalAlpha = 1;
  polishCtx.filter = 'none';
}



/*
|--------------------------------------------------------------------------
| PER-NAIL FINISH / REFLECTION
|--------------------------------------------------------------------------
|
| Instead of adding one huge white transparent overlay over the whole hand,
| every detected nail receives its own highlight.
|
*/

function drawPerNailFinishHighlights(settings) {

  if (!lastLandmarks) {
    return;
  }

  if (!aiComponents.length) {
    return;
  }


  /*
  |--------------------------------------------------------------------------
  | MATTE / NATURAL
  |--------------------------------------------------------------------------
  */

  if (
    settings.finish === 'matte' ||
    settings.finish === 'natural'
  ) {
    return;
  }


  /*
  |--------------------------------------------------------------------------
  | DRAW EACH NAIL
  |--------------------------------------------------------------------------
  */

  for (const component of aiComponents) {

    const pose =
      getTrackedComponentPose(
        component,
        lastLandmarks
      );

    if (!pose) {
      continue;
    }


    const w = Math.max(
      8,
      pose.width
    );

    const h = Math.max(
      12,
      pose.height
    );


    polishCtx.save();

    polishCtx.translate(
      pose.centerX,
      pose.centerY
    );

    polishCtx.rotate(
      pose.rotation
    );


    /*
    |--------------------------------------------------------------------------
    | CHROME
    |--------------------------------------------------------------------------
    */

    if (settings.finish === 'chrome') {

      polishCtx.globalCompositeOperation =
        'screen';

      polishCtx.globalAlpha = 0.58;


      const chrome =
        polishCtx.createLinearGradient(
          -w * 0.55,
          -h * 0.20,

          w * 0.55,
          h * 0.20
        );


      chrome.addColorStop(
        0.00,
        'rgba(255,255,255,0.03)'
      );

      chrome.addColorStop(
        0.30,
        'rgba(255,255,255,0.16)'
      );

      chrome.addColorStop(
        0.43,
        'rgba(255,255,255,0.92)'
      );

      chrome.addColorStop(
        0.53,
        'rgba(255,255,255,0.20)'
      );

      chrome.addColorStop(
        0.72,
        'rgba(255,255,255,0.62)'
      );

      chrome.addColorStop(
        1.00,
        'rgba(255,255,255,0.04)'
      );


      polishCtx.fillStyle = chrome;

      polishCtx.fillRect(
        -w * 0.60,
        -h * 0.60,

        w * 1.20,
        h * 1.20
      );
    }


    /*
    |--------------------------------------------------------------------------
    | PEARL
    |--------------------------------------------------------------------------
    */

    else if (settings.finish === 'pearl') {

      polishCtx.globalCompositeOperation =
        'screen';

      polishCtx.globalAlpha = 0.28;


      const pearl =
        polishCtx.createRadialGradient(

          -w * 0.12,
          -h * 0.22,
          0,

          -w * 0.12,
          -h * 0.22,
          Math.max(w, h) * 0.72
        );


      pearl.addColorStop(
        0.00,
        'rgba(255,248,255,0.88)'
      );

      pearl.addColorStop(
        0.32,
        'rgba(255,238,250,0.28)'
      );

      pearl.addColorStop(
        1.00,
        'rgba(255,255,255,0.00)'
      );


      polishCtx.fillStyle = pearl;


      polishCtx.fillRect(
        -w * 0.60,
        -h * 0.60,

        w * 1.20,
        h * 1.20
      );
    }


    /*
    |--------------------------------------------------------------------------
    | GLOSSY
    |--------------------------------------------------------------------------
    */

    else {

      /*
      | Main reflection strip
      */

      polishCtx.globalCompositeOperation =
        'screen';

      polishCtx.globalAlpha = 0.36;


      const gloss =
        polishCtx.createLinearGradient(

          -w * 0.28,
          -h * 0.45,

          w * 0.18,
          h * 0.34
        );


      gloss.addColorStop(
        0.00,
        'rgba(255,255,255,0.00)'
      );

      gloss.addColorStop(
        0.22,
        'rgba(255,255,255,0.18)'
      );

      gloss.addColorStop(
        0.42,
        'rgba(255,255,255,0.95)'
      );

      gloss.addColorStop(
        0.55,
        'rgba(255,255,255,0.20)'
      );

      gloss.addColorStop(
        1.00,
        'rgba(255,255,255,0.00)'
      );


      polishCtx.fillStyle = gloss;


      polishCtx.fillRect(

        -w * 0.42,
        -h * 0.48,

        w * 0.34,
        h * 0.68
      );


      /*
      | Small reflected light spot
      */

      polishCtx.globalAlpha = 0.24;


      const spot =
        polishCtx.createRadialGradient(

          -w * 0.12,
          -h * 0.25,
          0,

          -w * 0.12,
          -h * 0.25,

          Math.max(
            4,
            w * 0.22
          )
        );


      spot.addColorStop(
        0.00,
        'rgba(255,255,255,0.95)'
      );

      spot.addColorStop(
        1.00,
        'rgba(255,255,255,0.00)'
      );


      polishCtx.fillStyle = spot;


      polishCtx.beginPath();

      polishCtx.ellipse(

        -w * 0.12,
        -h * 0.24,

        w * 0.17,
        h * 0.13,

        0,
        0,
        Math.PI * 2
      );

      polishCtx.fill();
    }


    polishCtx.restore();
  }


  polishCtx.globalAlpha = 1;

  polishCtx.globalCompositeOperation =
    'source-over';
}



/*
|--------------------------------------------------------------------------
| GET CURRENT POSITION OF AI NAIL
|--------------------------------------------------------------------------
|
| The segmentation happens only every few milliseconds.
|
| This calculates where each nail currently is by using MediaPipe tracking.
|
| The reflections therefore move together with each nail.
|
*/

function getTrackedComponentPose(
  component,
  currentLandmarks
) {

  const finger =
    fingers[component.fingerIndex];


  if (
    !finger ||
    !component.anchorTip ||
    !component.anchorDip
  ) {
    return null;
  }


  const oldTip =
    component.anchorTip;

  const oldDip =
    component.anchorDip;


  const newTip =
    landmarkToPoint(
      currentLandmarks[finger.tip],
      currentFrameWidth,
      currentFrameHeight
    );


  const newDip =
    landmarkToPoint(
      currentLandmarks[finger.dip],
      currentFrameWidth,
      currentFrameHeight
    );


  const oldLength =
    distance(
      oldTip,
      oldDip
    );


  const newLength =
    distance(
      newTip,
      newDip
    );


  if (
    oldLength < 4 ||
    newLength < 4
  ) {
    return null;
  }


  /*
  |--------------------------------------------------------------------------
  | SCALE
  |--------------------------------------------------------------------------
  */

  const scale =
    clamp(
      newLength / oldLength,
      0.68,
      1.45
    );


  /*
  |--------------------------------------------------------------------------
  | ROTATION
  |--------------------------------------------------------------------------
  */

  const oldAngle =
    Math.atan2(

      oldTip.y - oldDip.y,
      oldTip.x - oldDip.x
    );


  const newAngle =
    Math.atan2(

      newTip.y - newDip.y,
      newTip.x - newDip.x
    );


  const rotation =
    normalizeAngle(
      newAngle - oldAngle
    );


  if (
    Math.abs(rotation) >
    Math.PI * 0.55
  ) {
    return null;
  }


  /*
  |--------------------------------------------------------------------------
  | MOVE ORIGINAL MASK CENTER
  |--------------------------------------------------------------------------
  */

  const localX =
    component.centerX -
    oldTip.x;


  const localY =
    component.centerY -
    oldTip.y;


  const cos =
    Math.cos(rotation);


  const sin =
    Math.sin(rotation);


  const rotatedX =
    (
      localX * cos -
      localY * sin
    ) * scale;


  const rotatedY =
    (
      localX * sin +
      localY * cos
    ) * scale;


  return {

    centerX:
      newTip.x +
      rotatedX,

    centerY:
      newTip.y +
      rotatedY,

    width:
      component.width *
      scale,

    height:
      component.height *
      scale,

    rotation
  };
}

function buildTrackedAiMask(currentLandmarks, width, height) {
  liveMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  liveMaskCtx.clearRect(0, 0, width, height);

  if (!aiComponents.length) return false;

  let drawn = 0;
  for (const component of aiComponents) {
    const finger = fingers[component.fingerIndex];
    if (!finger) continue;

    const oldTip = component.anchorTip;
    const oldDip = component.anchorDip;
    const newTip = landmarkToPoint(currentLandmarks[finger.tip], width, height);
    const newDip = landmarkToPoint(currentLandmarks[finger.dip], width, height);

    const oldLength = distance(oldTip, oldDip);
    const newLength = distance(newTip, newDip);
    if (oldLength < 4 || newLength < 4) continue;

    const scale = clamp(newLength / oldLength, 0.68, 1.45);
    const oldAngle = Math.atan2(oldTip.y - oldDip.y, oldTip.x - oldDip.x);
    const newAngle = Math.atan2(newTip.y - newDip.y, newTip.x - newDip.x);
    const rotation = normalizeAngle(newAngle - oldAngle);

    if (Math.abs(rotation) > Math.PI * 0.55) continue;

    liveMaskCtx.save();
    liveMaskCtx.translate(newTip.x, newTip.y);
    liveMaskCtx.rotate(rotation);
    liveMaskCtx.scale(scale, scale);
    liveMaskCtx.translate(-oldTip.x, -oldTip.y);
    liveMaskCtx.drawImage(component.canvas, component.x, component.y, component.width, component.height);
    liveMaskCtx.restore();
    drawn++;
  }

  return drawn > 0;
}

function scheduleAiSegmentation(image, landmarks, width, height) {
  aiBusy = true;

  inferenceCanvas.width = width;
  inferenceCanvas.height = height;
  inferenceCtx.setTransform(1, 0, 0, 1, 0, 0);
  inferenceCtx.clearRect(0, 0, width, height);
  inferenceCtx.drawImage(image, 0, 0, width, height);

  const landmarkSnapshot = cloneLandmarks(landmarks);
  const settings = getSettings();
  const started = performance.now();

  runAiSegmentation(inferenceCanvas, landmarkSnapshot, settings.confidence, settings.threshold)
    .then(components => {
      aiComponents = components;
      const elapsed = performance.now() - started;
      nailCount.textContent = `${components.length} nail${components.length === 1 ? '' : 's'} masked`;
      inferenceTime.textContent = `AI: ${Math.round(elapsed)} ms`;

      if (components.length > 0) {
        setAiState('ready', 'AI nail mask active', `${components.length} nail region${components.length === 1 ? '' : 's'} matched to your fingers.`);
      } else {
        setAiState('warning', 'No nail mask found', 'Move the hand closer, use front lighting, or lower Detection Confidence slightly.');
      }
    })
    .catch(error => {
      console.error('AI inference error:', error);
      lastModelError = error.message || String(error);
      setAiState('error', 'AI inference error', 'Refresh the page if the model stops responding.');
    })
    .finally(() => {
      aiBusy = false;
    });
}

async function runAiSegmentation(sourceCanvas, landmarks, confidence, threshold) {
  if (!ortSession) return [];

  const { tensor, geometry } = preprocessForModel(sourceCanvas);
  const feeds = {};
  feeds[ortSession.inputNames[0]] = tensor;
  const outputMap = await ortSession.run(feeds);
  const outputs = Object.values(outputMap);
  const prediction = outputs.find(t => t.dims.length === 3);
  const prototypes = outputs.find(t => t.dims.length === 4);

  if (!prediction || !prototypes) {
    throw new Error('Unexpected nail model outputs.');
  }

  const detections = decodeDetections(prediction, confidence);
  const kept = nonMaximumSuppression(detections, NMS_THRESHOLD, MAX_DETECTIONS);
  const components = [];
  const usedFingers = new Set();

  for (const detection of kept) {
    const component = buildMaskComponent(detection, prototypes, geometry, sourceCanvas.width, sourceCanvas.height, threshold);
    if (!component) continue;

    const assignment = assignComponentToFinger(component, landmarks, sourceCanvas.width, sourceCanvas.height, usedFingers);
    if (!assignment) continue;

    component.fingerIndex = assignment.fingerIndex;
    component.anchorTip = assignment.tip;
    component.anchorDip = assignment.dip;
    usedFingers.add(assignment.fingerIndex);
    components.push(component);
  }

  return components;
}

function preprocessForModel(sourceCanvas) {
  const width = sourceCanvas.width;
  const height = sourceCanvas.height;
  const scale = Math.min(MODEL_SIZE / width, MODEL_SIZE / height);
  const nw = Math.round(width * scale);
  const nh = Math.round(height * scale);
  const left = Math.floor((MODEL_SIZE - nw) / 2);
  const top = Math.floor((MODEL_SIZE - nh) / 2);

  modelMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  modelMaskCtx.clearRect(0, 0, MODEL_SIZE, MODEL_SIZE);

  const prepCanvas = modelMaskCanvas;
  const prepCtx = modelMaskCtx;
  prepCtx.fillStyle = 'rgb(114,114,114)';
  prepCtx.fillRect(0, 0, MODEL_SIZE, MODEL_SIZE);
  prepCtx.drawImage(sourceCanvas, 0, 0, width, height, left, top, nw, nh);

  const rgba = prepCtx.getImageData(0, 0, MODEL_SIZE, MODEL_SIZE).data;
  const plane = MODEL_SIZE * MODEL_SIZE;
  const input = new Float32Array(plane * 3);

  for (let p = 0, i = 0; p < plane; p++, i += 4) {
    input[p] = rgba[i] / 255;
    input[plane + p] = rgba[i + 1] / 255;
    input[plane * 2 + p] = rgba[i + 2] / 255;
  }

  return {
    tensor: new ort.Tensor('float32', input, [1, 3, MODEL_SIZE, MODEL_SIZE]),
    geometry: { left, top, nw, nh, width, height }
  };
}

function decodeDetections(prediction, confidence) {
  const dims = prediction.dims;
  const data = prediction.data;
  let channelFirst = false;
  let anchors;
  let channels;

  if (dims[1] === 37) {
    channelFirst = true;
    channels = dims[1];
    anchors = dims[2];
  } else if (dims[2] === 37) {
    channels = dims[2];
    anchors = dims[1];
  } else {
    throw new Error(`Unexpected prediction shape: ${dims.join('×')}`);
  }

  if (channels !== 37) {
    throw new Error('This page expects the supplied one-class YOLOv8 nail segmentation model.');
  }

  const candidates = [];
  const read = (anchor, channel) => channelFirst
    ? data[channel * anchors + anchor]
    : data[anchor * channels + channel];

  for (let anchor = 0; anchor < anchors; anchor++) {
    const score = read(anchor, 4);
    if (!Number.isFinite(score) || score < confidence) continue;

    const cx = read(anchor, 0);
    const cy = read(anchor, 1);
    const w = read(anchor, 2);
    const h = read(anchor, 3);
    if (![cx, cy, w, h].every(Number.isFinite) || w <= 1 || h <= 1) continue;

    const coeffs = new Float32Array(32);
    for (let c = 0; c < 32; c++) coeffs[c] = read(anchor, 5 + c);

    candidates.push({
      score,
      x1: cx - w / 2,
      y1: cy - h / 2,
      x2: cx + w / 2,
      y2: cy + h / 2,
      coeffs
    });
  }

  candidates.sort((a, b) => b.score - a.score);
  return candidates.slice(0, 300);
}

function nonMaximumSuppression(candidates, threshold, maximum) {
  const kept = [];
  for (const candidate of candidates) {
    if (kept.some(existing => boxIou(candidate, existing) > threshold)) continue;
    kept.push(candidate);
    if (kept.length >= maximum) break;
  }
  return kept;
}

function boxIou(a, b) {
  const x1 = Math.max(a.x1, b.x1);
  const y1 = Math.max(a.y1, b.y1);
  const x2 = Math.min(a.x2, b.x2);
  const y2 = Math.min(a.y2, b.y2);
  const iw = Math.max(0, x2 - x1);
  const ih = Math.max(0, y2 - y1);
  const intersection = iw * ih;
  const areaA = Math.max(0, a.x2 - a.x1) * Math.max(0, a.y2 - a.y1);
  const areaB = Math.max(0, b.x2 - b.x1) * Math.max(0, b.y2 - b.y1);
  return intersection / Math.max(areaA + areaB - intersection, 1e-8);
}

function buildMaskComponent(detection, prototypes, geometry, frameWidth, frameHeight, threshold) {
  const dims = prototypes.dims;
  const data = prototypes.data;
  let channels;
  let protoH;
  let protoW;
  let channelFirst = true;

  if (dims[1] === 32) {
    channels = dims[1];
    protoH = dims[2];
    protoW = dims[3];
  } else if (dims[3] === 32) {
    channels = dims[3];
    protoH = dims[1];
    protoW = dims[2];
    channelFirst = false;
  } else {
    throw new Error(`Unexpected prototype shape: ${dims.join('×')}`);
  }

  if (channels !== 32) return null;

  const protoPixels = protoW * protoH;
  smallMaskCanvas.width = protoW;
  smallMaskCanvas.height = protoH;
  const imageData = smallMaskCtx.createImageData(protoW, protoH);
  const rgba = imageData.data;
  let nonZero = 0;

  for (let p = 0; p < protoPixels; p++) {
    let logit = 0;
    for (let c = 0; c < 32; c++) {
      const protoValue = channelFirst
        ? data[c * protoPixels + p]
        : data[p * 32 + c];
      logit += detection.coeffs[c] * protoValue;
    }
    const probability = 1 / (1 + Math.exp(-Math.max(-40, Math.min(40, logit))));
    const alpha = probability >= threshold ? 255 : 0;
    if (alpha) nonZero++;
    const i = p * 4;
    rgba[i] = 255;
    rgba[i + 1] = 255;
    rgba[i + 2] = 255;
    rgba[i + 3] = alpha;
  }

  if (nonZero < 8) return null;
  smallMaskCtx.putImageData(imageData, 0, 0);

  modelMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  modelMaskCtx.clearRect(0, 0, MODEL_SIZE, MODEL_SIZE);
  modelMaskCtx.imageSmoothingEnabled = true;
  modelMaskCtx.drawImage(smallMaskCanvas, 0, 0, protoW, protoH, 0, 0, MODEL_SIZE, MODEL_SIZE);

  const x1 = clamp(Math.floor(detection.x1), 0, MODEL_SIZE);
  const y1 = clamp(Math.floor(detection.y1), 0, MODEL_SIZE);
  const x2 = clamp(Math.ceil(detection.x2), 0, MODEL_SIZE);
  const y2 = clamp(Math.ceil(detection.y2), 0, MODEL_SIZE);
  if (x2 <= x1 || y2 <= y1) return null;

  modelMaskCtx.clearRect(0, 0, MODEL_SIZE, y1);
  modelMaskCtx.clearRect(0, y2, MODEL_SIZE, MODEL_SIZE - y2);
  modelMaskCtx.clearRect(0, y1, x1, y2 - y1);
  modelMaskCtx.clearRect(x2, y1, MODEL_SIZE - x2, y2 - y1);

  originalMaskCtx.setTransform(1, 0, 0, 1, 0, 0);
  originalMaskCtx.clearRect(0, 0, frameWidth, frameHeight);
  originalMaskCtx.drawImage(
    modelMaskCanvas,
    geometry.left,
    geometry.top,
    geometry.nw,
    geometry.nh,
    0,
    0,
    frameWidth,
    frameHeight
  );

  const ox1 = clamp(Math.floor(((detection.x1 - geometry.left) / geometry.nw) * frameWidth) - 3, 0, frameWidth - 1);
  const oy1 = clamp(Math.floor(((detection.y1 - geometry.top) / geometry.nh) * frameHeight) - 3, 0, frameHeight - 1);
  const ox2 = clamp(Math.ceil(((detection.x2 - geometry.left) / geometry.nw) * frameWidth) + 3, ox1 + 1, frameWidth);
  const oy2 = clamp(Math.ceil(((detection.y2 - geometry.top) / geometry.nh) * frameHeight) + 3, oy1 + 1, frameHeight);

  const cropWidth = Math.max(1, ox2 - ox1);
  const cropHeight = Math.max(1, oy2 - oy1);
  const crop = document.createElement('canvas');
  crop.width = cropWidth;
  crop.height = cropHeight;
  const cropCtx = crop.getContext('2d');
  cropCtx.drawImage(originalMaskCanvas, ox1, oy1, cropWidth, cropHeight, 0, 0, cropWidth, cropHeight);

  return {
    canvas: crop,
    x: ox1,
    y: oy1,
    width: cropWidth,
    height: cropHeight,
    centerX: (ox1 + ox2) / 2,
    centerY: (oy1 + oy2) / 2,
    score: detection.score,
    fingerIndex: -1,
    anchorTip: null,
    anchorDip: null
  };
}

function assignComponentToFinger(component, landmarks, width, height, usedFingers) {
  let best = null;

  fingers.forEach((finger, fingerIndex) => {
    if (usedFingers.has(fingerIndex)) return;

    const tip = landmarkToPoint(landmarks[finger.tip], width, height);
    const dip = landmarkToPoint(landmarks[finger.dip], width, height);
    const upperLength = distance(tip, dip);
    const componentDistance = Math.hypot(component.centerX - tip.x, component.centerY - tip.y);
    const maxDistance = Math.max(26, upperLength * (finger.name === 'thumb' ? 1.65 : 1.45));

    if (componentDistance > maxDistance) return;

    const normalized = componentDistance / Math.max(upperLength, 1);
    if (!best || normalized < best.normalized) {
      best = { fingerIndex, tip, dip, normalized };
    }
  });

  return best;
}

function cloneLandmarks(landmarks) {
  return landmarks.map(point => ({ x: point.x, y: point.y, z: point.z || 0 }));
}

function landmarkToPoint(landmark, width, height) {
  return { x: landmark.x * width, y: landmark.y * height };
}

function drawLandmarkFallback(landmarks, settings) {
  fingers.forEach((finger, index) => {
    const tip = landmarkToPoint(landmarks[finger.tip], currentFrameWidth, currentFrameHeight);
    const dip = landmarkToPoint(landmarks[finger.dip], currentFrameWidth, currentFrameHeight);
    const pip = landmarkToPoint(landmarks[finger.pip], currentFrameWidth, currentFrameHeight);
    const dx = tip.x - dip.x;
    const dy = tip.y - dip.y;
    const upperLength = Math.hypot(dx, dy);
    if (upperLength < 5) return;

    const unitX = dx / upperLength;
    const unitY = dy / upperLength;
    const jointLength = distance(dip, pip);
    let nailWidth = jointLength * 0.55 * settings.size * finger.widthBoost;
    let nailHeight = upperLength * 0.95 * settings.size * finger.heightBoost;

    if (finger.name === 'thumb') {
      nailWidth *= 1.15;
      nailHeight *= 0.9;
    }

    const depthAdjust = settings.depth * 0.004 * upperLength;
    const targetX = tip.x - unitX * upperLength * finger.shift - unitX * depthAdjust;
    const targetY = tip.y - unitY * upperLength * finger.shift - unitY * depthAdjust + settings.verticalOffset;
    const targetAngle = Math.atan2(dy, dx) - Math.PI / 2;
    const smooth = smoothNails[index];

    if (!smooth.ready) {
      Object.assign(smooth, { x: targetX, y: targetY, w: nailWidth, h: nailHeight, angle: targetAngle, ready: true });
    } else {
      smooth.x = lerp(smooth.x, targetX, 0.45);
      smooth.y = lerp(smooth.y, targetY, 0.45);
      smooth.w = lerp(smooth.w, nailWidth, 0.35);
      smooth.h = lerp(smooth.h, nailHeight, 0.35);
      smooth.angle = lerpAngle(smooth.angle, targetAngle, 0.25);
    }

    drawFallbackNail(smooth.x, smooth.y, smooth.w, smooth.h, smooth.angle, finger.name, settings);
  });
}

function drawFallbackNail(x, y, w, h, angle, fingerName, settings) {
  frameCtx.save();
  frameCtx.translate(x, y);
  frameCtx.rotate(angle);
  if (fingerName === 'thumb') frameCtx.rotate(-0.25);

  const gradient = frameCtx.createLinearGradient(0, -h / 2, 0, h / 2);
  gradient.addColorStop(0, lightenColor(settings.color, settings.finish === 'matte' ? 15 : 42));
  gradient.addColorStop(0.48, settings.color);
  gradient.addColorStop(1, darkenColor(settings.color, settings.finish === 'matte' ? 12 : 28));
  frameCtx.globalAlpha = settings.strength;
  frameCtx.fillStyle = gradient;
  createFallbackNailPath(frameCtx, 0, 0, w, h, settings.shape);
  frameCtx.fill();
  frameCtx.globalAlpha = 1;
  frameCtx.restore();
}

function createFallbackNailPath(context, x, y, w, h, shape) {
  context.beginPath();
  if (shape === 'square') {
    const r = w * 0.12;
    context.moveTo(x - w / 2 + r, y - h / 2);
    context.lineTo(x + w / 2 - r, y - h / 2);
    context.quadraticCurveTo(x + w / 2, y - h / 2, x + w / 2, y - h / 2 + r);
    context.lineTo(x + w / 2, y + h / 2 - r);
    context.quadraticCurveTo(x + w / 2, y + h / 2, x + w / 2 - r, y + h / 2);
    context.lineTo(x - w / 2 + r, y + h / 2);
    context.quadraticCurveTo(x - w / 2, y + h / 2, x - w / 2, y + h / 2 - r);
    context.lineTo(x - w / 2, y - h / 2 + r);
    context.quadraticCurveTo(x - w / 2, y - h / 2, x - w / 2 + r, y - h / 2);
    context.closePath();
    return;
  }

  if (shape === 'coffin') {
    context.moveTo(x - w * 0.32, y - h / 2);
    context.lineTo(x + w * 0.32, y - h / 2);
    context.lineTo(x + w * 0.50, y + h / 2);
    context.lineTo(x - w * 0.50, y + h / 2);
    context.closePath();
    return;
  }

  if (shape === 'stiletto') {
    context.moveTo(x, y - h / 2);
    context.lineTo(x + w * 0.48, y + h / 2);
    context.lineTo(x - w * 0.48, y + h / 2);
    context.closePath();
    return;
  }

  if (shape === 'round') {
    context.ellipse(x, y, w / 2, h / 2, 0, 0, Math.PI * 2);
    return;
  }

  context.moveTo(x, y - h / 2);
  context.bezierCurveTo(x + w * 0.46, y - h * 0.30, x + w * 0.42, y + h * 0.28, x + w * 0.25, y + h * 0.45);
  context.bezierCurveTo(x + w * 0.10, y + h * 0.55, x - w * 0.10, y + h * 0.55, x - w * 0.25, y + h * 0.45);
  context.bezierCurveTo(x - w * 0.42, y + h * 0.28, x - w * 0.46, y - h * 0.30, x, y - h / 2);
  context.closePath();
}

function drawDebugLandmarksOnFrame(landmarks) {
  frameCtx.save();
  frameCtx.fillStyle = '#00f59b';
  for (const finger of fingers) {
    for (const index of [finger.tip, finger.dip, finger.pip]) {
      const point = landmarkToPoint(landmarks[index], currentFrameWidth, currentFrameHeight);
      frameCtx.beginPath();
      frameCtx.arc(point.x, point.y, 4, 0, Math.PI * 2);
      frameCtx.fill();
    }
  }
  frameCtx.restore();
}

function capturePreview() {
  if (!latestImage) {
    alert('Start the camera first.');
    return;
  }
  capturedImage.src = latestImage;
  capturedImage.classList.remove('hidden');
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
    const response = await fetch('backend/save-design.php', {
      method: 'POST',
      body: form,
      headers: { Accept: 'application/json' }
    });
    const result = await response.json();
    arMessage.className = `alert ${result.status}`;
    arMessage.textContent = result.message;
    if (result.status === 'success') {
      localStorage.setItem('savedNailTryOnDesign', JSON.stringify(settings));
    }
  } catch (error) {
    arMessage.className = 'alert error';
    arMessage.textContent = 'Design could not be saved.';
  }
}

function clamp(value, min, max) {
  return Math.max(min, Math.min(max, value));
}

function distance(a, b) {
  return Math.hypot(a.x - b.x, a.y - b.y);
}

function lerp(a, b, t) {
  return a + (b - a) * t;
}

function lerpAngle(a, b, t) {
  return a + normalizeAngle(b - a) * t;
}

function normalizeAngle(angle) {
  while (angle > Math.PI) angle -= Math.PI * 2;
  while (angle < -Math.PI) angle += Math.PI * 2;
  return angle;
}

function lightenColor(hex, amount) {
  const color = parseInt(hex.replace('#', ''), 16);
  const r = Math.min(255, (color >> 16) + amount);
  const g = Math.min(255, ((color >> 8) & 255) + amount);
  const b = Math.min(255, (color & 255) + amount);
  return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

function darkenColor(hex, amount) {
  const color = parseInt(hex.replace('#', ''), 16);
  const r = Math.max(0, (color >> 16) - amount);
  const g = Math.max(0, ((color >> 8) & 255) - amount);
  const b = Math.max(0, (color & 255) - amount);
  return `#${((1 << 24) + (r << 16) + (g << 8) + b).toString(16).slice(1)}`;
}

window.addEventListener('pagehide', () => {
  try {
    camera?.stop?.();
  } catch (_) {}
  try {
    video.srcObject?.getTracks?.().forEach(track => track.stop());
  } catch (_) {}
});
