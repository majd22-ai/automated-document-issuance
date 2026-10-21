"""Create web-sized copies; preserve every source file and its aspect ratio."""
from pathlib import Path
from PIL import Image, ImageOps

source = Path('public/assets/images/banner')
target = Path('public/assets/images/services')
target.mkdir(exist_ok=True)
assets = [
    ('شعار الاداره العامة للمرور.webp', 'traffic-logo.png', 1200),
    ('شعار الهجرة والجوازات والجنسية (2).png', 'passports-logo.png', 600),
    ('صوة البطاقة..jpeg', 'identity-document.jpg', 1200),
    ('صورة الرخصة.jpeg', 'driving-license.jpg', 1200),
    ('صورة جواز السفر.jpeg', 'yemeni-passport.jpg', 1200),
]
for original, output, width in assets:
    with Image.open(source / original) as image:
        image = ImageOps.exif_transpose(image)
        image.thumbnail((width, width), Image.Resampling.LANCZOS)
        if output.endswith('.jpg'):
            image.convert('RGB').save(target / output, quality=92, optimize=True)
        else:
            image.save(target / output, optimize=True)
        print(f'{output}: {image.width}x{image.height}')
