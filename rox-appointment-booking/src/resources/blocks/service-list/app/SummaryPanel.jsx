import React from "react";
import dayjs from "dayjs";

// Day.js runs on the WordPress-derived locale (lib/locale.js, imported by the
// block's view entry), so month names and the AM/PM marker follow the site
// language instead of being hardcoded English.
const formatDate = (date) => {
  if (!date) return "";
  const d = dayjs(date);
  return d.isValid() ? d.format("MMM D, YYYY") : "";
};

const formatTime = (time) => {
  if (!time) return "";
  const [h, m] = String(time).split(":");
  return dayjs().hour(parseInt(h, 10)).minute(parseInt(m, 10)).format("h:mm A");
};

/**
 * Optional right-hand info panel. Shown only when the block's "Show info panel"
 * control is enabled.
 */
const SummaryPanel = ({
  service,
  agent,
  date,
  startTime,
  total,
  currencySymbol = "$",
}) => {
  const hasAny = service || agent || date || startTime;

  return (
    <div className="rab-sl-summary-inner">
      <div className="rab-sl-summary-title">Your selection</div>

      {hasAny ? (
        <ul className="rab-sl-summary-list">
          {service ? (
            <li>
              <span>Service</span>
              <strong>{service.name}</strong>
            </li>
          ) : null}
          {agent ? (
            <li>
              <span>Agent</span>
              <strong>{agent.name}</strong>
            </li>
          ) : null}
          {date ? (
            <li>
              <span>Date</span>
              <strong>{formatDate(date)}</strong>
            </li>
          ) : null}
          {startTime ? (
            <li>
              <span>Time</span>
              <strong>{formatTime(startTime)}</strong>
            </li>
          ) : null}
        </ul>
      ) : (
        <div className="rab-sl-summary-empty">
          Make a selection to see the details here.
        </div>
      )}

      {service ? (
        <div className="rab-sl-summary-total">
          <span>Total</span>
          <strong>
            {currencySymbol}
            {total}
          </strong>
        </div>
      ) : null}
    </div>
  );
};

export default SummaryPanel;
