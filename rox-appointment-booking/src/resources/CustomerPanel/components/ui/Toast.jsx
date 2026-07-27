import React from "react";

// Toast host — renders the fixed stack in the top-right, mirroring the mockup's
// `#toastContainer` markup and slide-in animation. Driven by ToastContext.
const ICONS = { success: "✓", warn: "!", err: "✕", info: "i" };

export default function Toast({ toasts, onDismiss }) {
  return (
    <div className="toast-container">
      {toasts.map((t) => (
        <div key={t.id} className={`toast ${t.type}`}>
          <div className="toast-icon">{ICONS[t.type] || "✓"}</div>
          <div className="toast-body">
            <div className="toast-title">{t.title}</div>
            {t.msg && <div className="toast-msg">{t.msg}</div>}
          </div>
          <div className="toast-close" onClick={() => onDismiss(t.id)}>
            ✕
          </div>
        </div>
      ))}
    </div>
  );
}
