import React from "react";

// Step progress bar (mockup `.stepper`). `steps` is an array of
// { label, state } where state is "done" | "active" | "" (upcoming). Done steps
// show a ✓; the others show their 1-based position. Connectors sit between bubbles.
export default function Stepper({ steps = [] }) {
  return (
    <div className="stepper">
      {steps.map((step, i) => (
        <React.Fragment key={step.label}>
          {i > 0 && <div className="step-conn" />}
          <div className={`step-bubble${step.state ? " " + step.state : ""}`}>
            <div className="step-circle">
              {step.state === "done" ? "✓" : i + 1}
            </div>
            <div className="step-label">{step.label}</div>
          </div>
        </React.Fragment>
      ))}
    </div>
  );
}
