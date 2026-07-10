import React from "react";

/**
 * Bespoke completion step.
 */
const formatDate = (date) => {
  if (!date) return "";
  const d = date instanceof Date ? date : new Date(date);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleDateString("en-US", {
    weekday: "long",
    month: "long",
    day: "numeric",
    year: "numeric",
  });
};

const formatTime = (time) => {
  if (!time) return "";
  const [h, m] = String(time).split(":");
  const hour = parseInt(h, 10);
  const ampm = hour >= 12 ? "PM" : "AM";
  return `${hour % 12 || 12}:${m} ${ampm}`;
};

const CompleteStep = ({ booking }) => (
  <div className="main-content-container">
    <span className="main-page-header-title">Booking Confirmed</span>
    <div className="rab-sl-complete">
      <div className="rab-sl-complete-check">✓</div>
      <p className="rab-sl-complete-msg">
        Thank you! Your appointment has been booked.
      </p>
      {booking ? (
        <ul className="rab-sl-complete-details">
          {booking.service ? (
            <li>
              <span>Service</span>
              <strong>{booking.service.name}</strong>
            </li>
          ) : null}
          {booking.employee ? (
            <li>
              <span>Agent</span>
              <strong>{booking.employee.name}</strong>
            </li>
          ) : null}
          {booking.date ? (
            <li>
              <span>Date</span>
              <strong>{formatDate(booking.date)}</strong>
            </li>
          ) : null}
          {booking.start_time ? (
            <li>
              <span>Time</span>
              <strong>{formatTime(booking.start_time)}</strong>
            </li>
          ) : null}
        </ul>
      ) : null}
    </div>
  </div>
);

export default CompleteStep;
