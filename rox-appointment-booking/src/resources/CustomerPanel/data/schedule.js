// Shared availability helpers for the Customer Panel. Both Book New and
// Reschedule derive a service/agent's bookable days + time slots from
// GET /public/appointment-schedule, so keeping the logic here guarantees the two
// stay in sync. Mirrors the public booking panel's Calendar.jsx rules
// (holiday > special day off > weekly day_off).

import dayjs from "dayjs";
import { getSiteNow } from "../../lib/locale.js";
import { isTimezoneShiftOn, toCustomer } from "../../lib/timezone.js";

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

/**
 * What one slot reads.
 *
 * "Show a time range" (per service) runs the label to the end of what the
 * slot holds — the appointment plus the wrap-up when the buffer is part of the
 * grid, which is what `slot_range_minutes` carries — instead of showing the
 * start alone. The preparation before the slot is never shown.
 *
 * @param {object} schedule Schedule payload.
 * @param {string} time     Clock time, "HH:mm:ss" or "HH:mm".
 * @return {string} Label.
 */
export function slotLabel(schedule, time) {
  const minutes = schedule && schedule.show_slot_time_range
    ? Number(schedule.slot_range_minutes ?? schedule.slot_duration) || 0
    : 0;

  if (minutes <= 0) {
    return to12h(time);
  }

  const [h, m] = String(time).split(":").map(Number);
  const end = dayjs()
    .hour(h || 0)
    .minute(m || 0)
    .add(minutes, "minute");

  return `${to12h(time)} - ${end.format("h:mm A")}`;
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

// Times closed only by the buffer around a booking, as a Set of "HH:MM". The
// panels drop these from the list instead of showing them disabled — see
// Calendar.jsx and GetAppointmentSchedule::getBookedTimeslots().
export function bufferTimesForDate(schedule, dateStr) {
  const arr =
    schedule && Array.isArray(schedule.booked_timeslots)
      ? schedule.booked_timeslots
      : [];
  const entry = arr.find((b) => b.date === dateStr);
  const times = entry ? entry.buffer_timeslots || [] : [];
  return new Set(
    times.map((t) =>
      String(typeof t === "string" ? t : t.time || t.start_time).slice(0, 5)
    )
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

  // Times only a booking's buffer holds read as taken too — see Calendar.jsx.
  const heldByBuffer = bufferTimesForDate(schedule, dateStr);

  return times
    .map((time) => ({
      value: time,
      label: slotLabel(schedule, time),
      disabled: booked.has(time) || heldByBuffer.has(hhmm(time)) || outsideWindow(time),
      // The pair the server knows this slot by — always what gets posted, even
      // when the labels have been moved onto the customer's clock.
      siteDate: dateStr,
      siteTime: time,
    }));
}

/**
 * The slots a customer on another timezone sees on their date `dateStr`.
 *
 * Same rule as the booking panel's calendar: the schedule answers per site
 * date, and a shift moves a slot onto the day before or after, so the three
 * site dates that could feed this one are asked for and whatever lands is
 * kept. Falls through to `daySlotsFor` untouched when nothing is shifting.
 *
 * @param {object} schedule Schedule payload.
 * @param {string} dateStr  The customer's date, `YYYY-MM-DD`.
 * @return {Array} Slots, labelled on the customer's clock.
 */
export function displaySlotsFor(schedule, dateStr) {
  if (!isTimezoneShiftOn()) {
    return daySlotsFor(schedule, dateStr);
  }

  const [year, month, day] = String(dateStr).split("-").map(Number);
  const out = [];

  for (let step = -1; step <= 1; step++) {
    const at = new Date(year, month - 1, day + step);
    const siteDate = `${at.getFullYear()}-${String(at.getMonth() + 1).padStart(2, "0")}-${String(at.getDate()).padStart(2, "0")}`;

    daySlotsFor(schedule, siteDate).forEach((slot) => {
      const on = toCustomer(slot.siteDate, slot.siteTime);

      if (on.date === dateStr) {
        out.push({ ...slot, value: on.time, label: slotLabel(schedule, on.time) });
      }
    });
  }

  return out.sort((a, b) => a.value.localeCompare(b.value));
}

/**
 * Whether a date on the customer's calendar has nothing to offer.
 *
 * While shifting, "holiday" and "day off" are facts about a site date that a
 * customer's day can straddle, so the honest test is whether any slot reached
 * this day at all.
 *
 * @param {object} schedule Schedule payload.
 * @param {string} dateStr  The customer's date.
 * @return {boolean} True when the day should be disabled.
 */
export function isDisplayDateOff(schedule, dateStr) {
  if (!isTimezoneShiftOn()) {
    return isDateOff(schedule, dateStr);
  }

  return displaySlotsFor(schedule, dateStr).length === 0;
}
