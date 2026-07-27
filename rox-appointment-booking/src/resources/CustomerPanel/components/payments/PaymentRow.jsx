import React from "react";
import Button from "../ui/Button.jsx";

// One transaction row (mockup `.pay-row`). Variants by status:
// paid → ✓ (green), refunded → ↺ (teal, negative amount), failed → ✕ (red),
// pending → ! (yellow, yellow row bg + Pay Now instead of a reference).
const ICON = { paid: "✓", refunded: "↺", failed: "✕", pending: "!" };

export default function PaymentRow({ tx, onPayNow }) {
  const iconClass = ["pay-icon", tx.status !== "paid" && tx.status].filter(Boolean).join(" ");
  const amountClass = ["pay-row-amount", tx.status === "refunded" && "negative"]
    .filter(Boolean)
    .join(" ");
  const amountStyle =
    tx.status === "pending" ? { color: "var(--yellow-text)" } : undefined;

  return (
    <div className={`pay-row${tx.status === "pending" ? " pending" : ""}`}>
      <div className="pay-row-left">
        <div className={iconClass}>{ICON[tx.status] || "✓"}</div>
        <div>
          <div className="pay-row-title">{tx.title}</div>
          <div className="pay-row-meta">{tx.meta}</div>
        </div>
      </div>
      <div style={{ textAlign: "right" }}>
        <div className={amountClass} style={amountStyle}>
          {tx.sign}
          {tx.amount}
        </div>
        {tx.payable ? (
          <Button
            variant="primary"
            size="sm"
            style={{ marginTop: "4px" }}
            onClick={() => onPayNow && onPayNow(tx)}
          >
            Pay Now
          </Button>
        ) : (
          <div className="pay-row-meta">{tx.reference}</div>
        )}
      </div>
    </div>
  );
}
