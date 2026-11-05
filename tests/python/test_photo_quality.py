"""Run with: python -m unittest discover -s tests/python -v

Numerical screening fixtures are synthetic; they do not prove real-photo accuracy.
"""
import importlib.util
import json
from pathlib import Path
import unittest

import cv2
import numpy as np

ROOT = Path(__file__).resolve().parents[2]
spec = importlib.util.spec_from_file_location("photo_quality", ROOT / "scripts/photo_quality.py")
quality = importlib.util.module_from_spec(spec)
spec.loader.exec_module(quality)


class Detector:
    def __init__(self, count=1):
        self.count = count

    def detectMultiScale(self, *args, **kwargs):
        return [(180, 180, 240, 240)] * self.count


class PhotoQualityTests(unittest.TestCase):
    def setUp(self):
        self.config = dict(blur_min=60, brightness_min=65, brightness_max=235,
                           dark_fraction_max=.35, bright_fraction_max=.45,
                           lighting_difference_max=55, white_saturation_max=40,
                           white_value_min=205, background_white_min=.8,
                           background_std_max=25, max_pixels=20000000)
        self.image = np.full((600, 600, 3), 255, np.uint8)
        rng = np.random.default_rng(42)
        self.image[180:420, 180:420] = rng.integers(100, 180, (240, 240, 3), dtype=np.uint8)

    def check(self, image=None, count=1):
        return quality.analyze(self.image if image is None else image, self.config, Detector(count))

    def test_technical_success(self):
        self.assertTrue(self.check()["accepted"])

    def test_face_count(self):
        self.assertIn("no_face", self.check(count=0)["errors"])
        self.assertIn("multiple_faces", self.check(count=2)["errors"])

    def test_blur(self):
        image = cv2.GaussianBlur(self.image, (51, 51), 15)
        self.assertIn("blur", self.check(image)["errors"])

    def test_dark_bright_and_shadows(self):
        image = self.image.copy()
        image[180:420, 180:420] = 20
        self.assertIn("dark", self.check(image)["errors"])
        image[180:420, 180:420] = 250
        self.assertIn("bright", self.check(image)["errors"])
        image[180:420, 180:300] = 50
        image[180:420, 300:420] = 180
        self.assertIn("lighting", self.check(image)["errors"])

    def test_colored_and_nonuniform_background(self):
        image = self.image.copy()
        image[:70] = (255, 0, 0)
        self.assertIn("background", self.check(image)["errors"])
        image = self.image.copy()
        image[:70, :300] = 80
        self.assertIn("background", self.check(image)["errors"])

    def test_unreadable(self):
        self.assertEqual(["unreadable"], quality.analyze(None, self.config, Detector())["errors"])

    def test_real_detector_on_blank_image_does_not_find_a_face(self):
        detector = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
        result = quality.analyze(np.full((600, 600, 3), 255, np.uint8), self.config, detector)
        self.assertIn("no_face", result["errors"])

    @unittest.skipUnless(importlib.util.find_spec("matplotlib"), "Optional bundled public sample unavailable")
    def test_real_detector_on_one_and_two_bundled_public_portraits(self):
        import matplotlib

        sample = Path(matplotlib.get_data_path()) / "sample_data" / "grace_hopper.jpg"
        if not sample.is_file():
            self.skipTest("Bundled public sample unavailable")
        image = cv2.imdecode(np.fromfile(str(sample), dtype=np.uint8), cv2.IMREAD_COLOR)
        detector = cv2.CascadeClassifier(cv2.data.haarcascades + "haarcascade_frontalface_default.xml")
        one = quality.analyze(image, self.config, detector)
        two = quality.analyze(np.concatenate([image, image], axis=1), self.config, detector)
        self.assertEqual(1, one["metrics"]["faces"])
        self.assertIn("multiple_faces", two["errors"])


if __name__ == "__main__":
    unittest.main()
