import React from "react";
import Modal from "../ui/Modal.jsx";
import Button from "../ui/Button.jsx";

// Cancel-confirmation modal (mockup `#cancelModal`).
export default function CancelModal({ open, onKeep, onConfirm }) {
  return (
    <Modal open={open}>
      <div className="modal-icon">⚠</div>
      <h3 className="modal-title">Cancel this appointment?</h3>
      <p className="modal-msg">
        Cancelling within 24 hours may incur a fee. You'll receive a refund
        confirmation by email.
      </p>
      <div className="modal-actions">
        <Button variant="secondary" onClick={onKeep}>
          Keep Booking
        </Button>
        <Button variant="danger" onClick={onConfirm}>
          Yes, Cancel
        </Button>
      </div>
    </Modal>
  );
}
