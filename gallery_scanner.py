#!/usr/bin/env python3
"""
gallery_scanner.py
==================
OpticVault — Background Content Detective

يعمل هذا الملف كمحقق صامت في الخلفية:
  - يجلب صور المعرض العام من Laravel API
  - يفحص كل صورة بطبقتين:
      Layer 1: Skin Tone Heuristic (سريع، بلا AI)
      Layer 2: ai_filter.py (فحص أعمق)
  - إذا وجد مخالفة → يُبلّغ عنها للـ Laravel تلقائياً
  - يعمل كل 30 دقيقة بدون تدخل بشري

Usage:
  python3 gallery_scanner.py
"""

import os
import sys
import time
import json
import logging
import hashlib
import requests
import subprocess
import tempfile
from PIL import Image

# ──────────────────────────────────────────────────────────────
# CONFIGURATION
# ──────────────────────────────────────────────────────────────
LARAVEL_BASE_URL  = os.environ.get("LARAVEL_URL", "http://127.0.0.1:8000")
SCANNER_API_KEY   = os.environ.get("SCANNER_API_KEY", "lVV0HMvLY4UsVwnJBQSECrOGpIB9hzs5")
AI_FILTER_SCRIPT  = os.path.join(os.path.dirname(__file__), "scripts", "ai_filter.py")
PYTHON_BIN        = sys.executable
SCAN_INTERVAL_SEC = 30 * 60  # 30 minutes between full scans
LOG_FILE          = os.path.join(os.path.dirname(__file__), "scanner.log")

# ──────────────────────────────────────────────────────────────
# LOGGING SETUP
# ──────────────────────────────────────────────────────────────
logging.basicConfig(
    level=logging.INFO,
    format="%(asctime)s [%(levelname)s] %(message)s",
    handlers=[
        logging.FileHandler(LOG_FILE),
        logging.StreamHandler(sys.stdout),
    ],
)
log = logging.getLogger("GalleryScanner")

# ──────────────────────────────────────────────────────────────
# LAYER 1: Skin Tone Heuristic (no AI, pure Pillow)
# ──────────────────────────────────────────────────────────────
def _is_skin_ycbcr(y, cb, cr) -> bool:
    return (77 <= cb <= 127) and (133 <= cr <= 173) and (y > 80)


def layer1_skin_heuristic(image_path: str) -> str:
    """Returns: SAFE / SUSPICIOUS / UNSAFE"""
    try:
        with Image.open(image_path) as img:
            if img.width < 50 or img.height < 50:
                return "SAFE"
            img = img.convert("YCbCr")
            img.thumbnail((120, 120))
            w, h = img.size
            pixels = img.load()
            total = skin = 0
            for x in range(0, w, 2):
                for y in range(0, h, 2):
                    yv, cb, cr = pixels[x, y]
                    total += 1
                    if _is_skin_ycbcr(yv, cb, cr):
                        skin += 1
            if total == 0:
                return "SAFE"
            ratio = skin / total
            if ratio > 0.55:
                return "UNSAFE"
            if ratio > 0.35:
                return "SUSPICIOUS"
            return "SAFE"
    except Exception as e:
        log.warning(f"Layer1 error: {e}")
        return "ERROR"


# ──────────────────────────────────────────────────────────────
# LAYER 2: ai_filter.py (local Python ML)
# ──────────────────────────────────────────────────────────────
def layer2_ai_filter(image_path: str) -> str:
    """Returns: SAFE / SUSPICIOUS / UNSAFE / ERROR / SKIPPED"""
    if not os.path.exists(AI_FILTER_SCRIPT):
        return "SKIPPED"
    try:
        result = subprocess.run(
            [PYTHON_BIN, AI_FILTER_SCRIPT, image_path],
            capture_output=True, text=True, timeout=15
        )
        return result.stdout.strip() if result.returncode == 0 else "ERROR"
    except Exception as e:
        log.warning(f"Layer2 error: {e}")
        return "ERROR"


# ──────────────────────────────────────────────────────────────
# REPORT TO LARAVEL
# ──────────────────────────────────────────────────────────────
def report_violation(image_id: str, reason: str, details: str) -> bool:
    try:
        resp = requests.post(
            f"{LARAVEL_BASE_URL}/api/internal/scanner/report",
            headers={
                "X-Scanner-Key": SCANNER_API_KEY,
                "Content-Type": "application/json",
                "Accept": "application/json",
            },
            json={"image_id": image_id, "reason": reason, "details": details},
            timeout=10,
        )
        if resp.status_code in (200, 201):
            log.info(f"  ✓ Report accepted for image {image_id}")
            return True
        else:
            log.warning(f"  Report rejected ({resp.status_code}): {resp.text[:200]}")
            return False
    except Exception as e:
        log.error(f"  Report request failed: {e}")
        return False


# ──────────────────────────────────────────────────────────────
# FETCH PUBLIC IMAGES FROM LARAVEL
# ──────────────────────────────────────────────────────────────
def fetch_public_images() -> list:
    try:
        resp = requests.get(
            f"{LARAVEL_BASE_URL}/api/internal/scanner/images",
            headers={
                "X-Scanner-Key": SCANNER_API_KEY,
                "Accept": "application/json",
            },
            timeout=15,
        )
        if resp.status_code == 200:
            return resp.json()
        log.warning(f"Failed to fetch images: {resp.status_code}")
        return []
    except Exception as e:
        log.error(f"fetch_public_images error: {e}")
        return []


# ──────────────────────────────────────────────────────────────
# INSPECT A SINGLE IMAGE
# ──────────────────────────────────────────────────────────────
def inspect_image(image_record: dict):
    image_id  = image_record.get("id")
    filename  = image_record.get("filename", "unknown")
    image_url = image_record.get("s3_key")  # could be a path or URL

    if not image_url:
        log.debug(f"  Skipping {image_id} — no URL/path available")
        return

    log.info(f"Inspecting: {filename} ({image_id})")

    # Download to a temp file for inspection
    tmp_path = None
    try:
        with tempfile.NamedTemporaryFile(delete=False, suffix=os.path.splitext(filename)[-1]) as tmp:
            tmp_path = tmp.name
            # Try to download the image — handles both http URLs and local paths
            if image_url.startswith("http"):
                response = requests.get(image_url, timeout=20)
                tmp.write(response.content)
            else:
                # Local file path (LocalStack S3 mount, or local disk)
                local = os.path.join("/home/toshiba/Graduation_project/OpticVault/storage/app", image_url.lstrip("/"))
                if not os.path.exists(local):
                    log.debug(f"  Local file not found: {local}")
                    return
                with open(local, "rb") as f:
                    tmp.write(f.read())

        # ── Layer 1 ──
        l1 = layer1_skin_heuristic(tmp_path)
        log.info(f"  [Layer 1] Skin Heuristic: {l1}")

        # ── Layer 2 ──
        l2 = layer2_ai_filter(tmp_path)
        log.info(f"  [Layer 2] AI Filter: {l2}")

        # ── Decision ──
        if l1 == "UNSAFE" or l2 == "UNSAFE":
            report_violation(
                image_id,
                reason="Nudity/Violence/Other",
                details=f"Auto-detected by scanner. Layer1={l1}, Layer2={l2}"
            )
        elif l1 == "SUSPICIOUS" or l2 == "SUSPICIOUS":
            report_violation(
                image_id,
                reason="Suspicious Content",
                details=f"Needs review. Layer1={l1}, Layer2={l2}"
            )
        else:
            log.info(f"  ✓ Clean — no violation.")

    except Exception as e:
        log.error(f"  Error inspecting {image_id}: {e}")
    finally:
        if tmp_path and os.path.exists(tmp_path):
            os.remove(tmp_path)


# ──────────────────────────────────────────────────────────────
# MAIN SCANNER LOOP
# ──────────────────────────────────────────────────────────────
def scan_gallery():
    log.info("═══════════════════════════════════════")
    log.info("  OpticVault Gallery Scanner — Starting")
    log.info("═══════════════════════════════════════")

    images = fetch_public_images()
    if not images:
        log.info("No public images to scan, or API unreachable.")
        return

    log.info(f"Fetched {len(images)} public images to inspect...")
    for image_record in images:
        inspect_image(image_record)
        time.sleep(0.5)  # gentle delay — don't hammer the system

    log.info("Scan complete.\n")


def main():
    while True:
        scan_gallery()
        log.info(f"Next scan in {SCAN_INTERVAL_SEC // 60} minutes.\n")
        time.sleep(SCAN_INTERVAL_SEC)


if __name__ == "__main__":
    main()
