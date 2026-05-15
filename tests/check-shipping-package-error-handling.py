from pathlib import Path


root = Path(__file__).resolve().parents[1]
helper = root / "common" / "models" / "shipping" / "util" / "ShippingHelper.php"
text = helper.read_text(encoding="utf-8")

if "print_r($errors);die();" in text.replace(" ", ""):
    raise SystemExit("Shipping package errors must not be printed and followed by die().")

if "\\Yii::warning('Unable to build shipping packages: ' . print_r($errors, true), __METHOD__);" not in text:
    raise SystemExit("Shipping package errors should be written to the Yii log.")

if "return [];" not in text:
    raise SystemExit("Shipping package errors should return an empty package list.")

print("shipping package error handling guard passed")
