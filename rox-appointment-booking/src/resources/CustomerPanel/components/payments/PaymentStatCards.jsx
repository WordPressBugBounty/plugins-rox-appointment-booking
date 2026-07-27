import React from "react";

// Three summary stat cards (mockup: Total Paid / Outstanding / Refunded).
export default function PaymentStatCards({ stats = [] }) {
  return (
    <div className="pay-stats">
      {stats.map((stat) => (
        <div className="card stat-card" key={stat.label}>
          <div className="stat-label">{stat.label}</div>
          <div className="stat-value" style={stat.color ? { color: stat.color } : undefined}>
            {stat.value}
          </div>
        </div>
      ))}
    </div>
  );
}
