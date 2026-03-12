#!/usr/bin/env python3
import sys
import os
from PIL import Image

# --- Skin detection in YCbCr ---
def is_skin(y, cb, cr):
    return (
        77 <= cb <= 127 and
        133 <= cr <= 173 and
        y > 80
    )

def check_nsfw(image_path):
    try:

        # Check file exists
        if not os.path.exists(image_path):
            return "ERROR"

        with Image.open(image_path) as img:

            # Ignore extremely small images
            if img.width < 50 or img.height < 50:
                return "SAFE"

            # Convert color space
            img = img.convert("YCbCr")

            # Resize for performance
            img.thumbnail((120, 120))

            width, height = img.size
            pixels = img.load()

            total = 0
            skin_pixels = 0

            # Sample every 2 pixels for speed
            for x in range(0, width, 2):
                for y in range(0, height, 2):
                    y_val, cb, cr = pixels[x, y]

                    total += 1

                    if is_skin(y_val, cb, cr):
                        skin_pixels += 1

            if total == 0:
                return "ERROR"

            skin_ratio = skin_pixels / total

            # --- Decision logic ---
            if skin_ratio > 0.55:
                return "UNSAFE"

            elif skin_ratio > 0.35:
                return "SUSPICIOUS"

            else:
                return "SAFE"

    except Exception:
        return "ERROR"


if __name__ == "__main__":

    if len(sys.argv) < 2:
        print("ERROR")
        sys.exit(1)

    result = check_nsfw(sys.argv[1])
    print(result)
