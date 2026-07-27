import React from "react";

// Status pill from the mockup's `.pill` classes.
// status: approved | rescheduled | pending | completed | cancelled
export default function Pill({ status, className = "", children, ...props }) {
  const classes = ["pill", status && `pill-${status}`, className]
    .filter(Boolean)
    .join(" ");

  return (
    <span className={classes} {...props}>
      {children}
    </span>
  );
}
