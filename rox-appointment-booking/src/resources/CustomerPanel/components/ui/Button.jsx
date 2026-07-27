import React from "react";

// Thin wrapper over the mockup's `.btn` classes.
// variant: primary | secondary | ghost | danger (default secondary)
// size:    "sm" for the compact `.btn-sm` variant
export default function Button({
  variant = "secondary",
  size,
  className = "",
  children,
  ...props
}) {
  const classes = [
    "btn",
    variant && `btn-${variant}`,
    size === "sm" && "btn-sm",
    className,
  ]
    .filter(Boolean)
    .join(" ");

  return (
    <button className={classes} {...props}>
      {children}
    </button>
  );
}
