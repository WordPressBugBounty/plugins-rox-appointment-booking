import React from "react";

// Right slide-over drawer (mockup `.drawer`). The backdrop is rendered
// separately by the owner so a single backdrop can sit behind whichever drawer
// is open. `title` accepts any node (icon + text); `footer` is the sticky
// `.drawer-foot` content.
export default function Drawer({ open, title, onClose, footer, children }) {
  return (
    <div className={`drawer${open ? " open" : ""}`}>
      <div className="drawer-head">
        <div className="drawer-title">{title}</div>
        <div className="drawer-close" onClick={onClose}>
          ✕
        </div>
      </div>
      <div className="drawer-body">
        <div className="drawer-body-inner">{children}</div>
      </div>
      {footer && <div className="drawer-foot">{footer}</div>}
    </div>
  );
}
