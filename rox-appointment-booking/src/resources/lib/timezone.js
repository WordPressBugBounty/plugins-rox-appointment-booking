/**
 * The one place the plugin converts between the site's clock and a customer's.
 *
 * Schedules, slots, bookings, the overbooking check and every admin screen are
 * naive site-local wall time, and stay that way — this module never changes
 * what is stored or what is posted. It exists so a customer in another country
 * can *read* the available times on their own clock, and so the slot they pick
 * can be mapped straight back to the site pair the server already expects.
 *
 * Conversion runs through Day.js's `utc` + `timezone` plugins, which delegate
 * to the browser's own `Intl` timezone database: no timezone data is added to
 * the bundle, and an offset is resolved for the date being converted rather
 * than for today, so a week containing a DST transition comes out right.
 *
 * Nothing here does anything until `configureTimezone()` has been handed the
 * `timezone` block from the booking panel structure, and with the setting off
 * every export below behaves exactly as the code it replaced.
 */

import { useEffect, useState } from "react";
import dayjs from "dayjs";
import utc from "dayjs/plugin/utc";
import timezone from "dayjs/plugin/timezone";
import { siteLocale } from "./locale.js";

dayjs.extend(utc);
dayjs.extend(timezone);

/**
 * Where a visitor's choice is remembered.
 *
 * A cookie would be the obvious pick (and is what other plugins use), but page
 * caches vary on or strip cookies, and nothing server-side needs to read this:
 * the panel always posts the chosen zone explicitly.
 */
const STORAGE_KEY = "rox_appointment_booking_timezone";

/**
 * Settings > General can leave the site timezone as a bare UTC offset instead
 * of a city, and `dayjs.tz()` will not accept one of those. Same pattern
 * `locale.js` already matches for `getSiteNow()`.
 */
const FIXED_OFFSET = /^([+-])(\d{2}):(\d{2})$/;

let settings = {
  selectorEnabled: false,
  siteTimezone: "",
  zones: [],
};

/** Resolved once per page — see `getCustomerTimezone()`. */
let resolved = null;

const listeners = new Set();

/**
 * Minutes east of UTC for a `+HH:MM` match.
 *
 * @param {string[]} match Result of `FIXED_OFFSET.exec()`.
 * @return {number} Signed minutes.
 */
const offsetMinutes = (match) =>
  (match[1] === "-" ? -1 : 1) * (Number(match[2]) * 60 + Number(match[3]));

/**
 * Whether a string names a zone this browser can actually convert into.
 *
 * @param {*} zone Candidate identifier.
 * @return {boolean} True when usable.
 */
const isUsableZone = (zone) => {
  if (typeof zone !== "string" || zone === "") {
    return false;
  }

  if (FIXED_OFFSET.test(zone)) {
    return true;
  }

  try {
    // Throws a RangeError on anything Intl does not recognise.
    new Intl.DateTimeFormat("en-US", { timeZone: zone });
    return true;
  } catch (error) {
    return false;
  }
};

/**
 * A zone's offset from UTC, in minutes, at a given moment.
 *
 * @param {string} zone Zone identifier or bare offset.
 * @param {Date} at Moment to read it at.
 * @return {number|null} Minutes, or null when the zone is unreadable.
 */
const offsetAt = (zone, at) => {
  const fixed = FIXED_OFFSET.exec(zone);

  if (fixed) {
    return offsetMinutes(fixed);
  }

  try {
    return dayjs(at).tz(zone).utcOffset();
  } catch (error) {
    return null;
  }
};

/**
 * Whether two zones show the same clock all year.
 *
 * Sampled across the next twelve months rather than read once: Europe/London
 * and Africa/Abidjan agree every winter and part by an hour every summer, and
 * treating them as one zone in January would quietly run an hour out in July.
 *
 * @param {string} a First zone.
 * @param {string} b Second zone.
 * @return {boolean} True when they never differ.
 */
const keepsSameClock = (a, b) => {
  if (a === b) {
    return true;
  }

  const now = Date.now();

  for (let quarter = 0; quarter < 4; quarter++) {
    const at = new Date(now + quarter * 91 * 24 * 60 * 60 * 1000);
    const left = offsetAt(a, at);
    const right = offsetAt(b, at);

    if (left === null || right === null || left !== right) {
      return false;
    }
  }

  return true;
};

/**
 * `YYYY-MM-DD` from either a wire string or a Date.
 *
 * A Date is read by its **UTC** fields, because every Date the panel passes
 * around is built with `Date.UTC()` and is read back by the booking POST with
 * `toISOString()`. Reading the same instant with local fields instead would
 * land a day earlier for any visitor west of UTC — which is exactly the
 * audience this module exists for.
 *
 * @param {string|Date} value Date to normalise.
 * @return {string} Wire date.
 */
export const asWireDate = (value) => {
  if (value instanceof Date) {
    return value.toISOString().slice(0, 10);
  }

  if (typeof value === "string") {
    return value.slice(0, 10);
  }

  return value ? new Date(value).toISOString().slice(0, 10) : "";
};

const asDate = asWireDate;

/**
 * `HH:MM:SS`, accepting the `HH:MM` the schedule endpoint sometimes returns.
 *
 * @param {string} value Clock time.
 * @return {string} Wire time.
 */
const asTime = (value) => {
  const parts = String(value || "").trim().split(":");
  const pad = (n) => String(n || "0").padStart(2, "0");
  return `${pad(parts[0])}:${pad(parts[1])}:${pad(parts[2])}`;
};

/**
 * A wall-clock pair in a given zone, as a real instant.
 *
 * @param {string} date Wire date.
 * @param {string} time Wire time.
 * @param {string} zone Zone the pair is written in.
 * @return {import('dayjs').Dayjs} The instant.
 */
const wallToInstant = (date, time, zone) => {
  const stamp = `${date} ${time}`;
  const fixed = FIXED_OFFSET.exec(zone);

  if (fixed) {
    return dayjs.utc(stamp).subtract(offsetMinutes(fixed), "minute");
  }

  try {
    return dayjs.tz(stamp, zone);
  } catch (error) {
    // An unusable zone should have been filtered out long before here; read
    // the pair as UTC rather than throwing inside a render.
    return dayjs.utc(stamp);
  }
};

/**
 * An instant, read as a wall-clock pair in a given zone.
 *
 * @param {import('dayjs').Dayjs} instant The instant.
 * @param {string} zone Zone to read it in.
 * @return {{date: string, time: string}} Wire pair.
 */
const instantToWall = (instant, zone) => {
  const fixed = FIXED_OFFSET.exec(zone);
  let at;

  if (fixed) {
    at = instant.utc().add(offsetMinutes(fixed), "minute");
  } else {
    try {
      at = instant.tz(zone);
    } catch (error) {
      at = instant.utc();
    }
  }

  return { date: at.format("YYYY-MM-DD"), time: at.format("HH:mm:ss") };
};

/**
 * Hand the module the `timezone` block from the booking panel structure.
 *
 * @param {object} [config] Panel timezone config.
 * @return {void}
 */
export const configureTimezone = (config) => {
  if (!config || typeof config !== "object") {
    return;
  }

  settings = {
    selectorEnabled: Boolean(config.selectorEnabled),
    siteTimezone: typeof config.siteTimezone === "string" ? config.siteTimezone : "",
    zones: Array.isArray(config.zones) ? config.zones : [],
  };

  listeners.forEach((listener) => listener());
};

/**
 * The zone bookings are stored and scheduled in.
 *
 * Prefers the panel config, falling back to the locale payload PHP publishes
 * on every page — so this answers correctly even before the panel structure
 * has loaded.
 *
 * @return {string} Zone identifier or bare offset.
 */
export const getSiteTimezone = () =>
  settings.siteTimezone || siteLocale.timezone || "UTC";

/**
 * The zone the visitor's own device is set to.
 *
 * @return {string} Zone identifier, or an empty string when unavailable.
 */
export const detectBrowserTimezone = () => {
  try {
    return Intl.DateTimeFormat().resolvedOptions().timeZone || "";
  } catch (error) {
    return "";
  }
};

/**
 * Read the remembered choice. Storage can throw in a private window.
 *
 * @return {string} Stored zone, or an empty string.
 */
const readStored = () => {
  try {
    return window.localStorage.getItem(STORAGE_KEY) || "";
  } catch (error) {
    return "";
  }
};

/**
 * The zone to show the customer their times in.
 *
 * Remembered choice first, then the device's own zone, then the site's. The
 * answer is cached for the page so every surface agrees within one render.
 *
 * @return {string} Zone identifier.
 */
export const getCustomerTimezone = () => {
  if (resolved) {
    return resolved;
  }

  const stored = readStored();

  if (isUsableZone(stored)) {
    resolved = stored;
    return resolved;
  }

  const detected = detectBrowserTimezone();
  const site = getSiteTimezone();

  if (!isUsableZone(detected)) {
    resolved = site;
    return resolved;
  }

  // A device can name a zone its owner has never heard of for their own
  // offset — Windows set to UTC+6 in Dhaka reports Asia/Omsk. When the
  // detected zone keeps the same clock as the site all year there is nothing
  // to convert, so the site's own name is the one worth showing.
  resolved = keepsSameClock(detected, site) ? site : detected;

  return resolved;
};

/**
 * Change the zone and remember it, telling every subscriber.
 *
 * @param {string} zone Zone identifier.
 * @return {boolean} False when the zone was rejected.
 */
export const setCustomerTimezone = (zone) => {
  if (!isUsableZone(zone) || zone === resolved) {
    return false;
  }

  resolved = zone;

  try {
    window.localStorage.setItem(STORAGE_KEY, zone);
  } catch (error) {
    // Remembering is a convenience; the choice still applies to this page.
  }

  listeners.forEach((listener) => listener());

  return true;
};

/**
 * Subscribe to zone and config changes.
 *
 * @param {Function} listener Called after every change.
 * @return {Function} Unsubscribe.
 */
export const subscribeTimezone = (listener) => {
  listeners.add(listener);
  return () => listeners.delete(listener);
};

/**
 * Re-render a component whenever the visitor's zone changes.
 *
 * One visitor has one timezone, so two panels on the same page share it and
 * both update together.
 *
 * @return {string} The current zone.
 */
export const useCustomerTimezone = () => {
  const [zone, setZone] = useState(getCustomerTimezone);

  useEffect(() => subscribeTimezone(() => setZone(getCustomerTimezone())), []);

  return zone;
};

/**
 * Whether the selector should be rendered at all.
 *
 * @return {boolean} True when the setting is on.
 */
export const timezoneSelectorEnabled = () => settings.selectorEnabled;

/**
 * The grouped zone list for the selector.
 *
 * @return {Array} antd-shaped option groups.
 */
export const timezoneZones = () => settings.zones;

/**
 * Whether times actually need moving.
 *
 * False when the setting is off and false when the visitor is already on the
 * site's clock — in both cases every caller falls through to the behaviour it
 * had before this module existed.
 *
 * @return {boolean} True when a conversion is required.
 */
export const isTimezoneShiftOn = () =>
  settings.selectorEnabled && getCustomerTimezone() !== getSiteTimezone();

/**
 * A site wall-clock pair, as the customer's.
 *
 * @param {string|Date} date Site date.
 * @param {string} time Site time.
 * @return {{date: string, time: string}} Customer pair.
 */
export const toCustomer = (date, time) => {
  const from = asDate(date);
  const at = asTime(time);

  if (!isTimezoneShiftOn()) {
    return { date: from, time: at };
  }

  return instantToWall(
    wallToInstant(from, at, getSiteTimezone()),
    getCustomerTimezone()
  );
};

/**
 * A customer wall-clock pair, as the site's — the pair that gets posted.
 *
 * @param {string|Date} date Customer date.
 * @param {string} time Customer time.
 * @return {{date: string, time: string}} Site pair.
 */
export const toSite = (date, time) => {
  const from = asDate(date);
  const at = asTime(time);

  if (!isTimezoneShiftOn()) {
    return { date: from, time: at };
  }

  return instantToWall(
    wallToInstant(from, at, getCustomerTimezone()),
    getSiteTimezone()
  );
};

/**
 * A 24-hour clock string as the site language writes it.
 *
 * Same formatting the booking panel has always used for a slot — Day.js runs
 * on the WordPress-derived locale, so the meridiem follows the site language.
 *
 * @param {string} time Clock time.
 * @return {string} Display time.
 */
export const formatClock = (time) => {
  const [hours, minutes] = asTime(time).split(":");

  return dayjs()
    .hour(Number(hours))
    .minute(Number(minutes))
    .format("h:mm A");
};

/**
 * A stored (site) slot, written the way the customer should read it.
 *
 * Just the clock — the selector above the list already names the zone every
 * time on screen is being read in, so repeating it on each one is noise.
 *
 * @param {string|Date} date Site date.
 * @param {string} time Site time.
 * @return {string} Display label.
 */
export const formatSlotLabel = (date, time) =>
  formatClock(toCustomer(date, time).time);

/**
 * A site slot as an unambiguous UTC calendar stamp (`20260923T020000Z`).
 *
 * For handing a slot to an external calendar when the site timezone is a bare
 * offset and so cannot be named to the other service.
 *
 * @param {string|Date} date Site date.
 * @param {string} time Site time.
 * @return {string} Stamp.
 */
export const toUtcStamp = (date, time) =>
  wallToInstant(asWireDate(date), asTime(time), getSiteTimezone())
    .utc()
    .format("YYYYMMDDTHHmmss") + "Z";

/**
 * A stored booking's day and clock, both read on the customer's calendar.
 *
 * A shift can move the day as well as the hour, so the two have to be read
 * together — formatting the date on its own would keep showing the site's day
 * beside a converted time.
 *
 * @param {string|Date} date Site date.
 * @param {string} time Site time.
 * @param {string} [format] Day.js format for the date part.
 * @return {{date: string, time: string}} Ready-to-render strings.
 */
export const formatSlotParts = (date, time, format = "MMMM D") => {
  const on = toCustomer(date, time);

  return {
    // Parsed from the wire string rather than handed a Date, so the day cannot
    // drift by one on a device in another zone.
    date: dayjs(on.date).format(format),
    time: on.time ? formatClock(on.time) : "",
  };
};
