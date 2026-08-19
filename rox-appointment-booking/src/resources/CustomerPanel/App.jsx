import React, { useEffect, useState } from "react";
import { createRoot } from "react-dom/client";
import { ToastProvider } from "./context/ToastContext.jsx";
import PublicHeader from "./components/layout/PublicHeader.jsx";
import BookingsView from "./components/bookings/BookingsView.jsx";
import BookNewView from "./components/booknew/BookNewView.jsx";
import PaymentsView from "./components/payments/PaymentsView.jsx";
import ProfileView from "./components/profile/ProfileView.jsx";
// Side effect: registers the WordPress-derived Day.js locale, so every date the
// panel renders (and MiniCalendar's month/weekday names) follows the site
// language rather than Day.js's built-in English.
import "../lib/locale.js";
import "./styles/customer-panel.scss";

// Customer Panel — separate UI mounted into its OWN dedicated mount node
// (#rox-appointment-booking-customer-panel-root), distinct from the admin app's
// #rox-appointment-booking-app-root. The CustomerPanel PHP module enqueues this
// bundle only for customer users; the admin app bails out for them (App.php
// guard), so exactly one bundle mounts on the shared dashboard page.
const config =
  (window.rox_appointment_booking &&
    window.rox_appointment_booking.config &&
    window.rox_appointment_booking.config.customerPanel) ||
  {};

// Placeholder views for B1 — each subsequent Phase B step fills one of these in.
function ViewPlaceholder({ title }) {
  return (
    <div className="empty-state">
      <div className="empty-icon">🗂️</div>
      <div className="empty-title">{title}</div>
      <div className="empty-msg">Coming next.</div>
    </div>
  );
}

const VIEW_TITLES = {
  bookings: "My Bookings",
  "book-new": "Book New",
  payments: "Payment History",
  profile: "Profile",
};

// Hash-based routing so each view is a real URL the customer can bookmark/reload
// and stay put. The panel lives on a single wp-admin page
// (admin.php?page=rox-appointment-booking-dashboard), so a hash route
// (#/payments) survives reload with no server-side routing — WordPress ignores
// the fragment. The leading "/" (#/bookings) also stops the browser from trying
// to scroll to an element whose id matches the view name.
const VIEWS = Object.keys(VIEW_TITLES);
const DEFAULT_VIEW = "bookings";

function viewFromHash() {
  const raw = (window.location.hash || "").replace(/^#\/?/, "").trim();
  return VIEWS.includes(raw) ? raw : DEFAULT_VIEW;
}

function Panel() {
  const [view, setView] = useState(viewFromHash);
  // Optional payload handed to the view being navigated to (Book Again passes
  // the previous booking's ids so Book New can pre-fill the cascade). Kept in
  // memory only — it is not part of the hash route, so a reload starts clean.
  const [routeData, setRouteData] = useState(null);

  // Keep view in sync with the URL hash (browser back/forward, manual edits,
  // reload) and canonicalize an empty/unknown hash on first load so a reload is
  // always stable.
  useEffect(() => {
    const sync = () => {
      setView(viewFromHash());
      window.scrollTo(0, 0);
    };
    window.addEventListener("hashchange", sync);
    const canonical = `#/${viewFromHash()}`;
    if (window.location.hash !== canonical) {
      window.history.replaceState(null, "", canonical);
    }
    return () => window.removeEventListener("hashchange", sync);
  }, []);

  // Route by setting the hash; the `hashchange` listener updates the view. If the
  // hash is already the target (e.g. confirming a booking while on that view),
  // update state directly so nothing is missed.
  const navigate = (next, data = null) => {
    setRouteData(data);
    const target = `#/${next}`;
    if (window.location.hash === target) {
      setView(next);
      window.scrollTo(0, 0);
    } else {
      window.location.hash = target;
    }
  };

  return (
    <>
      <PublicHeader
        currentUser={config.currentUser}
        activeView={view}
        onNavigate={navigate}
        logoutUrl={config.logoutUrl}
      />
      <main className="container">
        {view === "bookings" ? (
          <BookingsView
            currentUser={config.currentUser}
            canReschedule={!!config.canReschedule}
            canCancel={!!config.canCancel}
            onNavigate={navigate}
          />
        ) : view === "book-new" ? (
          <BookNewView onNavigate={navigate} prefill={routeData} />
        ) : view === "payments" ? (
          <PaymentsView />
        ) : view === "profile" ? (
          <ProfileView currentUser={config.currentUser} />
        ) : (
          <ViewPlaceholder title={VIEW_TITLES[view] || ""} />
        )}
      </main>
    </>
  );
}

function App() {
  return (
    <div className="rox-cp">
      <ToastProvider>
        <Panel />
      </ToastProvider>
    </div>
  );
}

const rootElement = document.getElementById(
  "rox-appointment-booking-customer-panel-root"
);

if (rootElement) {
  createRoot(rootElement).render(<App />);
}
