import React from "react";
import Drawer from "../ui/Drawer.jsx";
import Button from "../ui/Button.jsx";
import Pill from "../ui/Pill.jsx";

// Appointment detail drawer (mockup `#apptDrawer`). Summary header is driven by
// the selected booking; the info/payment/notes sections come from booking.detail.
export default function BookingDetailDrawer({
  open,
  booking,
  onClose,
  onReschedule,
  onCancel,
  canReschedule = false,
  canCancel = false,
}) {
  const detail = booking && booking.detail;
  const provider = booking ? (booking.with || "").split(" · ")[0] : "";
  const timeRange = booking ? (booking.meta[0] || "").replace(/^🕐\s*/, "") : "";
  const detailTime = booking
    ? `🕐 ${booking.weekday}, ${booking.month} ${booking.day} · ${timeRange}`
    : "";

  // Both actions are admin-gated (Settings > Booking); with neither allowed the
  // drawer renders without a footer instead of an empty bar.
  const footer =
    canCancel || canReschedule ? (
      <>
        {canCancel && (
          <Button variant="secondary" style={{ flex: 1 }} onClick={onCancel}>
            Cancel Booking
          </Button>
        )}
        {canReschedule && (
          <Button variant="primary" style={{ flex: 1 }} onClick={onReschedule}>
            Reschedule
          </Button>
        )}
      </>
    ) : null;

  return (
    <Drawer
      open={open}
      title={<>📅 Appointment Details</>}
      onClose={onClose}
      footer={booking ? footer : null}
    >
      {booking && (
        <>
          <div className="detail-summary">
            <Pill status={booking.status} style={{ marginBottom: "10px" }}>
              {booking.statusLabel}
            </Pill>
            <div className="detail-service">{booking.title}</div>
            <div className="detail-with">{provider}</div>
            <div className="detail-time">{detailTime}</div>
          </div>

          {detail && (
            <>
              <div className="detail-section">
                <div className="detail-section-title">Booking Information</div>
                {detail.info.map((row) => (
                  <div className="detail-row" key={row.label}>
                    <span className="label">{row.label}</span>
                    <span className="value">{row.value}</span>
                  </div>
                ))}
              </div>

              <div className="detail-section">
                <div className="detail-section-title">Payment</div>
                {detail.payment.map((row) => (
                  <div className="detail-row" key={row.label}>
                    <span className="label">{row.label}</span>
                    <span className="value">{row.value}</span>
                  </div>
                ))}
                <div className="detail-row">
                  <span className="label">Total</span>
                  <span
                    className="value"
                    style={{ fontWeight: 700, color: detail.paymentTotalColor }}
                  >
                    {detail.paymentTotal}
                  </span>
                </div>
              </div>

              <div className="detail-section">
                <div className="detail-section-title">Notes</div>
                <div
                  style={{
                    background: "var(--bg-soft)",
                    padding: "12px",
                    borderRadius: "8px",
                    fontSize: "13.5px",
                    color: "var(--text-secondary)",
                  }}
                >
                  {detail.notes}
                </div>
              </div>
            </>
          )}
        </>
      )}
    </Drawer>
  );
}
