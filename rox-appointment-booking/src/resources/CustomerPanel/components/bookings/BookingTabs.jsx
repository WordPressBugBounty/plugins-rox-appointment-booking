import React from "react";

// Upcoming / Past / Cancelled tab bar with counts.
const TABS = [
  { id: "upcoming", label: "Upcoming" },
  { id: "past", label: "Past" },
  { id: "cancelled", label: "Cancelled" },
];

export default function BookingTabs({ active, counts = {}, onChange }) {
  return (
    <div className="tab-bar">
      {TABS.map((tab) => (
        <div
          key={tab.id}
          className={`tab${active === tab.id ? " active" : ""}`}
          onClick={() => onChange(tab.id)}
        >
          {tab.label} <span className="count">{counts[tab.id] ?? 0}</span>
        </div>
      ))}
    </div>
  );
}
