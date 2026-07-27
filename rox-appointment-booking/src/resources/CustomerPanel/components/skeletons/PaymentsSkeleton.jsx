import React from "react";
import Skeleton from "../ui/Skeleton.jsx";

// Loading silhouette for Payment History: title, three stat cards, and a
// transactions card with a few rows.
export default function PaymentsSkeleton() {
  return (
    <>
      <Skeleton w={220} h={26} style={{ marginBottom: 10 }} />
      <Skeleton w={320} h={14} style={{ marginBottom: 24 }} />

      <div className="pay-stats">
        {[0, 1, 2].map((i) => (
          <Skeleton key={i} h={90} r={12} />
        ))}
      </div>

      <div className="card">
        <div className="pay-tx-head">
          <Skeleton w={120} h={16} />
        </div>
        {[0, 1, 2, 3].map((i) => (
          <div className="pay-row" key={i}>
            <div className="pay-row-left">
              <Skeleton w={40} h={40} r={20} />
              <div>
                <Skeleton w={160} h={14} style={{ marginBottom: 6 }} />
                <Skeleton w={100} h={11} />
              </div>
            </div>
            <Skeleton w={70} h={18} />
          </div>
        ))}
      </div>
    </>
  );
}
