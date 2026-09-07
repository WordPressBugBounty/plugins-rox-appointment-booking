// Shared availability helpers for the Customer Panel. Both Book New and
// Reschedule derive a service/agent's bookable days + time slots from
// GET /public/appointment-schedule, so keeping the logic here guarantees the two
// stay in sync. Mirrors the public booking panel's Calendar.jsx rules
// (holiday > special day off > weekly day_off).

import dayjs from "dayjs";
import { getSiteNow } from "../../lib/locale.js";

// JS Date.getDay() order (0=Sun) → the day names the schedule endpoint uses.
// These are wire values matched against `weekly_schedule[].day_name`, not UI
// text, so they stay in English.
export const DAY_NAMES = [
  "Sunday",
  "Monday",
  "Tuesday",
  "Wednesday",
  "Thursday",
  "Friday",
  "Saturday",
];

// "09:00:00" (24h) → "9:00 AM" for slot labels (the schedule endpoint returns
// raw 24h times). Formatted through Day.js so the meridiem comes from the
// WordPress-derived locale rather than a hardcoded English "AM"/"PM".
export function to12h(t) {
  const [h, m] = String(t).split(":").map(Number);
  return dayjs()
    .hour(h || 0)
    .minute(m || 0)
    .format("h:mm A");
}

// The bookable timeslots for a date, or null when it's off (holiday > special
// day off > weekly day_off). Mirrors Calendar.jsx getTimeslotsArrayForDate.
export function timeslotsForDate(schedule, dateStr) {
  if (!schedule) return null;
  if (Array.isArray(schedule.holidays) && schedule.holidays.includes(dateStr)) {
    return null;
  }
  if (Array.isArray(schedule.special_days)) {
    const sp = schedule.special_days.find((d) => d.date === dateStr);
    if (sp) {
      if (sp.day_off === true) return null;
      if (Array.isArray(sp.timeslots)) return sp.timeslots;
    }
  }
  if (Array.isArray(schedule.weekly_schedule)) {
    const [y, m, d] = dateStr.split("-").map(Number);
    const dayName = DAY_NAMES[new Date(y, m - 1, d).getDay()];
    const day = schedule.weekly_schedule.find((x) => x.day_name === dayName);
    if (day) {
      if (day.day_off === true) return null;
      if (Array.isArray(day.timeslots)) return day.timeslots;
    }
  }
  return null;
}

// Already-booked (unavailable) times for a date, as a Set of "HH:MM:SS".
export function bookedTimesForDate(schedule, dateStr) {
  const arr =
    schedule && Array.isArray(schedule.booked_timeslots)
      ? schedule.booked_timeslots
      : [];
  const entry = arr.find((b) => b.date === dateStr);
  const times = entry ? entry.timeslots || [] : [];
  return new Set(
    times.map((t) => (typeof t === "string" ? t : t.time || t.start_time))
  );
}

// The booking window's two ends, as the site's own clock reads them. The
// schedule is a per-weekday template with no notion of "now", so these are what
// turn it into real dates: nothing sooner than `first`, nothing later than
// `last`. `last` is null when the service sets no maximum.
function bookingWindow(schedule) {
  const maxMinutes = Number(schedule?.maximum_advance_minutes) || 0;
  return {
    first: getSiteNow(Number(schedule?.minimum_advance_minutes) || 0),
    last: maxMinutes > 0 ? getSiteNow(maxMinutes) : null,
  };
}

// Whether a whole date sits outside the booking window — too soon to book, or
// further out than the service allows.
function isOutsideWindow(schedule, dateStr) {
  const { first, last } = bookingWindow(schedule);
  return dateStr < first.date || (last !== null && dateStr > last.date);
}

// Whether a calendar date is unbookable: outside the booking window, or
// holiday > special day off > weekly day_off (matched by day name, exactly like
// the frontend booking panel).
export function isDateOff(schedule, dateStr) {
  if (!schedule) return false;
  // A date the window rules out has no pickable slot on it, so it disables
  // alongside the days off rather than opening to an all-grey slot list.
  if (isOutsideWindow(schedule, dateStr)) return true;
  if (Array.isArray(schedule.holidays) && schedule.holidays.includes(dateStr)) {
    return true;
  }
  if (Array.isArray(schedule.special_days)) {
    const sp = schedule.special_days.find((d) => d.date === dateStr);
    if (sp) return sp.day_off === true;
  }
  if (Array.isArray(schedule.weekly_schedule)) {
    const [y, m, d] = dateStr.split("-").map(Number);
    const dayName = DAY_NAMES[new Date(y, m - 1, d).getDay()];
    const day = schedule.weekly_schedule.find((x) => x.day_name === dayName);
    return day ? day.day_off === true : false;
  }
  return false;
}

// The pickable slots for a day: every timeslot with already-booked ones marked
// disabled (so unavailable times show grayed instead of vanishing). Mirrors
// Calendar.jsx getTimeSlotsForDate.
export function daySlotsFor(schedule, dateStr) {
  if (!schedule || !dateStr) return [];
  const times = timeslotsForDate(schedule, dateStr);
  if (!times) return [];
  const booked = bookedTimesForDate(schedule, dateStr);

  // Read on the site's clock rather than the visitor's, since the slots are
  // site-local wall time. Without this today's earlier times stay pickable all
  // day, and a service with a maximum stays bookable years out.
  const { first, last } = bookingWindow(schedule);
  const hhmm = (time) => String(time).slice(0, 5);
  const outsideWindow = (time) =>
    dateStr < first.date ||
    (dateStr === first.date && hhmm(time) <= hhmm(first.time)) ||
    (last !== null &&
      (dateStr > last.date ||
        (dateStr === last.date && hhmm(time) > hhmm(last.time))));

  return times.map((time) => ({
    value: time,
    label: to12h(time),
    disabled: booked.has(time) || outsideWindow(time),
  }));
}
