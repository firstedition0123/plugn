from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def require(path: str, needle: str) -> None:
    content = (ROOT / path).read_text(encoding="utf-8")
    if needle not in content:
        raise AssertionError(f"{path} is missing expected escaping guard: {needle}")


def forbid(path: str, needle: str) -> None:
    content = (ROOT / path).read_text(encoding="utf-8")
    if needle in content:
        raise AssertionError(f"{path} still contains unsafe raw output: {needle}")


require(
    "frontend/views/business-location/index.php",
    "Html::encode($businessLocation->country->country_name)",
)
require(
    "frontend/views/business-location/index.php",
    "Html::a(Html::encode($businessLocation->business_location_name)",
)
forbid(
    "frontend/views/business-location/index.php",
    "Html::a($businessLocation->business_location_name  . ' <i",
)
require(
    "backend/views/subscription-payment/view.php",
    "'format' => 'text',",
)
forbid(
    "backend/views/subscription-payment/view.php",
    "'label' => 'Store Name',\n                'format' => 'raw',",
)

print("name escaping guards passed")
