import React from "react";
import dayjs from "dayjs";

/**
 * Bespoke completion step.
 *
 * Day.js runs on the WordPress-derived locale (lib/locale.js, imported by the
 * block's view entry), so weekday/month names and the AM/PM marker follow the
 * site language instead of being hardcoded English.
 */
const formatDate = (date) => {
  if (!date) return "";
  const d = dayjs(date);
  return d.isValid() ? d.format("dddd, MMMM D, YYYY") : "";
};

const formatTime = (time) => {
  if (!time) return "";
  const [h, m] = String(time).split(":");
  return dayjs().hour(parseInt(h, 10)).minute(parseInt(m, 10)).format("h:mm A");
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
