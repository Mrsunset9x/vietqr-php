"""Optional independent image check. Dev tools only: zxing-cpp, Pillow, PyMuPDF."""
import json
from pathlib import Path

import fitz
from PIL import Image
import zxingcpp

directory = Path(__file__).resolve().parent.parent / "build" / "qr"
manifest = json.loads((directory / "manifest.json").read_text(encoding="utf-8"))
for filename, expected in manifest.items():
    path = directory / filename
    if path.suffix == ".svg":
        with fitz.open(path) as document:
            pixmap = document[0].get_pixmap()
            picture = Image.frombytes("RGB", [pixmap.width, pixmap.height], pixmap.samples)
    else:
        with Image.open(path) as source:
            picture = source.convert("RGB")
    result = zxingcpp.read_barcode(picture)
    if result is None or result.text != expected:
        raise SystemExit(f"FAIL {filename}: decoded payload differs")
    # QR images must remain readable after a quarter turn as well.
    rotated = zxingcpp.read_barcode(picture.transpose(Image.Transpose.ROTATE_90))
    if rotated is None or rotated.text != expected:
        raise SystemExit(f"FAIL {filename}: rotated decode differs")
print(f"PASS {len(manifest)} images, {len(manifest) * 2} independent decodes including rotation")
