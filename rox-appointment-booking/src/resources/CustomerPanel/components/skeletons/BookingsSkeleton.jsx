import React from "react";
import Skeleton from "../ui/Skeleton.jsx";

// Loading silhouette for My Bookings: hero banner + tab row + a few booking
// cards, so switching to this view doesn't blank-flash.
export default function BookingsSkeleton() {
  return (
    <>
      <Skeleton h={120} r={14} style={{ marginBottom: 24 }} />
      <div style={{ display: "flex", gap: 10, marginBottom: 20 }}>
        <Skeleton w={120} h={38} r={8} />
        <Skeleton w={95} h={38} r={8} />
        <Skeleton w={110} h={38} r={8} />
      </div>
      {[0, 1, 2].map((i) => (
        <Skeleton key={i} h={92} r={12} style={{ marginBottom: 14 }} />
      ))}
    </>
  );
}
