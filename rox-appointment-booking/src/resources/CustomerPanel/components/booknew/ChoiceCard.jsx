import React from "react";

// Selectable option row for the Book New cascade (Location / Category / Service
// / Agent). `icon` is optional; `meta` is the right-aligned value (e.g. price).
export default function ChoiceCard({
  icon,
  title,
  subtitle,
  meta,
  selected,
  onClick,
}) {
  return (
    <div
      className={`choice-card${selected ? " selected" : ""}`}
      onClick={onClick}
    >
      {icon && <div className="choice-icon">{icon}</div>}
      <div className="choice-body">
        <div className="choice-title">{title}</div>
        {subtitle && <div className="choice-sub">{subtitle}</div>}
      </div>
      {meta && <div className="choice-meta">{meta}</div>}
    </div>
  );
}
