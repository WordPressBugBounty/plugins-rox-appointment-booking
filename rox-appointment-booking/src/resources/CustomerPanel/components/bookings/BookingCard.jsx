import React from "react";
import Button from "../ui/Button.jsx";
import Pill from "../ui/Pill.jsx";

// A single booking card: date block + info (status pill, title, meta) + actions
// column (price + quick-action buttons). Clicking the card body opens the detail
// drawer (wired in B3); quick actions dispatch via onAction(type, booking).
export default function BookingCard({ booking, onAction, onOpenDetail }) {
  const {
    month,
    day,
    weekday,
    status,
    statusLabel,
    relativeLabel,
    title,
    with: withText,
    meta = [],
    price,
    priceStrikethrough,
    balance_due_formatted: balanceDueFormatted,
    completed,
    actions = [],
  } = booking;

  // When a deposit was already paid, show what's still owed instead of the
  // full price — matches the Pay Now action right below it.
  const headlinePrice = balanceDueFormatted || price;

  const handleAction = (event, type) => {
    event.stopPropagation();
    if (onAction) onAction(type, booking);
  };

  return (
    <div
      className={`booking-card${completed ? " completed" : ""}`}
      onClick={() => onOpenDetail && onOpenDetail(booking)}
    >
      <div className="booking-date">
        <div className="month">{month}</div>
        <div className="day">{day}</div>
        <div className="weekday">{weekday}</div>
      </div>

      <div className="booking-info">
        <div style={{ display: "flex", alignItems: "center", gap: "10px", marginBottom: "8px" }}>
          <Pill status={status}>{statusLabel}</Pill>
          {relativeLabel && (
            <span style={{ fontSize: "12px", color: "var(--text-muted)" }}>
              {relativeLabel}
            </span>
          )}
        </div>
        <h3 className="booking-title">{title}</h3>
        <div className="booking-with">{withText}</div>
        <div className="booking-meta">
          {meta.map((item, i) => (
            <span key={i}>{item}</span>
          ))}
        </div>
      </div>

      <div className="booking-actions">
        <div
          className="booking-price"
          style={
            priceStrikethrough
              ? { textDecoration: "line-through", color: "var(--text-muted)" }
              : undefined
          }
        >
          {headlinePrice}
        </div>
        <div className="booking-quick-actions">
          {actions.map((action) => (
            <Button
              key={action.type}
              variant={action.variant}
              size="sm"
              onClick={(e) => handleAction(e, action.type)}
            >
              {action.label}
            </Button>
          ))}
        </div>
      </div>
    </div>
  );
}
