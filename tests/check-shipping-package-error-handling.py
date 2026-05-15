#!/usr/bin/env python3
"""Validate that shipping package build errors do not terminate requests."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / "common" / "models" / "shipping" / "util" / "ShippingHelper.php"


def main() -> int:
    """Return non-zero when shipping package error handling regresses."""

    text = HELPER.read_text(encoding="utf-8")

    if "print_r($errors);die();" in text.replace(" ", ""):
        raise SystemExit("Shipping package errors must not be printed and followed by die().")

    if "\\Yii::warning('Unable to build shipping packages: ' . print_r($errors, true), __METHOD__);" not in text:
        raise SystemExit("Shipping package errors should be written to the Yii log.")

    if "return [];" not in text:
        raise SystemExit("Shipping package errors should return an empty package list.")

    print("shipping package error handling guard passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
