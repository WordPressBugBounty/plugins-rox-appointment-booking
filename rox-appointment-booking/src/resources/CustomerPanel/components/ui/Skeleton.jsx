import React from "react";

// Shimmer placeholder block. `w`/`h`/`r` are width/height/border-radius (numbers
// are px, strings pass through e.g. "60%"). Used to build per-view skeletons so
// navigating between views shows a matching silhouette instead of a blank blink.
export default function Skeleton({ w = "100%", h = 14, r = 6, className = "", style }) {
  return (
    <span
      className={`skel ${className}`.trim()}
      style={{ width: w, height: h, borderRadius: r, ...style }}
    />
  );
}
