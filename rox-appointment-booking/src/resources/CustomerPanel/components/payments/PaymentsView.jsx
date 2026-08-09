import React, { useCallback, useEffect, useState } from "react";
import PaymentStatCards from "./PaymentStatCards.jsx";
import PaymentRow from "./PaymentRow.jsx";
import PayNowModal from "./PayNowModal.jsx";
import { useToast } from "../../context/ToastContext.jsx";
import { apiGet } from "../../data/api.js";
import PaymentsSkeleton from "../skeletons/PaymentsSkeleton.jsx";

// Payment History view (D2): stat cards + a transactions list. Data is the
// logged-in customer's real payments, fetched from GET /customer-panel/payments.
export default function PaymentsView() {
  const showToast = useToast();

  // Fetched payload: { stats, transactions }.
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [payTx, setPayTx] = useState(null); // { orderId, amount, title }

  // Load (and reload, e.g. after a Pay Now) the customer's payments.
  const loadPayments = useCallback(() => {
    setError(false);
    return apiGet("customer-panel/payments")
      .then((res) => setData((res && res.data) || null))
      .catch(() => setError(true));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    loadPayments().finally(() => {
      if (active) setLoading(false);
    });
    return () => {
      active = false;
    };
  }, [loadPayments]);

  const handlePayNow = (tx) => {
    if (tx && tx.order_id) {
      setPayTx({
        orderId: tx.order_id,
        // When set, settles just this transaction's own appointment instead
        // of the whole order (see PayBooking's booking_id path).
        bookingId: tx.booking_id,
        amount: tx.amount,
        title: tx.title,
      });
    } else {
      showToast("error", "Can't take payment", "This charge has no order to pay.");
    }
  };

  const handlePaid = async () => {
    setPayTx(null);
    await loadPayments();
    showToast("success", "Payment successful", "Your payment has been recorded.");
  };

  if (loading) {
    return <PaymentsSkeleton />;
  }

  if (error) {
    return (
      <div className="empty-state">
        <div className="empty-icon">⚠️</div>
        <div className="empty-title">Couldn't load your payments</div>
        <div className="empty-msg">Please refresh the page and try again.</div>
      </div>
    );
  }

  const stats = (data && data.stats) || [];
  const transactions = (data && data.transactions) || [];

  return (
    <>
      <h1 className="page-title">Payment History</h1>
      <p className="page-sub">
        All payments, refunds, and pending charges in one place.
      </p>

      <PaymentStatCards stats={stats} />

      <div className="card">
        <div className="pay-tx-head">Transactions</div>
        {transactions.length === 0 ? (
          <div className="empty-state">
            <div className="empty-icon">🧾</div>
            <div className="empty-title">No transactions yet</div>
            <div className="empty-msg">
              Your payments will appear here after your first booking.
            </div>
          </div>
        ) : (
          transactions.map((tx) => (
            <PaymentRow key={tx.id} tx={tx} onPayNow={handlePayNow} />
          ))
        )}
      </div>

      {payTx && (
        <PayNowModal
          order={payTx}
          onClose={() => setPayTx(null)}
          onPaid={handlePaid}
        />
      )}
    </>
  );
}
