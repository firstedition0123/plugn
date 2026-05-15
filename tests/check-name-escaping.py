"""Static regression checks for stored-name escaping in high-risk views."""

import re
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def require(path: str, pattern: str) -> None:
    """Assert that a file contains an expected escaping pattern."""
    content = (ROOT / path).read_text(encoding="utf-8")
    if not re.search(pattern, content, flags=re.DOTALL):
        raise AssertionError(f"{path} is missing expected escaping guard: {pattern}")


def forbid(path: str, pattern: str) -> None:
    """Assert that a file no longer contains a known unsafe output pattern."""
    content = (ROOT / path).read_text(encoding="utf-8")
    if re.search(pattern, content, flags=re.DOTALL):
        raise AssertionError(f"{path} still contains unsafe raw output: {pattern}")


require(
    "frontend/views/business-location/index.php",
    r"Html::encode\(\s*\$businessLocation->country->country_name\s*\)",
)
require(
    "frontend/views/business-location/index.php",
    r"Html::a\(\s*Html::encode\(\s*\$businessLocation->business_location_name\s*\)",
)
forbid(
    "frontend/views/business-location/index.php",
    r"Html::a\(\s*\$businessLocation->business_location_name\s*\.\s*'",
)
require(
    "backend/views/subscription-payment/view.php",
    r"'format'\s*=>\s*'text'\s*,",
)
forbid(
    "backend/views/subscription-payment/view.php",
    r"'label'\s*=>\s*'Store Name'\s*,\s*'format'\s*=>\s*'raw'\s*,",
)

print("name escaping guards passed")
