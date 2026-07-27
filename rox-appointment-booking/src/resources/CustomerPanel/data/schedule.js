// Shared availability helpers for the Customer Panel. Both Book New and
// Reschedule derive a service/agent's bookable days + time slots from
// GET /public/appointment-schedule, so keeping the logic here guarantees the two
// stay in sync. Mirrors the public booking panel's Calendar.jsx rules
// (holiday > special day off > weekly day_off).

// JS Date.getDay() order (0=Sun) → the day names the schedule endpoint uses.
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
// raw 24h times).
export function to12h(t) {
  const [h, m] = String(t).split(":").map(Number);
  const ampm = h >= 12 ? "PM" : "AM";
  const hr = h % 12 === 0 ? 12 : h % 12;
  return `${hr}:${String(m || 0).padStart(2, "0")} ${ampm}`;
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

// Whether a calendar date is unbookable: holiday > special day off > weekly
// day_off (matched by day name, exactly like the frontend booking panel).
export function isDateOff(schedule, dateStr) {
  if (!schedule) return false;
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
  return times.map((time) => ({
    value: time,
    label: to12h(time),
    disabled: booked.has(time),
  }));
}
