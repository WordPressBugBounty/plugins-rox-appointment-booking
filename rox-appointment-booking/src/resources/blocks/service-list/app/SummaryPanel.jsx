import React from "react";

const formatDate = (date) => {
  if (!date) return "";
  const d = date instanceof Date ? date : new Date(date);
  if (Number.isNaN(d.getTime())) return "";
  return d.toLocaleDateString("en-US", {
    month: "short",
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
