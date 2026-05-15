#!/usr/bin/env python3
"""Guard order and invoice save failures from terminating live requests."""

from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]
ORDER_HISTORY = ROOT / "common/models/OrderHistory.php"
INVOICE_PAYMENT = ROOT / "common/models/InvoicePayment.php"
RESTAURANT_INVOICE = ROOT / "common/models/RestaurantInvoice.php"


def compact(text: str) -> str:
    """Normalize whitespace for brittle legacy PHP one-liners."""
    return "".join(text.split())


def assert_absent(path: Path, needle: str, message: str) -> None:
    """Reject a compacted PHP snippet."""
    text = compact(path.read_text(encoding="utf-8"))
    if compact(needle) in text:
        raise SystemExit(message)


def assert_present(path: Path, needle: str, message: str) -> None:
    """Require a compacted PHP snippet."""
    text = compact(path.read_text(encoding="utf-8"))
    if compact(needle) not in text:
        raise SystemExit(message)


def main() -> int:
    """Run static checks for the touched order and invoice models."""
    assert_absent(
        ORDER_HISTORY,
        "Yii::error($order->errors); print_r($order->errors); die();",
        "OrderHistory must not print order errors and die.",
    )
    assert_absent(
        ORDER_HISTORY,
        "Yii::error($model->errors); print_r($model->errors); die();",
        "OrderHistory must not print history errors and die.",
    )
    assert_absent(
        INVOICE_PAYMENT,
        "Yii::error($payment->errors); print_r($payment->errors); die();",
        "InvoicePayment must not print payment errors and die.",
    )
    assert_absent(
        RESTAURANT_INVOICE,
        "print_r($domainSubscription->errors); die();",
        "RestaurantInvoice must not print subscription errors and die.",
    )

    assert_present(
        ORDER_HISTORY,
        "Yii::error('Order not found while adding history: ' . $order_uuid, __METHOD__);",
        "OrderHistory missing not-found logging.",
    )
    assert_present(
        ORDER_HISTORY,
        "Yii::error(print_r($order->errors, true), __METHOD__); return false;",
        "OrderHistory order save failure should be logged and returned.",
    )
    assert_present(
        ORDER_HISTORY,
        "Yii::error(print_r($model->errors, true), __METHOD__); return false;",
        "OrderHistory history save failure should be logged and returned.",
    )
    assert_present(
        INVOICE_PAYMENT,
        "Yii::error(print_r($payment->errors, true), __METHOD__); return $payment;",
        "InvoicePayment save failure should be logged and returned.",
    )
    assert_present(
        RESTAURANT_INVOICE,
        "Yii::error(print_r($domainSubscription->errors, true), __METHOD__); continue;",
        "RestaurantInvoice subscription save failure should be logged and skipped.",
    )

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
