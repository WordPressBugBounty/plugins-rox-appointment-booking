import React, { useCallback, useEffect, useState } from "react";
import { createPortal } from "react-dom";
import Button from "../ui/Button.jsx";
import { useToast } from "../../context/ToastContext.jsx";
import { apiGet, apiPost } from "../../data/api.js";
import HeroNextAppointment from "./HeroNextAppointment.jsx";
import BookingTabs from "./BookingTabs.jsx";
import BookingList from "./BookingList.jsx";
import BookingDetailDrawer from "./BookingDetailDrawer.jsx";
import RescheduleDrawer from "./RescheduleDrawer.jsx";
import CancelModal from "./CancelModal.jsx";
import PayNowModal from "../payments/PayNowModal.jsx";
import BookingsSkeleton from "../skeletons/BookingsSkeleton.jsx";

const EMPTY_COUNTS = { upcoming: 0, past: 0, cancelled: 0 };
const EMPTY_GROUPS = { upcoming: [], past: [], cancelled: [] };

// My Bookings view (D1): hero + tabs + booking cards, plus the detail/reschedule
// drawers and cancel modal. Data is the logged-in customer's real bookings,
// fetched from GET /customer-panel/bookings.
export default function BookingsView({
  currentUser = {},
  canReschedule = false,
  canCancel = false,
  onNavigate,
}) {
  const [tab, setTab] = useState("upcoming");
  const [selected, setSelected] = useState(null);
  const [activeDrawer, setActiveDrawer] = useState(null); // null | "detail" | "reschedule"
  const [modalOpen, setModalOpen] = useState(false);
  const [payOrder, setPayOrder] = useState(null); // { orderId, amount, title }
  const showToast = useToast();

  // Fetched bookings payload: { nextAppointment, counts, groups }.
  const [data, setData] = useState(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(false);
  const [rescheduling, setRescheduling] = useState(false);

  // Load (and reload, e.g. after a reschedule) the customer's bookings.
  const loadBookings = useCallback(() => {
    setError(false);
    return apiGet("customer-panel/bookings")
      .then((res) => setData((res && res.data) || null))
      .catch(() => setError(true));
  }, []);

  useEffect(() => {
    let active = true;
    setLoading(true);
    loadBookings().finally(() => {
      if (active) setLoading(false);
    });
    return () => {
      active = false;
    };
  }, [loadBookings]);

  const counts = (data && data.counts) || EMPTY_COUNTS;
  const groups = (data && data.groups) || EMPTY_GROUPS;
  const nextAppointment =
    (data && data.nextAppointment) || {
      title: "You have no upcoming appointments",
      sub: "Book a new appointment to get started.",
    };

  const openDetail = (booking) => {
    setSelected(booking);
    setActiveDrawer("detail");
  };
  const openReschedule = (booking) => {
    setSelected(booking);
    setActiveDrawer("reschedule");
  };
  const openCancel = (booking) => {
    setSelected(booking);
    setActiveDrawer(null);
    setModalOpen(true);
  };
  const closeDrawers = () => setActiveDrawer(null);

  const handleAction = (type, booking) => {
    switch (type) {
      case "payNow":
        if (booking.order_id) {
          setPayOrder({
            orderId: booking.order_id,
            // This appointment's own payment row (see
            // PaymentProcessingService::savePayment()) — Pay Now settles just
            // this service, not the other appointments sharing the order.
            bookingId: booking.id,
            // The remaining balance (may be less than the full price if a
            // deposit was already paid) — matches what the server actually
            // charges (PayBooking's booking_id/order_id paths).
            amount: booking.due_amount_formatted || booking.price,
            title: booking.title,
          });
        } else {
          showToast(
            "error",
            "Can't take payment",
            "This booking has no order to pay."
          );
        }
        break;
      case "bookAgain":
        if (!booking.service_id) {
          showToast(
            "error",
            "Can't rebook",
            "This booking's service is no longer available."
          );
          break;
        }
        // Hand the old booking's ids to Book New, which resolves them into real
        // selections and drops the customer straight on Date & Time.
        onNavigate("book-new", {
          serviceId: booking.service_id,
          agentId: booking.agent_id,
          categoryId: booking.category_id,
          locationId: booking.location_id,
        });
        break;
      case "reschedule":
        if (!canReschedule) break;
        openReschedule(booking);
        break;
      case "cancel":
        if (!canCancel) break;
        openCancel(booking);
        break;
      case "directions":
        showToast("info", "Opening directions", "Launching maps for this location.");
        break;
      default:
        break;
    }
  };

  // Persist the reschedule, then reload the list so the moved booking reflects
  // its new date/time. `date` is Y-m-d, `startTime` is the raw slot time.
  const confirmReschedule = async (date, startTime) => {
    if (!selected) return;
    setRescheduling(true);
    try {
      await apiPost("customer-panel/reschedule", {
        id: selected.id,
        date,
        start_time: startTime,
      });
      await loadBookings();
      closeDrawers();
      showToast(
        "success",
        "Reschedule confirmed",
        "Your appointment has been moved."
      );
    } catch (e) {
      showToast(
        "error",
        "Couldn't reschedule",
        (e && e.message) || "That slot may no longer be available."
      );
    } finally {
      setRescheduling(false);
    }
  };

  const confirmCancel = async () => {
    if (!selected) return;
    try {
      await apiPost("customer-panel/cancel", { id: selected.id });
      await loadBookings();
      setModalOpen(false);
      showToast(
        "warn",
        "Appointment cancelled",
        "Your booking has been cancelled."
      );
    } catch (e) {
      showToast(
        "error",
        "Couldn't cancel",
        (e && e.message) || "Please try again."
      );
    }
  };

  // After a successful Pay Now charge, reload so the paid booking loses its
  // Pay Now action, then confirm.
  const handlePaid = async () => {
    setPayOrder(null);
    await loadBookings();
    showToast("success", "Payment successful", "Your booking is now paid.");
  };

  if (loading) {
    return <BookingsSkeleton />;
  }

  if (error) {
    return (
      <div className="empty-state">
        <div className="empty-icon">⚠️</div>
        <div className="empty-title">Couldn't load your bookings</div>
        <div className="empty-msg">Please refresh the page and try again.</div>
      </div>
    );
  }

  return (
    <>
      <HeroNextAppointment
        name={currentUser.name}
        appointment={nextAppointment}
        onViewDetails={
          groups.upcoming[0] ? () => openDetail(groups.upcoming[0]) : null
        }
      />

      <BookingTabs active={tab} counts={counts} onChange={setTab} />

      <BookingList
        bookings={groups[tab] || []}
        onAction={handleAction}
        onOpenDetail={openDetail}
      />

      {tab === "upcoming" && (
        <div style={{ textAlign: "center", marginTop: "24px" }}>
          <Button variant="primary" onClick={() => onNavigate("book-new")}>
            + Book a new appointment
          </Button>
        </div>
      )}

      {/* Overlay layer (backdrop + drawers + modal) is portaled to <body> so its
          position:fixed anchors to the viewport, not to any transformed/overflow
          ancestor inside the app tree. Wrapped in `.rox-cp` so scoped styles and
          CSS variables still apply. The backdrop + drawers live inside a fixed
          `.drawer-overlay` clip layer so the off-canvas (closed) drawers can't
          create page-wide horizontal scroll; the modal sits outside it (centered,
          no off-canvas problem). */}
      {createPortal(
        <div className="rox-cp">
          <div className="drawer-overlay">
            <div
              className={`drawer-backdrop${activeDrawer ? " open" : ""}`}
              onClick={closeDrawers}
            />

            <BookingDetailDrawer
              open={activeDrawer === "detail"}
              booking={selected}
              onClose={closeDrawers}
              onReschedule={() => setActiveDrawer("reschedule")}
              onCancel={() => openCancel(selected)}
              onBookAgain={() => handleAction("bookAgain", selected)}
              canReschedule={canReschedule}
              canCancel={canCancel}
            />

            <RescheduleDrawer
              open={activeDrawer === "reschedule"}
              booking={selected}
              onClose={closeDrawers}
              onConfirm={confirmReschedule}
              submitting={rescheduling}
            />
          </div>

          <CancelModal
            open={modalOpen}
            onKeep={() => setModalOpen(false)}
            onConfirm={confirmCancel}
          />
        </div>,
        document.body
      )}

      {payOrder && (
        <PayNowModal
          order={payOrder}
          onClose={() => setPayOrder(null)}
          onPaid={handlePaid}
        />
      )}
    </>
  );
}
