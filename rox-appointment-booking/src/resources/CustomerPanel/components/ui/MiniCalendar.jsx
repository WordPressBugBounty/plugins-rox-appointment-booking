import React, { useState } from "react";

// Real, navigable month calendar (mockup `.reschedule-cal`). Controlled by a
// `value` (a "YYYY-MM-DD" string) + `onChange(next)`. Dates before `minDate`
// (default today) are disabled; today is highlighted. Month arrows move the view.
// An optional `isDisabled(dateStr)` predicate disables extra dates (e.g. days off
// / holidays) on top of the past-date rule.
const DAY_HEADS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const MONTHS = [
  "January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December",
];

const pad = (n) => String(n).padStart(2, "0");
const ymd = (y, m, d) => `${y}-${pad(m + 1)}-${pad(d)}`; // m is 0-indexed

export default function MiniCalendar({ value, onChange, minDate, isDisabled }) {
  const now = new Date();
  const todayStr = ymd(now.getFullYear(), now.getMonth(), now.getDate());
  const minStr = minDate || todayStr;

  // The month currently shown — starts on the selected date's month, else min.
  const [view, setView] = useState(() => {
    const base = value || minStr;
    const [y, m] = base.split("-").map(Number);
    return { year: y, month: m - 1 };
  });

  const firstWeekday = new Date(view.year, view.month, 1).getDay();
  const daysInMonth = new Date(view.year, view.month + 1, 0).getDate();
  const [minY, minM] = minStr.split("-").map(Number);
  const canPrev =
    view.year > minY || (view.year === minY && view.month > minM - 1);

  const prev = () =>
    setView((v) =>
      v.month === 0 ? { year: v.year - 1, month: 11 } : { year: v.year, month: v.month - 1 }
    );
  const next = () =>
    setView((v) =>
      v.month === 11 ? { year: v.year + 1, month: 0 } : { year: v.year, month: v.month + 1 }
    );

  const cells = [];
  for (let i = 0; i < firstWeekday; i++) cells.push(null);
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  return (
    <>
      <div style={{ display: "flex", alignItems: "center", justifyContent: "space-between", marginBottom: "12px" }}>
        <button className="btn btn-ghost btn-sm" onClick={prev} disabled={!canPrev}>
          ‹
        </button>
        <strong>{`${MONTHS[view.month]} ${view.year}`}</strong>
        <button className="btn btn-ghost btn-sm" onClick={next}>
          ›
        </button>
      </div>
      <div className="reschedule-cal">
        {DAY_HEADS.map((h) => (
          <div key={h} className="cal-day-head">
            {h}
          </div>
        ))}
        {cells.map((d, i) => {
          if (d === null) {
            return <div key={`blank-${i}`} />;
          }
          const cellStr = ymd(view.year, view.month, d);
          const disabled =
            cellStr < minStr || (isDisabled ? isDisabled(cellStr) : false);
          const classes = [
            "cal-day",
            disabled && "disabled",
            cellStr === todayStr && "today",
            !disabled && value === cellStr && "selected",
          ]
            .filter(Boolean)
            .join(" ");
          return (
            <div
              key={cellStr}
              className={classes}
              onClick={() => !disabled && onChange && onChange(cellStr)}
            >
              {d}
            </div>
          );
        })}
      </div>
    </>
  );
}
