#!/usr/bin/env python3
import sys
import os
from PIL import Image
from datadog import initialize, statsd

# Datadog configuration
options = {
    'statsd_host': '127.0.0.1',
    'statsd_port': 8125
}

initialize(**options)

def check_skin_tone(image_path):
    """
    Simple heuristic to check for excessive skin tones using Pillow.
    This mimics the logic in ContentSafetyService.php.
    """
    try:
        with Image.open(image_path) as img:
            img = img.resize((50, 50))
            width, height = img.size
            total_pixels = width * height
            skin_pixels = 0
            
            rgb_img = img.convert('RGB')
            
            for x in range(width):
                for y in range(height):
                    r, g, b = rgb_img.getpixel((x, y))
                    
                    # Heuristic skin color detection
                    if (r > 95 and g > 40 and b > 20 and 
                        max(r, g, b) - min(r, g, b) > 15 and 
                        abs(r - g) > 15 and r > g and r > b):
                        skin_pixels += 1
            
            ratio = skin_pixels / total_pixels
            return ratio
            
    except Exception as e:
        print(f"Error analyzing image: {e}")
        return None

def main():
    if len(sys.argv) < 2:
        print("Usage: python3 datadog_skin_tone.py <image_path>")
        sys.exit(1)

    image_path = sys.argv[1]
    
    if not os.path.exists(image_path):
        print(f"File not found: {image_path}")
        sys.exit(1)

    ratio = check_skin_tone(image_path)
    
    tags = ["env:dev", "service:opticvault", "integration:pillow"]

    if ratio is not None:
        print(f"Skin tone ratio: {ratio:.2f}")
        
        # Send metric to Datadog
        statsd.gauge('opticvault.analysis.skin_tone_ratio', ratio, tags=tags)
        
        if ratio > 0.45:
            print("ALERT: Excessive skin tones detected!")
            # Send event to Datadog
            statsd.event(
                title="Excessive Skin Tones Detected (Local)",
                text=f"Image {os.path.basename(image_path)} failed local skin-tone check with ratio {ratio:.2f}",
                alert_type="warning",
                tags=tags
            )
    else:
        statsd.increment('opticvault.analysis.error', tags=tags)

if __name__ == "__main__":
    main()
