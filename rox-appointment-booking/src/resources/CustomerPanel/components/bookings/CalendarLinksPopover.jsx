import React, { useState, useRef, useEffect, useCallback } from "react";
import { createPortal } from "react-dom";

const GoogleIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <path d="M18.316 5.684H24v12.632h-5.684V5.684z" fill="#1A73E8"/>
    <path d="M5.684 18.316H0V5.684h5.684v12.632z" fill="#EA4335"/>
    <path d="M18.316 24V18.316H5.684V24h12.632z" fill="#34A853"/>
    <path d="M5.684 5.684V0h12.632v5.684H5.684z" fill="#FBBC04"/>
    <path d="M18.316 5.684V0H24v5.684h-5.684z" fill="#4285F4"/>
    <path d="M18.316 18.316H24V24h-5.684v-5.684z" fill="#188038"/>
    <path d="M0 18.316h5.684V24H0v-5.684z" fill="#A50E0E"/>
    <path d="M0 0h5.684v5.684H0V0z" fill="#C5221F"/>
    <rect x="5.684" y="5.684" width="12.632" height="12.632" fill="#fff"/>
    <path d="M9.2 15.2l-1.1-.85 1.77-2.3-1.77-2.3 1.1-.85 2.3 3.15-2.3 3.15zm4.5 0l-1.1-.85 1.77-2.3-1.77-2.3 1.1-.85 2.3 3.15-2.3 3.15z" fill="#1A73E8"/>
  </svg>
);

const OutlookIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <path d="M24 7.387v10.478c0 .23-.08.424-.238.583a.793.793 0 01-.583.238h-9.97V6.565h9.97c.23 0 .424.08.583.238.159.159.238.353.238.584z" fill="#0364B8"/>
    <path d="M15.834 6.565V18.686H5.87L0 15.5V3.38c0-.23.08-.424.238-.583A.793.793 0 01.82 2.56h8.425l6.588 4.005z" fill="#0A2767"/>
    <path d="M15.834 6.565h-6.59l2.44 3.37-2.44 3.37h6.59V6.566z" fill="#28A8EA"/>
    <path d="M9.245 6.565v6.74H15.834v-6.74H9.245z" fill="#0078D4"/>
    <path d="M9.245 13.305v5.381h6.59v-5.381H9.245z" fill="#0364B8"/>
    <path d="M0 8.74v8.496a.68.68 0 00.698.699h5.172c.23 0 .424-.08.583-.239a.793.793 0 00.238-.583V8.74L3.346 7.566 0 8.74z" fill="#14447D"/>
    <ellipse cx="3.346" cy="12.565" rx="2.8" ry="2.8" fill="#0078D4"/>
    <path d="M3.346 10.565c-1.105 0-2 .895-2 2s.895 2 2 2 2-.895 2-2-.895-2-2-2zm0 3.2a1.2 1.2 0 110-2.4 1.2 1.2 0 010 2.4z" fill="#fff"/>
  </svg>
);

const YahooIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <rect width="24" height="24" rx="4" fill="#6001D2"/>
    <path d="M7 7l3.5 5.5V17h3v-4.5L17 7h-3l-2 3.5L10 7H7z" fill="#fff"/>
  </svg>
);

const IcsIcon = () => (
  <svg width="16" height="16" viewBox="0 0 24 24" fill="none">
    <rect x="2" y="3" width="20" height="19" rx="3" stroke="#555" strokeWidth="1.5" fill="none"/>
    <path d="M2 9h20" stroke="#555" strokeWidth="1.5"/>
    <path d="M7 1v4M17 1v4" stroke="#555" strokeWidth="1.5" strokeLinecap="round"/>
    <circle cx="8" cy="14" r="1.5" fill="#555"/>
    <circle cx="12" cy="14" r="1.5" fill="#555"/>
    <circle cx="16" cy="14" r="1.5" fill="#555"/>
  </svg>
);

const PROVIDERS = [
  { key: "google", label: "Google Calendar", Icon: GoogleIcon },
  { key: "outlook", label: "Outlook.com", Icon: OutlookIcon },
  { key: "yahoo", label: "Yahoo Calendar", Icon: YahooIcon },
  { key: "ics", label: "Download .ics", Icon: IcsIcon },
];

export default function CalendarLinksPopover({ calendarLinks, icsUrl }) {
  const [open, setOpen] = useState(false);
  const [pos, setPos] = useState({ top: 0, left: 0 });
  const triggerRef = useRef(null);
  const menuRef = useRef(null);

  const links = calendarLinks || {};
  const hasMultipleLinks = links.google || links.outlook || links.yahoo;

  const updatePos = useCallback(() => {
    if (!triggerRef.current) return;
    const rect = triggerRef.current.getBoundingClientRect();
    setPos({
      top: rect.bottom + 4,
      left: rect.left,
    });
  }, []);

  useEffect(() => {
    if (!open) return;
    updatePos();
    const handleClick = (e) => {
      if (
        triggerRef.current && !triggerRef.current.contains(e.target) &&
        menuRef.current && !menuRef.current.contains(e.target)
      ) {
        setOpen(false);
      }
    };
    const handleScroll = () => setOpen(false);
    document.addEventListener("mousedown", handleClick);
    window.addEventListener("scroll", handleScroll, true);
    return () => {
      document.removeEventListener("mousedown", handleClick);
      window.removeEventListener("scroll", handleScroll, true);
    };
  }, [open, updatePos]);

  if (!hasMultipleLinks) {
    const href = links.ics || icsUrl;
    if (!href) return null;
    return (
      <a
        href={href}
        download
        rel="noreferrer"
        onClick={(e) => e.stopPropagation()}
        style={{ color: "var(--primary)", textDecoration: "none", fontSize: "13.5px" }}
      >
        📅 Add to Calendar
      </a>
    );
  }

  const available = PROVIDERS.filter((p) => links[p.key]);

  const menu = open
    ? createPortal(
        <div
          ref={menuRef}
          onClick={(e) => e.stopPropagation()}
          style={{
            position: "fixed",
            top: pos.top,
            left: pos.left,
            background: "#fff",
            borderRadius: 8,
            boxShadow: "0 4px 16px rgba(0,0,0,0.15)",
            border: "1px solid #e5e7eb",
            padding: "4px 0",
            zIndex: 999999,
            minWidth: 180,
          }}
        >
          {available.map((p) => (
            <a
              key={p.key}
              href={links[p.key]}
              target={p.key === "ics" ? undefined : "_blank"}
              download={p.key === "ics" ? true : undefined}
              rel="noreferrer"
              onClick={(e) => { e.stopPropagation(); setOpen(false); }}
              style={{
                display: "flex",
                alignItems: "center",
                gap: 8,
                padding: "8px 14px",
                textDecoration: "none",
                color: "#1f2937",
                fontSize: "13.5px",
              }}
              onMouseEnter={(e) => { e.currentTarget.style.background = "#f3f4f6"; }}
              onMouseLeave={(e) => { e.currentTarget.style.background = "transparent"; }}
            >
              <p.Icon />
              {p.label}
            </a>
          ))}
        </div>,
        document.body
      )
    : null;

  return (
    <>
      <a
        ref={triggerRef}
        href="#"
        onClick={(e) => { e.preventDefault(); e.stopPropagation(); setOpen(!open); }}
        style={{ color: "var(--primary)", textDecoration: "none", fontSize: "13.5px", display: "inline-flex", alignItems: "center", gap: 4 }}
      >
        📅 Add to Calendar
        <svg width="10" height="10" viewBox="0 0 10 10" style={{ verticalAlign: "middle", transform: open ? "rotate(180deg)" : "none", transition: "transform 0.15s" }}>
          <path d="M2 3.5L5 6.5L8 3.5" stroke="currentColor" strokeWidth="1.5" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
        </svg>
      </a>
      {menu}
    </>
  );
}
