import * as THREE from "https://cdn.jsdelivr.net/npm/three@0.164.1/build/three.module.js";

const video = document.getElementById("inputVideo");
const canvas = document.getElementById("threeCanvas");
const wrapper = document.getElementById("arWrapper");

const startBtn = document.getElementById("startBtn");
const captureBtn = document.getElementById("captureBtn");
const saveBtn = document.getElementById("saveBtn");
const capturedImage = document.getElementById("capturedImage");

const nailColorInput = document.getElementById("nailColor");
const nailShapeInput = document.getElementById("nailShape");
const nailFinishInput = document.getElementById("nailFinish");
const nailSizeInput = document.getElementById("nailSize");
const verticalOffsetInput = document.getElementById("verticalOffset");
const nailDepthInput = document.getElementById("nailDepth");
const showDebugInput = document.getElementById("showDebug");

let scene;
let camera3D;
let renderer;
let hands;
let mediaPipeCamera;
let nailMeshes = [];
let debugMeshes = [];
let latestFrameReady = false;

const fingers = [
  { name: "thumb", tip: 4, base: 3, widthBoost: 1.15, heightBoost: 0.9 },
  { name: "index", tip: 8, base: 6, widthBoost: 1.0, heightBoost: 1.0 },
  { name: "middle", tip: 12, base: 10, widthBoost: 1.05, heightBoost: 1.1 },
  { name: "ring", tip: 16, base: 14, widthBoost: 0.95, heightBoost: 1.0 },
  { name: "pinky", tip: 20, base: 18, widthBoost: 0.8, heightBoost: 0.85 }
];

startBtn.addEventListener("click", startAR);
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

function initThree() {
  scene = new THREE.Scene();

  const width = wrapper.clientWidth;
  const height = wrapper.clientHeight;

  camera3D = new THREE.OrthographicCamera(
    width / -2,
    width / 2,
    height / 2,
    height / -2,
    -1000,
    1000
  );

  camera3D.position.z = 500;

  renderer = new THREE.WebGLRenderer({
    canvas: canvas,
    alpha: true,
    antialias: true,
    preserveDrawingBuffer: true
  });

  renderer.setPixelRatio(window.devicePixelRatio);
  renderer.setSize(width, height);

  const ambientLight = new THREE.AmbientLight(0xffffff, 1.4);
  scene.add(ambientLight);

  const mainLight = new THREE.DirectionalLight(0xffffff, 1.8);
  mainLight.position.set(120, 180, 300);
  scene.add(mainLight);

  const sideLight = new THREE.DirectionalLight(0xffc9df, 0.9);
  sideLight.position.set(-200, 80, 150);
  scene.add(sideLight);

  createNailMeshes();
  createDebugMeshes();

  window.addEventListener("resize", resizeThree);
}

function resizeThree() {
  const width = wrapper.clientWidth;
  const height = wrapper.clientHeight;

  camera3D.left = width / -2;
  camera3D.right = width / 2;
  camera3D.top = height / 2;
  camera3D.bottom = height / -2;
  camera3D.updateProjectionMatrix();

  renderer.setSize(width, height);
}

function createNailMeshes() {
  nailMeshes.forEach(mesh => scene.remove(mesh));
  nailMeshes = [];

  for (let i = 0; i < 5; i++) {
    const mesh = createNailMesh();
    mesh.visible = false;
    nailMeshes.push(mesh);
    scene.add(mesh);
  }
}

function createNailMesh() {
  const geometry = createNailGeometry(getSettings().shape);

  const material = createNailMaterial();

  const mesh = new THREE.Mesh(geometry, material);

  mesh.rotation.x = THREE.MathUtils.degToRad(20);
  mesh.renderOrder = 10;

  return mesh;
}

function createNailGeometry(shape) {
  if (shape === "square") {
    return new THREE.BoxGeometry(1, 1.4, 0.22, 4, 8, 2);
  }

  if (shape === "stiletto") {
    return new THREE.ConeGeometry(0.52, 1.7, 32, 1);
  }

  if (shape === "coffin") {
    const shape2D = new THREE.Shape();
    shape2D.moveTo(-0.32, -0.7);
    shape2D.lineTo(0.32, -0.7);
    shape2D.lineTo(0.5, 0.7);
    shape2D.lineTo(-0.5, 0.7);
    shape2D.lineTo(-0.32, -0.7);

    return new THREE.ExtrudeGeometry(shape2D, {
      depth: 0.18,
      bevelEnabled: true,
      bevelThickness: 0.08,
      bevelSize: 0.06,
      bevelSegments: 4
    });
  }

  if (shape === "round") {
    return new THREE.SphereGeometry(0.55, 32, 32, 0, Math.PI * 2, 0, Math.PI);
  }

  // almond default
  const almond = new THREE.Shape();
  almond.moveTo(0, -0.85);
  almond.quadraticCurveTo(0.55, -0.35, 0.42, 0.65);
  almond.quadraticCurveTo(0, 0.85, -0.42, 0.65);
  almond.quadraticCurveTo(-0.55, -0.35, 0, -0.85);

  return new THREE.ExtrudeGeometry(almond, {
    depth: 0.2,
    bevelEnabled: true,
    bevelThickness: 0.08,
    bevelSize: 0.06,
    bevelSegments: 5
  });
}

function createNailMaterial() {
  const settings = getSettings();

  let roughness = 0.22;
  let metalness = 0.08;
  let clearcoat = 1.0;

  if (settings.finish === "chrome") {
    roughness = 0.12;
    metalness = 0.85;
    clearcoat = 1.0;
  }

  if (settings.finish === "matte") {
    roughness = 0.85;
    metalness = 0.02;
    clearcoat = 0.1;
  }

  if (settings.finish === "pearl") {
    roughness = 0.3;
    metalness = 0.25;
    clearcoat = 1.0;
  }

  return new THREE.MeshPhysicalMaterial({
    color: new THREE.Color(settings.color),
    roughness: roughness,
    metalness: metalness,
    clearcoat: clearcoat,
    clearcoatRoughness: 0.12,
    transparent: true,
    opacity: 0.96
  });
}

function recreateNailsIfShapeChanged() {
  const selectedShape = getSettings().shape;

  nailMeshes.forEach((mesh, index) => {
    if (mesh.userData.shape !== selectedShape) {
      scene.remove(mesh);

      const newMesh = createNailMesh();
      newMesh.userData.shape = selectedShape;
      newMesh.visible = false;

      nailMeshes[index] = newMesh;
      scene.add(newMesh);
    }
  });
}

function updateNailMaterials() {
  const material = createNailMaterial();

  nailMeshes.forEach(mesh => {
    mesh.material.dispose();
    mesh.material = material.clone();
  });
}

function createDebugMeshes() {
  for (let i = 0; i < 21; i++) {
    const geometry = new THREE.SphereGeometry(4, 12, 12);
    const material = new THREE.MeshBasicMaterial({ color: 0x00ff99 });
    const dot = new THREE.Mesh(geometry, material);
    dot.visible = false;
    debugMeshes.push(dot);
    scene.add(dot);
  }
}

async function startAR() {
  if (!scene) {
    initThree();
  }

  if (hands) {
    alert("AR camera is already running.");
    return;
  }

  hands = new window.Hands({
    locateFile: function(file) {
      return "https://cdn.jsdelivr.net/npm/@mediapipe/hands/" + file;
    }
  });

  hands.setOptions({
    maxNumHands: 1,
    modelComplexity: 1,
    minDetectionConfidence: 0.7,
    minTrackingConfidence: 0.7
  });

  hands.onResults(onResults);

  mediaPipeCamera = new window.Camera(video, {
    onFrame: async function() {
      await hands.send({ image: video });
    },
    width: 960,
    height: 720
  });

  await mediaPipeCamera.start();
}

function onResults(results) {
  if (!renderer || !scene || !camera3D) {
    return;
  }

  const width = wrapper.clientWidth;
  const height = wrapper.clientHeight;

  renderer.setSize(width, height);

  if (video.readyState >= 2) {
    latestFrameReady = true;
  }

  recreateNailsIfShapeChanged();
  updateNailMaterials();

  if (results.multiHandLandmarks && results.multiHandLandmarks.length > 0) {
    const landmarks = results.multiHandLandmarks[0];
    updateNailsFromLandmarks(landmarks, width, height);
    updateDebugPoints(landmarks, width, height);
  } else {
    hideNails();
    hideDebugPoints();
  }

  renderer.render(scene, camera3D);
}

function updateNailsFromLandmarks(landmarks, width, height) {
  const settings = getSettings();

  fingers.forEach((finger, index) => {
    const tip = landmarks[finger.tip];
    const base = landmarks[finger.base];

    const tipPoint = landmarkToScreenPoint(tip, width, height);
    const basePoint = landmarkToScreenPoint(base, width, height);

    const dx = tipPoint.x - basePoint.x;
    const dy = tipPoint.y - basePoint.y;

    const fingerLength = Math.sqrt(dx * dx + dy * dy);

    const unitX = dx / fingerLength;
    const unitY = dy / fingerLength;

    const nailWidth = fingerLength * 0.32 * settings.size * finger.widthBoost;
    const nailHeight = fingerLength * 0.55 * settings.size * finger.heightBoost;

    //const angle = Math.atan2(dy, dx) - Math.PI / 2;
    const angle = 0;

    /*
      The nail should not sit exactly on the fingertip.
      It should move slightly backward toward the finger base.
    */
    const centerX = tipPoint.x - unitX * nailHeight * 0.25;
    const centerY = tipPoint.y - unitY * nailHeight * 0.25 + settings.verticalOffset;

    const mesh = nailMeshes[index];

    mesh.visible = true;

    mesh.position.set(
      centerX,
      centerY,
      settings.depth
    );

    mesh.scale.set(
      nailWidth,
      nailHeight,
      Math.max(nailWidth, nailHeight) * 0.14
    );

   mesh.rotation.set(
  THREE.MathUtils.degToRad(18),
  THREE.MathUtils.degToRad(0),
  0
);

if (finger.name === "thumb") {
  mesh.rotation.z = THREE.MathUtils.degToRad(-18);
}
  });
}

function landmarkToScreenPoint(landmark, width, height) {
  const x = (0.5 - landmark.x) * width;
  const y = (0.5 - landmark.y) * height;

  return {
    x: x,
    y: y,
    z: landmark.z || 0
  };
}

function updateDebugPoints(landmarks, width, height) {
  const settings = getSettings();

  if (!settings.showDebug) {
    hideDebugPoints();
    return;
  }

  landmarks.forEach((landmark, index) => {
    const point = landmarkToScreenPoint(landmark, width, height);
    debugMeshes[index].visible = true;
    debugMeshes[index].position.set(point.x, point.y, 60);
  });
}

function hideNails() {
  nailMeshes.forEach(mesh => {
    mesh.visible = false;
  });
}

function hideDebugPoints() {
  debugMeshes.forEach(dot => {
    dot.visible = false;
  });
}

function capturePreview() {
  if (!latestFrameReady) {
    alert("Start the camera first.");
    return;
  }

  const captureCanvas = document.createElement("canvas");
  const context = captureCanvas.getContext("2d");

  const width = wrapper.clientWidth;
  const height = wrapper.clientHeight;

  captureCanvas.width = width;
  captureCanvas.height = height;

  context.save();
  context.translate(width, 0);
  context.scale(-1, 1);
  context.drawImage(video, 0, 0, width, height);
  context.restore();

  context.drawImage(canvas, 0, 0, width, height);

  capturedImage.src = captureCanvas.toDataURL("image/png");
  capturedImage.classList.remove("hidden");
}

function saveDesign() {
  const settings = getSettings();

  localStorage.setItem("saved3DNailDesign", JSON.stringify(settings));

  alert("3D nail design saved!");
}