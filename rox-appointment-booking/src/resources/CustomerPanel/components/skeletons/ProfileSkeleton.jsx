import React from "react";
import Skeleton from "../ui/Skeleton.jsx";

// Loading silhouette for My Profile: sidebar (avatar/name/stats) + the
// personal-information form card.
export default function ProfileSkeleton() {
  return (
    <>
      <Skeleton w={160} h={26} style={{ marginBottom: 10 }} />
      <Skeleton w={340} h={14} style={{ marginBottom: 24 }} />

      <div className="profile-grid">
        <aside className="profile-side">
          <Skeleton w={80} h={80} r={40} style={{ margin: "0 auto 14px" }} />
          <Skeleton w={140} h={18} style={{ margin: "0 auto 8px" }} />
          <Skeleton w={180} h={13} style={{ margin: "0 auto 16px" }} />
          <Skeleton h={36} r={8} />
          <div style={{ display: "flex", gap: 12, marginTop: 20 }}>
            <Skeleton h={44} r={8} />
            <Skeleton h={44} r={8} />
          </div>
        </aside>

        <div className="card" style={{ padding: 24 }}>
          <Skeleton w={180} h={18} style={{ marginBottom: 18 }} />
          <div className="field-grid" style={{ marginBottom: 14 }}>
            <Skeleton h={40} r={8} />
            <Skeleton h={40} r={8} />
          </div>
          <Skeleton h={40} r={8} style={{ marginBottom: 14 }} />
          <Skeleton h={40} r={8} style={{ marginBottom: 14 }} />
          <div className="field-grid" style={{ marginBottom: 22 }}>
            <Skeleton h={40} r={8} />
            <Skeleton h={40} r={8} />
          </div>
          <Skeleton w={200} h={18} style={{ marginBottom: 16 }} />
          {[0, 1, 2].map((i) => (
            <Skeleton key={i} h={52} r={8} style={{ marginBottom: 12 }} />
          ))}
        </div>
      </div>
    </>
  );
}
