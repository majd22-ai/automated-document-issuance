"""Local technical screening only. No face recognition, identity or authenticity claims.

Protocol: image path in argv[1], trusted thresholds on stdin, one JSON result on stdout.
No photographs, face crops, embeddings, or biometric templates are saved.
"""
import json
import os
import sys

os.environ.setdefault("OPENCV_IO_MAX_IMAGE_PIXELS", "20000000")


def analyze(image, config, detector):
    import cv2
    import numpy as np

    errors = []
    if image is None or image.ndim != 3 or image.shape[0] * image.shape[1] > config["max_pixels"]:
        return {"version": 1, "status": "completed", "accepted": False, "errors": ["unreadable"]}
    height, width = image.shape[:2]
    scale = min(1.0, 1000 / max(height, width))
    if scale < 1:
        image = cv2.resize(image, None, fx=scale, fy=scale, interpolation=cv2.INTER_AREA)
    height, width = image.shape[:2]
    gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
    faces = detector.detectMultiScale(cv2.equalizeHist(gray), scaleFactor=1.1, minNeighbors=5,
                                     minSize=(max(50, width // 12), max(50, height // 12)))
    metrics = {"faces": len(faces)}
    if len(faces) != 1:
        errors.append("no_face" if len(faces) == 0 else "multiple_faces")
    else:
        x, y, w, h = map(int, faces[0])
        face = cv2.resize(gray[y:y+h, x:x+w], (256, 256), interpolation=cv2.INTER_AREA)
        blur = float(cv2.Laplacian(face, cv2.CV_64F).var())
        brightness = float(face.mean())
        left, right = float(face[:, :128].mean()), float(face[:, 128:].mean())
        top, bottom = float(face[:128, :].mean()), float(face[128:, :].mean())
        difference = max(abs(left - right), abs(top - bottom))
        metrics.update(blur=round(blur, 2), brightness=round(brightness, 2), lighting_difference=round(difference, 2))
        if blur < config["blur_min"]:
            errors.append("blur")
        if brightness < config["brightness_min"] or float((face < 40).mean()) > config["dark_fraction_max"]:
            errors.append("dark")
        if brightness > config["brightness_max"] or float((face > 245).mean()) > config["bright_fraction_max"]:
            errors.append("bright")
        if difference > config["lighting_difference_max"]:
            errors.append("lighting")
    # Sample outer background, not the entire image (skin/clothing are not white).
    mask = np.zeros((height, width), dtype=np.uint8)
    mask[:max(1, int(height * .12)), :] = 255
    mask[:int(height * .65), :max(1, int(width * .12))] = 255
    mask[:int(height * .65), int(width * .88):] = 255
    for x, y, w, h in faces:
        margin = int(w * .25)
        mask[max(0, y-margin):min(height, y+h+margin), max(0, x-margin):min(width, x+w+margin)] = 0
    hsv = cv2.cvtColor(image, cv2.COLOR_BGR2HSV)
    white = cv2.inRange(hsv, (0, 0, config["white_value_min"]), (179, config["white_saturation_max"], 255))
    sample = mask > 0
    if not sample.any():
        errors.append("background")
    else:
        ratio = float((white[sample] > 0).mean())
        spread = float(hsv[:, :, 2][sample].std())
        metrics.update(background_white=round(ratio, 3), background_std=round(spread, 2))
        if ratio < config["background_white_min"] or spread > config["background_std_max"]:
            errors.append("background")
    return {"version": 1, "status": "completed", "accepted": not errors, "errors": errors, "metrics": metrics}


def main():
    try:
        import cv2
        import numpy as np

        cv2.setNumThreads(1)
        config = json.load(sys.stdin)
        # Unicode paths on Windows are decoded through numpy instead of cv2.imread.
        image = cv2.imdecode(np.fromfile(sys.argv[1], dtype=np.uint8), cv2.IMREAD_COLOR)
        detector = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
        if detector.empty():
            raise RuntimeError("Face detector unavailable")
        result = analyze(image, config, detector)
        print(json.dumps(result))
        return 0
    except Exception:
        # Never leak paths, image contents, or raw exception details through the bridge.
        print(json.dumps({"version": 1, "status": "unavailable"}))
        return 2


if __name__ == "__main__":
    sys.exit(main())
