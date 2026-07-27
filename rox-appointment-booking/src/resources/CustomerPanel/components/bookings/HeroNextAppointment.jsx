import React from "react";
import Button from "../ui/Button.jsx";

function firstName(name) {
  if (!name) return "there";
  return name.trim().split(/\s+/)[0];
}

// The gradient hero at the top of My Bookings, summarising the next appointment.
export default function HeroNextAppointment({ name, appointment, onViewDetails }) {
  return (
    <div className="hero">
      <div className="hero-content">
        <div className="hero-greet">Hello, {firstName(name)} 👋</div>
        <h1 className="hero-title">{appointment.title}</h1>
        <p className="hero-sub">{appointment.sub}</p>
      </div>
      {onViewDetails ? (
        <div className="hero-cta">
          <Button variant={null} onClick={onViewDetails}>
            View details →
          </Button>
        </div>
      ) : null}
    </div>
  );
}
