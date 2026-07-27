import React, { useEffect, useRef, useState } from "react";
import { useToast } from "../../context/ToastContext.jsx";
import Logo from "../../../components/icons/Logo.jsx";

// Public header (brand + nav + bell + avatar), converted from the mockup's
// `.public-header`. Nav drives the view router; the avatar is driven by
// window.rox_appointment_booking.config.customerPanel.currentUser.
export const NAV_ITEMS = [
  { id: "bookings", label: "My Bookings" },
  { id: "book-new", label: "Book New" },
  { id: "payments", label: "Payment History" },
  { id: "profile", label: "Profile" },
];

function initials(name) {
  if (!name) return "";
  return name
    .trim()
    .split(/\s+/)
    .slice(0, 2)
    .map((w) => (w[0] ? w[0].toUpperCase() : ""))
    .join("");
}

export default function PublicHeader({
  currentUser = {},
  activeView,
  onNavigate,
  logoutUrl = "",
}) {
  const showToast = useToast();
  const name = currentUser.name || "Guest";
  const email = currentUser.email || "";
  const src = currentUser.src || "";

  // Avatar dropdown (menu with Log out). Closes on outside-click / Escape.
  const [menuOpen, setMenuOpen] = useState(false);
  const menuRef = useRef(null);
  // Gravatar (or any stored photo URL) can fail to load; fall back to initials.
  const [avatarBroken, setAvatarBroken] = useState(false);

  useEffect(() => {
    if (!menuOpen) return undefined;
    const onDocClick = (e) => {
      if (menuRef.current && !menuRef.current.contains(e.target)) {
        setMenuOpen(false);
      }
    };
    const onKey = (e) => e.key === "Escape" && setMenuOpen(false);
    document.addEventListener("mousedown", onDocClick);
    document.addEventListener("keydown", onKey);
    return () => {
      document.removeEventListener("mousedown", onDocClick);
      document.removeEventListener("keydown", onKey);
    };
  }, [menuOpen]);

  const handleLogout = () => {
    if (logoutUrl) {
      window.location.href = logoutUrl;
    } else {
      showToast("error", "Logout unavailable", "No logout URL was provided.");
    }
  };

  return (
    <header className="public-header">
      <div className="brand">
        <Logo style={{ width: "170px", height: "auto", display: "block" }} />
        <span className="brand-meta">My Portal</span>
      </div>

      <nav className="header-nav">
        {NAV_ITEMS.map((item) => (
          <a
            key={item.id}
            className={activeView === item.id ? "active" : ""}
            onClick={() => onNavigate(item.id)}
          >
            {item.label}
          </a>
        ))}
      </nav>

      <div className="header-right">
        <div className="avatar-menu" ref={menuRef}>
          <div
            className="avatar-row"
            onClick={() => setMenuOpen((o) => !o)}
            role="button"
            aria-haspopup="true"
            aria-expanded={menuOpen}
          >
            <div className="avatar">
              {src && !avatarBroken ? (
                <img src={src} alt={name} onError={() => setAvatarBroken(true)} />
              ) : (
                initials(name)
              )}
            </div>
            <div className="avatar-name">
              <strong>{name}</strong>
              {email && <small>{email}</small>}
            </div>
            <svg
              width="14"
              height="14"
              viewBox="0 0 24 24"
              fill="none"
              stroke="currentColor"
              strokeWidth="2"
              style={{
                transition: "transform .15s",
                transform: menuOpen ? "rotate(180deg)" : "none",
              }}
            >
              <polyline points="6 9 12 15 18 9" />
            </svg>
          </div>

          {menuOpen && (
            <div className="avatar-dropdown">
              <div className="avatar-dropdown-head">
                <strong>{name}</strong>
                {email && <small>{email}</small>}
              </div>
              <button
                type="button"
                className="avatar-dropdown-item"
                onClick={handleLogout}
              >
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2">
                  <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4" />
                  <polyline points="16 17 21 12 16 7" />
                  <line x1="21" y1="12" x2="9" y2="12" />
                </svg>
                Log out
              </button>
            </div>
          )}
        </div>
      </div>
    </header>
  );
}
