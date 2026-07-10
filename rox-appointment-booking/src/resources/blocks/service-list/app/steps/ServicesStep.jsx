import React from "react";

// Formats a duration in minutes to a short label (e.g. 90 -> "1h 30m").
const formatDuration = (minutes) => {
  const total = Number(minutes) || 0;
  if (total <= 0) return "";
  const hours = Math.floor(total / 60);
  const mins = total % 60;
  if (hours && mins) return `${hours}h ${mins}m`;
  if (hours) return `${hours}h`;
  return `${mins}m`;
};

/**
 * Bespoke service list step. Reuses the frontend card CSS classes
 * (`services-grid`/`base-card`) so it looks consistent, but it is its own
 * component (no import from the shortcode's BookingService).
 *
 * `readOnly` is used by the editor preview: cards render but do not select.
 */
const ServicesStep = ({
  services = [],
  selectedId,
  onSelect,
  loading = false,
  readOnly = false,
  showImage = true,
  layout = "grid",
}) => (
  <div className="main-content-container">
    <span className="main-page-header-title">Available Services</span>

    {loading ? (
      <div className="rab-sl-loading">Loading services…</div>
    ) : services.length > 0 ? (
      <div
        className={
          layout === "list" ? "rab-sl-services-list" : "services-grid"
        }
      >
        {services.map((service) => (
          <div
            key={service.id}
            className={`base-card service-card ${
              selectedId === service.id ? "selected" : ""
            } ${readOnly ? "is-readonly" : ""}`}
            onClick={readOnly ? undefined : () => onSelect(service)}
          >
            <div className="service-info">
              {showImage ? (
                <span className="service-icon">
                  {service.iconPath ? (
                    <img
                      src={service.iconPath}
                      alt={service.name}
                      width={18}
                      height={16}
                    />
                  ) : null}
                </span>
              ) : null}
              <span className="service-meta">
                <span className="service-title">{service.name}</span>
                {formatDuration(service.duration) ? (
                  <span className="service-duration">
                    {formatDuration(service.duration)}
                  </span>
                ) : null}
              </span>
            </div>
            <div className="service-price">
              {"$"}
              {Number(service.price || 0).toFixed(2)}
            </div>
          </div>
        ))}
      </div>
    ) : (
      <div className="no-services">No services available yet.</div>
    )}
  </div>
);

export default ServicesStep;
