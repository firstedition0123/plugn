#!/usr/bin/env python3
"""Validate that shipping package build errors do not terminate requests."""

import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
HELPER = ROOT / "common" / "models" / "shipping" / "util" / "ShippingHelper.php"


def main() -> int:
    """Return non-zero when shipping package error handling regresses."""

    text = HELPER.read_text(encoding="utf-8")

    if re.search(r"print_r\s*\(\s*\$errors\s*\)\s*;\s*die\s*\(\s*\)\s*;?", text):
        raise SystemExit("Shipping package errors must not be printed and followed by die().")

    if not re.search(
        r"\\Yii::warning\s*\(\s*['\"]Unable to build shipping packages:\s*['\"]"
        r"\s*\.\s*print_r\s*\(\s*\$errors\s*,\s*true\s*\)\s*,\s*__METHOD__\s*\)\s*;?",
        text,
    ):
        raise SystemExit("Shipping package errors should be written to the Yii log.")

    if not re.search(r"return\s*\[\s*\]\s*;?", text):
        raise SystemExit("Shipping package errors should return an empty package list.")

    print("shipping package error handling guard passed")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
