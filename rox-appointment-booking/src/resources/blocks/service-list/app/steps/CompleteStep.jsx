import React from "react";
import dayjs from "dayjs";
import { formatTimeOfDay } from "../../../../lib/locale.js";

/**
 * Bespoke completion step.
 *
 * Day.js runs on the WordPress-derived locale (lib/locale.js, imported by the
 * block's view entry), so weekday/month names follow the site language.
 */
const formatDate = (date) => {
  if (!date) return "";
  const d = dayjs(date);
  return d.isValid() ? d.format("dddd, MMMM D, YYYY") : "";
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
              <strong>{formatTimeOfDay(booking.start_time)}</strong>
            </li>
          ) : null}
        </ul>
      ) : null}
    </div>
  </div>
);

export default CompleteStep;
