import React from "react";
import Skeleton from "../ui/Skeleton.jsx";

// A few choice-card placeholders, used while a cascade step's options load.
export function ChoiceListSkeleton({ rows = 3 }) {
  return (
    <div className="choice-list">
      {Array.from({ length: rows }).map((_, i) => (
        <Skeleton key={i} h={64} r={10} />
      ))}
    </div>
  );
}

// Full Book New card silhouette (stepper + first step), used while identity /
// panel structure resolve on mount.
export default function BookNewBootSkeleton() {
  return (
    <>
      <div
        style={{
          display: "flex",
          gap: 12,
          padding: "18px 22px",
          borderBottom: "1px solid var(--border-soft)",
        }}
      >
        {[0, 1, 2, 3, 4].map((i) => (
          <Skeleton key={i} w="18%" h={30} r={8} />
        ))}
      </div>
      <div className="booknew-body">
        <Skeleton w={200} h={18} style={{ marginBottom: 6 }} />
        <Skeleton w={260} h={13} style={{ marginBottom: 18 }} />
        <ChoiceListSkeleton />
      </div>
    </>
  );
}
