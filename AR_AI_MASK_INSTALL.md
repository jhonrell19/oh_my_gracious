# OH MY GRACIOUS — AI Nail Mask Upgrade

This upgrade replaces the default 2D fingertip sticker approach with a hybrid live pipeline:

1. MediaPipe Hands tracks the hand and individual fingers.
2. `models/nail-seg.onnx` detects actual nail pixels.
3. Each detected nail mask is matched to the nearest tracked finger.
4. MediaPipe keeps the latest AI mask attached between slower AI inference updates.
5. Color/finish is blended only inside the detected nail mask.
6. Edge Coverage Boost and Edge Softness help fill small boundary gaps without enlarging the old oval overlay.

## Installation

No database migration is required for this AR-only update.

Copy the upgraded files over your current project while preserving `.env` and `.git`.

Confirm this model exists:

`C:\xampp\htdocs\oh_my_gracious\models\nail-seg.onnx`

The model is about 46 MB.

Start Apache + MySQL and open:

`http://localhost/oh_my_gracious/ar-3d-nails.php`

The page uses ONNX Runtime Web and MediaPipe from pinned CDN scripts, so the first load requires internet access. The nail model itself is hosted locally by Apache and inference runs in the browser. Camera frames are not sent to an external AI server by this implementation.

## First test settings

- Detection Confidence: 20%
- Mask Coverage: 42%
- Edge Coverage Boost: 1 px
- Edge Softness: 1.2 px
- Diagnostic View: Polish Preview
- Landmark fallback: OFF

Use bright front lighting and hold the back of one hand toward the camera. Keep nails large enough in the camera view.

If a nail is missed, try Detection Confidence around 15–18%. If the mask stops short of the nail edge, lower Mask Coverage slightly (for example 38–40%) or raise Edge Coverage Boost to 2 px. If color spills onto skin, raise Mask Coverage and reduce Edge Coverage Boost.

Turn Diagnostic View to `Show AI Mask in Green` to see exactly what the model is detecting. This separates model errors from rendering errors.

## Important limitation

The supplied `nail-seg.onnx` is the same YOLOv8 nail segmentation model previously tested in the separate prototype. It can improve masking substantially when it detects the nail correctly, but it was not previously proven reliable for every lighting condition, hand angle, nail shape, or skin tone. If the green diagnostic mask itself is wrong, changing color/finish code will not fix it; the next step is retraining or replacing the segmentation model with a stronger nail dataset.
