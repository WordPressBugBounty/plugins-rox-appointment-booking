import React from "react";

// Centered modal with its own backdrop (mockup `.modal-backdrop` / `.modal`).
export default function Modal({ open, children }) {
  return (
    <div className={`modal-backdrop${open ? " open" : ""}`}>
      <div className="modal">{children}</div>
    </div>
  );
}
