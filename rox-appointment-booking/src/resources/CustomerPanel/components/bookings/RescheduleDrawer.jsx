import React, { useEffect, useMemo, useState } from "react";
import Drawer from "../ui/Drawer.jsx";
import Button from "../ui/Button.jsx";
import MiniCalendar from "../ui/MiniCalendar.jsx";
import SlotPicker from "../ui/SlotPicker.jsx";
import { apiGet } from "../../data/api.js";
import { daySlotsFor, isDateOff } from "../../data/schedule.js";

// Reschedule drawer (real, D5): current-booking banner + a date picker + the
// real available slots for the booking's service/agent. Availability comes from
// the PUBLIC GET /public/appointment-schedule (fetched once per open) and the
// day's slots are derived client-side, exactly like Book New — so the panel
// depends on no admin (canAccessPanel) endpoint. Reports the chosen date + raw
// start time on confirm.
function today() {
  return new Date().toISOString().slice(0, 10);
}

export default function RescheduleDrawer({ open, booking, onClose, onConfirm, submitting }) {
  const [date, setDate] = useState(today());
  const [slot, setSlot] = useState(null);
  const [schedule, setSchedule] = useState(null);
  const [loading, setLoading] = useState(false);

  const provider = booking ? (booking.with || "").split(" · ")[0].replace(/^with\s*/, "") : "";
  const currentTime = booking ? (booking.meta[0] || "").replace(/^🕐\s*/, "") : "";

  // Seed the date from the booking when the drawer (re)opens for a booking.
  useEffect(() => {
    if (open && booking) {
      setDate(booking.date || today());
      setSlot(null);
    }
  }, [open, booking]);

  // Fetch the service (+agent) schedule once per open — the single source for
  // both the calendar's disabled days and the per-day slot list.
  useEffect(() => {
    if (!open || !booking) return undefined;
    let active = true;
    setLoading(true);
    setSchedule(null);
    apiGet("public/appointment-schedule", {
      ...(booking.service_id ? { service_id: booking.service_id } : {}),
      ...(booking.agent_id ? { agent_id: booking.agent_id } : {}),
    })
      .then((res) => active && setSchedule((res && res.data) || null))
      .catch(() => active && setSchedule(null))
      .finally(() => active && setLoading(false));
    return () => {
      active = false;
    };
  }, [open, booking]);

  // Slots for the picked day, derived from the schedule (booked ones disabled).
  const slots = useMemo(() => daySlotsFor(schedule, date), [schedule, date]);

  // Reset the picked slot whenever the date changes.
  useEffect(() => {
    setSlot(null);
  }, [date]);

  const footer = (
    <>
      <Button variant="secondary" style={{ flex: 1 }} onClick={onClose}>
        Keep current
      </Button>
      <Button
        variant="primary"
        style={{ flex: 1 }}
        disabled={!slot || submitting}
        onClick={() => onConfirm && onConfirm(date, slot)}
      >
        {submitting ? "Rescheduling…" : "Confirm Reschedule"}
      </Button>
    </>
  );

  return (
    <Drawer
      open={open}
      title={<>⟳ Reschedule Appointment</>}
      onClose={onClose}
      footer={booking ? footer : null}
    >
      {booking && (
        <>
          <div
            style={{
              padding: "14px 16px",
              background: "var(--primary-soft)",
              borderRadius: "8px",
              marginBottom: "18px",
              fontSize: "13.5px",
            }}
          >
            <strong style={{ color: "var(--primary)" }}>Current:</strong>{" "}
            {booking.weekday}, {booking.month} {booking.day} at {currentTime}
            {provider && <> with {provider}</>}
          </div>

          <h3 style={{ margin: "0 0 12px", fontSize: "14px" }}>Pick a new date</h3>
          <MiniCalendar
            value={date}
            onChange={setDate}
            minDate={today()}
            isDisabled={(d) => isDateOff(schedule, d)}
          />

          <h3 style={{ margin: "18px 0 12px", fontSize: "14px" }}>
            Available time slots
          </h3>
          {loading ? (
            <div style={{ fontSize: "13px", color: "var(--text-muted)" }}>
              Loading slots…
            </div>
          ) : slots.length ? (
            <SlotPicker slots={slots} selected={slot} onSelect={setSlot} />
          ) : (
            <div style={{ fontSize: "13px", color: "var(--text-muted)" }}>
              No available slots on this date. Try another date.
            </div>
          )}
        </>
      )}
    </Drawer>
  );
}
