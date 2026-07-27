import React, { useEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";
import Button from "../ui/Button.jsx";
import { apiPost } from "../../data/api.js";

// D5 — Pay Now: settle an unpaid (pay-later) order with a Stripe card, from
// inside the panel. Mirrors the public booking panel's Stripe flow
// (StripePaymentForm): loads Stripe.js from the CDN, reads the publishable key
// from the public payment-form structure, mounts a single card Element, and on
// submit turns the card into a pm_… payment method that our own
// POST /customer-panel/pay endpoint charges server-side (the amount is computed
// server-side from the order — never sent by the client).
//
// Rendered only while `order` is set (conditional mount keeps the Stripe element
// lifecycle simple). `order` = { orderId, amount, title }. Calls onPaid() after a
// successful charge (the parent reloads + toasts) and onClose() to dismiss.
export default function PayNowModal({ order, onClose, onPaid }) {
  const [loading, setLoading] = useState(true); // loading config / Stripe.js
  const [available, setAvailable] = useState(true); // Stripe enabled + configured
  const [cardReady, setCardReady] = useState(false);
  const [processing, setProcessing] = useState(false);
  const [error, setError] = useState("");

  const cardRef = useRef(null);
  const stripeRef = useRef(null);
  const cardElementRef = useRef(null);

  // Load Stripe.js, fetch the publishable key, then mount a card element.
  useEffect(() => {
    let cancelled = false;

    const cfg =
      (window.rox_appointment_booking &&
        window.rox_appointment_booking.config &&
        window.rox_appointment_booking.config.customerPanel) ||
      {};
    const apiBase = cfg.apiBaseUrl || "/wp-json/rox-appointment-booking/v1/";

    const mountCard = (publishableKey) => {
      if (cancelled || !cardRef.current || !window.Stripe) return;
      try {
        stripeRef.current = window.Stripe(publishableKey);
        const elements = stripeRef.current.elements();
        cardElementRef.current = elements.create("card", { hidePostalCode: false });
        cardElementRef.current.mount(cardRef.current);
        cardElementRef.current.on("ready", () => !cancelled && setCardReady(true));
        cardElementRef.current.on("change", (event) => {
          if (cancelled) return;
          setError(event.error ? event.error.message : "");
        });
      } catch (e) {
        if (!cancelled) setError("Failed to initialize the payment form.");
      }
    };

    const loadConfig = () => {
      fetch(`${apiBase}public/structure/payment-form`)
        .then((r) => r.json())
        .then((res) => {
          if (cancelled) return;
          const data = (res && res.data) || {};
          if (data.stripeEnable && data.stripe_key) {
            setLoading(false);
            mountCard(data.stripe_key);
          } else {
            setAvailable(false);
            setLoading(false);
          }
        })
        .catch(() => {
          if (cancelled) return;
          setError("Network error loading the payment form.");
          setLoading(false);
        });
    };

    const ensureStripe = () => {
      if (window.Stripe) {
        loadConfig();
      } else if (!document.querySelector('script[src="https://js.stripe.com/v3/"]')) {
        const script = document.createElement("script");
        script.src = "https://js.stripe.com/v3/";
        script.onload = loadConfig;
        script.onerror = () => {
          if (cancelled) return;
          setError("Failed to load Stripe. Please try again.");
          setLoading(false);
        };
        document.head.appendChild(script);
      } else {
        // Script tag exists but window.Stripe not ready yet — poll briefly.
        const timer = setInterval(() => {
          if (window.Stripe) {
            clearInterval(timer);
            loadConfig();
          }
        }, 150);
      }
    };

    ensureStripe();

    return () => {
      cancelled = true;
      if (cardElementRef.current) {
        try {
          cardElementRef.current.unmount();
        } catch (e) {
          /* element already gone */
        }
        cardElementRef.current = null;
      }
    };
  }, []);

  const handlePay = async () => {
    if (!cardReady || processing) return;
    setProcessing(true);
    setError("");
    try {
      const { paymentMethod, error: pmError } =
        await stripeRef.current.createPaymentMethod({
          type: "card",
          card: cardElementRef.current,
        });

      if (pmError) {
        setError(pmError.message);
        setProcessing(false);
        return;
      }

      await apiPost("customer-panel/pay", {
        order_id: order.orderId,
        payment_method: paymentMethod.id,
      });

      setProcessing(false);
      if (onPaid) onPaid();
    } catch (e) {
      setProcessing(false);
      setError((e && e.message) || "Payment failed. Please try again.");
    }
  };

  return createPortal(
    <div className="rox-cp">
      <div className="modal-backdrop open">
        <div className="modal paynow-modal">
          <h3 className="modal-title">Complete Payment</h3>

          {order && order.title && (
            <p className="modal-msg">
              {order.title}
              {order.amount ? <strong> · {order.amount}</strong> : null}
            </p>
          )}

          {!available ? (
            <>
              <p className="modal-msg">
                Online payment isn't available right now. Please contact us to
                complete your payment.
              </p>
              <div className="modal-actions">
                <Button variant="secondary" onClick={onClose}>
                  Close
                </Button>
              </div>
            </>
          ) : (
            <>
              <div className="paynow-field">
                <label className="paynow-label">Card details</label>
                {loading && (
                  <div className="paynow-card-loading">
                    Loading secure payment form…
                  </div>
                )}
                {/* Stripe injects an iframe here — this node must stay a childless
                    React leaf, or React's reconciler/unmount crashes with
                    removeChild ("node to be removed is not a child"). Keep the
                    loading text as a SIBLING above, never inside this div. It must
                    also stay VISIBLE (no display:none): Stripe mounts into it before
                    the loading re-render lands, and mounting into a hidden node
                    produces a zero-size, non-interactive card field. */}
                <div className="paynow-card" ref={cardRef} />
              </div>

              {error && <div className="paynow-error">{error}</div>}

              <div className="paynow-secure">🔒 Payments are secured by Stripe</div>

              <div className="modal-actions">
                <Button
                  variant="secondary"
                  onClick={onClose}
                  disabled={processing}
                >
                  Cancel
                </Button>
                <Button
                  variant="primary"
                  onClick={handlePay}
                  disabled={!cardReady || processing}
                >
                  {processing
                    ? "Processing…"
                    : order && order.amount
                    ? `Pay ${order.amount}`
                    : "Pay Now"}
                </Button>
              </div>
            </>
          )}
        </div>
      </div>
    </div>,
    document.body
  );
}
