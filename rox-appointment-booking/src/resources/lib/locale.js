/**
 * One source of truth for how dates read in the site's language.
 *
 * The date UIs the plugin renders — antd's DatePicker/TimePicker/Calendar,
 * FullCalendar, and every `dayjs().format()` call — carry their own English
 * locale data inside the shared `vendors` chunk, so without this module a
 * German or Spanish site still shows "January" and "Mon". Bundling one locale
 * file per library per language is not an option (the vendors chunk is already
 * megabytes), so instead the names come from WordPress itself: PHP publishes
 * the active WP_Locale tables on `window.rox_appointment_booking.l10n` (see
 * `src/functions/locale.php`) and this module feeds them to all three
 * libraries.
 *
 * Importing this module registers and activates the Day.js locale as a side
 * effect. Entry bundles import it once, before anything renders a date.
 */

import dayjs from "dayjs";
import localeData from "dayjs/plugin/localeData";
import weekday from "dayjs/plugin/weekday";
import enUS from "antd/es/locale/en_US";
import { __, sprintf } from "@wordpress/i18n";

// antd's picker calls `dayjs().localeData()` / `.weekday()`, and so do our own
// helpers. rc-picker extends the same plugins; extending twice is a no-op.
dayjs.extend(localeData);
dayjs.extend(weekday);

/**
 * Name the WordPress-derived Day.js locale is registered under.
 *
 * A fixed private name (rather than the real language code) keeps the locale
 * from colliding with a Day.js locale file another plugin may have registered,
 * and guarantees antd resolves to ours: rc-picker turns the antd locale's
 * `locale` field into a Day.js name by splitting on `_`, so a name with no
 * underscore survives that round trip unchanged.
 */
const DAYJS_LOCALE = "rox";

const FALLBACK = {
  locale: "en_US",
  language: "en",
  bcp47: "en",
  direction: "ltr",
  startOfWeek: 0,
  months: [
    "January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December",
  ],
  monthsShort: [
    "Jan", "Feb", "Mar", "Apr", "May", "Jun",
    "Jul", "Aug", "Sep", "Oct", "Nov", "Dec",
  ],
  weekdays: [
    "Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday",
  ],
  weekdaysShort: ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"],
  weekdaysMin: ["S", "M", "T", "W", "T", "F", "S"],
  meridiem: { am: "am", pm: "pm", AM: "AM", PM: "PM" },
  dateFormat: "F j, Y",
  timeFormat: "g:i a",
  timezone: "UTC",
};

/**
 * Read the locale payload PHP published, falling back to English.
 *
 * A list is only accepted when it is complete — a half-filled month table would
 * render blank cells, which is worse than English ones.
 *
 * @return {typeof FALLBACK} Locale data.
 */
const readLocaleData = () => {
  const raw = window?.rox_appointment_booking?.l10n;

  if (!raw || typeof raw !== "object") {
    return FALLBACK;
  }

  const list = (value, expected, fallback) =>
    Array.isArray(value) && value.length === expected && value.every(Boolean)
      ? value
      : fallback;

  return {
    ...FALLBACK,
    ...raw,
    months: list(raw.months, 12, FALLBACK.months),
    monthsShort: list(raw.monthsShort, 12, FALLBACK.monthsShort),
    weekdays: list(raw.weekdays, 7, FALLBACK.weekdays),
    weekdaysShort: list(raw.weekdaysShort, 7, FALLBACK.weekdaysShort),
    weekdaysMin: list(raw.weekdaysMin, 7, FALLBACK.weekdaysMin),
    meridiem: { ...FALLBACK.meridiem, ...(raw.meridiem || {}) },
    startOfWeek: Number.isInteger(raw.startOfWeek) ? raw.startOfWeek : 0,
  };
};

const data = readLocaleData();

/**
 * The site's locale data (month/weekday names, week start, text direction).
 *
 * @type {typeof FALLBACK}
 */
export const siteLocale = data;

/**
 * BCP 47 tag for `Intl` and FullCalendar, e.g. `de-DE`.
 *
 * @return {string} Language tag.
 */
export const getLocaleTag = () => data.bcp47 || "en";

/**
 * The current moment on the site's clock, in the wire formats slots use.
 *
 * Schedules, slots and bookings are all naive site-local wall time, so "now"
 * has to be read in the site's zone rather than the visitor's — a customer
 * browsing from another timezone would otherwise get the cutoff shifted by
 * their own offset. Settings > General can leave `wp_timezone_string()` as a
 * bare GMT offset ("+06:00"), which `Intl` will not accept as a zone, so those
 * are applied by hand against UTC.
 *
 * Note this reads the DEVICE clock and only corrects its timezone; it cannot
 * detect a device whose clock is simply wrong. That is why the server has to
 * re-check the slot on submit rather than trusting what the panel offered.
 *
 * @param {number} [offsetMinutes] Minutes to advance past now, e.g. a
 *                                 minimum-notice window.
 * @return {{date: string, time: string}} `YYYY-MM-DD` and 24-hour `HH:MM:SS`.
 */
export const getSiteNow = (offsetMinutes = 0) => {
  const now = new Date();
  const offset = /^([+-])(\d{2}):(\d{2})$/.exec(data.timezone || "");

  const read = (at, timeZone) =>
    new Intl.DateTimeFormat("en-CA", {
      timeZone,
      year: "numeric",
      month: "2-digit",
      day: "2-digit",
      hour: "2-digit",
      minute: "2-digit",
      second: "2-digit",
      hour12: false,
    }).formatToParts(at);

  let parts;
  try {
    parts = offset
      ? read(
          new Date(
            now.getTime() +
              (offset[1] === "-" ? -1 : 1) *
                (parseInt(offset[2], 10) * 60 + parseInt(offset[3], 10)) *
                60000,
          ),
          "UTC",
        )
      : read(now, data.timezone);
  } catch (error) {
    // An unrecognised zone name throws; the visitor's own clock is a better
    // answer than none.
    parts = read(now, undefined);
  }

  const part = (type) => (parts.find((p) => p.type === type) || {}).value || "00";
  // `hour12: false` reports midnight as "24" in some engines.
  const hour = part("hour") === "24" ? "00" : part("hour");

  // These are already site wall-clock fields, so the offset is applied through
  // UTC — the one zone that cannot add or drop an hour underneath the
  // arithmetic — and the same round trip normalises any rollover.
  const at = new Date(
    Date.UTC(
      Number(part("year")),
      Number(part("month")) - 1,
      Number(part("day")),
      Number(hour),
      Number(part("minute")) + (Number(offsetMinutes) || 0),
      Number(part("second")),
    ),
  );

  const pad = (value) => String(value).padStart(2, "0");

  return {
    date: `${at.getUTCFullYear()}-${pad(at.getUTCMonth() + 1)}-${pad(at.getUTCDate())}`,
    time: `${pad(at.getUTCHours())}:${pad(at.getUTCMinutes())}:${pad(at.getUTCSeconds())}`,
  };
};

/**
 * Month names in the site's language.
 *
 * @param {boolean} [short] Return the abbreviated names.
 * @return {string[]} Twelve month names, January first.
 */
export const getMonthNames = (short = false) =>
  short ? data.monthsShort : data.months;

// Register WordPress's names as a Day.js locale and make it the default, so
// every `dayjs(...).format('MMMM')` in the app is localised without each call
// site having to opt in.
dayjs.locale(
  DAYJS_LOCALE,
  {
    name: DAYJS_LOCALE,
    months: data.months,
    monthsShort: data.monthsShort,
    weekdays: data.weekdays,
    weekdaysShort: data.weekdaysShort,
    weekdaysMin: data.weekdaysMin,
    weekStart: data.startOfWeek,
    // WordPress has no ordinal table, and most languages write a plain number
    // anyway. Defined so the `Do` token cannot throw.
    ordinal: (n) => n,
    meridiem: (hour, minute, isLowercase) => {
      const key = hour < 12 ? "am" : "pm";
      return isLowercase ? data.meridiem[key] : data.meridiem[key.toUpperCase()];
    },
  },
  true,
);
dayjs.locale(DAYJS_LOCALE);

/**
 * Parse a clock string back into a Day.js instance.
 *
 * Day.js can *format* a localized meridiem but cannot reliably parse one back:
 * `customParseFormat` walks hours 1–24, takes the first whose meridiem marker
 * appears in the input, then calls it PM only when that hour is > 12 — so a
 * marker produced by an `hour < 12 ? am : pm` function always resolves at hour
 * 12 and noon is read as midnight. Once the marker is not plain "AM"/"PM" it
 * stops matching at all. The slot picker round-trips these strings (formats a
 * slot, parses it back to add the duration, and again before POSTing), so the
 * marker is compared against the site's own am/pm strings here instead, with
 * ASCII accepted as a fallback for values written by other code paths.
 *
 * Accepts "H:mm", "H:mm:ss" and either of those followed by a meridiem marker.
 *
 * @param {string} value Clock string, e.g. "02:30 PM" or "14:30:00".
 * @return {import('dayjs').Dayjs|null} Parsed time on an arbitrary date, or
 *                                      null when the string is not a clock time.
 */
export const parseTimeOfDay = (value) => {
  if (typeof value !== "string") {
    return null;
  }

  const match = value
    .trim()
    .match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?(?:\s*(\S+))?$/);
  if (!match) {
    return null;
  }

  let hours = parseInt(match[1], 10);
  const minutes = parseInt(match[2], 10);
  const seconds = parseInt(match[3] || "0", 10);
  const marker = (match[4] || "").toLowerCase();

  if (marker) {
    const lower = (v) => String(v).toLowerCase();
    const isPm = [data.meridiem.pm, data.meridiem.PM, "pm"].map(lower);
    const isAm = [data.meridiem.am, data.meridiem.AM, "am"].map(lower);

    if (isPm.includes(marker)) {
      if (hours < 12) hours += 12;
    } else if (isAm.includes(marker)) {
      if (hours === 12) hours = 0;
    } else {
      return null;
    }
  }

  if (hours > 23 || minutes > 59 || seconds > 59) {
    return null;
  }

  // Anchored to a fixed date: callers only ever read the time back out, and a
  // fixed day keeps a DST transition from shifting the result.
  return dayjs("2000-01-01").hour(hours).minute(minutes).second(seconds);
};

/**
 * antd locale object for `<ConfigProvider locale={...}>`.
 *
 * Two things happen here. The month and weekday grids follow WordPress because
 * `locale` / `lang.locale` point at the Day.js locale registered above — that is
 * how rc-picker looks up its names. The visible strings antd would otherwise
 * take from its bundled English locale are re-declared through the plugin text
 * domain so they land in the POT and are translatable like the rest of the UI;
 * spreading antd's `en_US` underneath keeps any key not listed here working.
 *
 * @return {object} antd locale.
 */
export const getAntdLocale = () => ({
  ...enUS,
  locale: DAYJS_LOCALE,
  global: {
    ...enUS.global,
    placeholder: __("Please select", "rox-appointment-booking"),
    close: __("Close", "rox-appointment-booking"),
  },
  Pagination: {
    ...enUS.Pagination,
    items_per_page: __("/ page", "rox-appointment-booking"),
    jump_to: __("Go to", "rox-appointment-booking"),
    jump_to_confirm: __("confirm", "rox-appointment-booking"),
    page: __("Page", "rox-appointment-booking"),
    prev_page: __("Previous Page", "rox-appointment-booking"),
    next_page: __("Next Page", "rox-appointment-booking"),
    prev_5: __("Previous 5 Pages", "rox-appointment-booking"),
    next_5: __("Next 5 Pages", "rox-appointment-booking"),
    prev_3: __("Previous 3 Pages", "rox-appointment-booking"),
    next_3: __("Next 3 Pages", "rox-appointment-booking"),
    page_size: __("Page Size", "rox-appointment-booking"),
  },
  DatePicker: {
    ...enUS.DatePicker,
    lang: {
      ...enUS.DatePicker.lang,
      locale: DAYJS_LOCALE,
      placeholder: __("Select date", "rox-appointment-booking"),
      yearPlaceholder: __("Select year", "rox-appointment-booking"),
      quarterPlaceholder: __("Select quarter", "rox-appointment-booking"),
      monthPlaceholder: __("Select month", "rox-appointment-booking"),
      weekPlaceholder: __("Select week", "rox-appointment-booking"),
      rangePlaceholder: [
        __("Start date", "rox-appointment-booking"),
        __("End date", "rox-appointment-booking"),
      ],
      rangeYearPlaceholder: [
        __("Start year", "rox-appointment-booking"),
        __("End year", "rox-appointment-booking"),
      ],
      rangeMonthPlaceholder: [
        __("Start month", "rox-appointment-booking"),
        __("End month", "rox-appointment-booking"),
      ],
      rangeWeekPlaceholder: [
        __("Start week", "rox-appointment-booking"),
        __("End week", "rox-appointment-booking"),
      ],
      today: __("Today", "rox-appointment-booking"),
      now: __("Now", "rox-appointment-booking"),
      backToToday: __("Back to today", "rox-appointment-booking"),
      ok: __("OK", "rox-appointment-booking"),
      clear: __("Clear", "rox-appointment-booking"),
      month: __("Month", "rox-appointment-booking"),
      year: __("Year", "rox-appointment-booking"),
      timeSelect: __("Select time", "rox-appointment-booking"),
      dateSelect: __("Select date", "rox-appointment-booking"),
      weekSelect: __("Choose a week", "rox-appointment-booking"),
      monthSelect: __("Choose a month", "rox-appointment-booking"),
      yearSelect: __("Choose a year", "rox-appointment-booking"),
      decadeSelect: __("Choose a decade", "rox-appointment-booking"),
      previousMonth: __("Previous month (PageUp)", "rox-appointment-booking"),
      nextMonth: __("Next month (PageDown)", "rox-appointment-booking"),
      previousYear: __("Last year (Control + left)", "rox-appointment-booking"),
      nextYear: __("Next year (Control + right)", "rox-appointment-booking"),
      previousDecade: __("Last decade", "rox-appointment-booking"),
      nextDecade: __("Next decade", "rox-appointment-booking"),
      previousCentury: __("Last century", "rox-appointment-booking"),
      nextCentury: __("Next century", "rox-appointment-booking"),
    },
    timePickerLocale: {
      ...enUS.DatePicker.timePickerLocale,
      placeholder: __("Select time", "rox-appointment-booking"),
      rangePlaceholder: [
        __("Start time", "rox-appointment-booking"),
        __("End time", "rox-appointment-booking"),
      ],
    },
  },
  TimePicker: {
    ...enUS.TimePicker,
    placeholder: __("Select time", "rox-appointment-booking"),
    rangePlaceholder: [
      __("Start time", "rox-appointment-booking"),
      __("End time", "rox-appointment-booking"),
    ],
  },
  Calendar: {
    ...enUS.Calendar,
    lang: {
      ...enUS.Calendar.lang,
      locale: DAYJS_LOCALE,
      today: __("Today", "rox-appointment-booking"),
      month: __("Month", "rox-appointment-booking"),
      year: __("Year", "rox-appointment-booking"),
    },
  },
  Table: {
    ...enUS.Table,
    filterTitle: __("Filter menu", "rox-appointment-booking"),
    filterConfirm: __("OK", "rox-appointment-booking"),
    filterReset: __("Reset", "rox-appointment-booking"),
    filterEmptyText: __("No filters", "rox-appointment-booking"),
    filterCheckAll: __("Select all items", "rox-appointment-booking"),
    filterSearchPlaceholder: __("Search in filters", "rox-appointment-booking"),
    emptyText: __("No data", "rox-appointment-booking"),
    selectAll: __("Select current page", "rox-appointment-booking"),
    selectInvert: __("Invert current page", "rox-appointment-booking"),
    selectNone: __("Clear all data", "rox-appointment-booking"),
    selectionAll: __("Select all data", "rox-appointment-booking"),
    sortTitle: __("Sort", "rox-appointment-booking"),
    expand: __("Expand row", "rox-appointment-booking"),
    collapse: __("Collapse row", "rox-appointment-booking"),
    triggerDesc: __("Click to sort descending", "rox-appointment-booking"),
    triggerAsc: __("Click to sort ascending", "rox-appointment-booking"),
    cancelSort: __("Click to cancel sorting", "rox-appointment-booking"),
  },
  Modal: {
    ...enUS.Modal,
    okText: __("OK", "rox-appointment-booking"),
    cancelText: __("Cancel", "rox-appointment-booking"),
    justOkText: __("OK", "rox-appointment-booking"),
  },
  Popconfirm: {
    ...enUS.Popconfirm,
    okText: __("OK", "rox-appointment-booking"),
    cancelText: __("Cancel", "rox-appointment-booking"),
  },
  Upload: {
    ...enUS.Upload,
    uploading: __("Uploading...", "rox-appointment-booking"),
    removeFile: __("Remove file", "rox-appointment-booking"),
    uploadError: __("Upload error", "rox-appointment-booking"),
    previewFile: __("Preview file", "rox-appointment-booking"),
    downloadFile: __("Download file", "rox-appointment-booking"),
  },
  Empty: {
    ...enUS.Empty,
    description: __("No data", "rox-appointment-booking"),
  },
  Text: {
    ...enUS.Text,
    edit: __("Edit", "rox-appointment-booking"),
    copy: __("Copy", "rox-appointment-booking"),
    copied: __("Copied", "rox-appointment-booking"),
    expand: __("Expand", "rox-appointment-booking"),
    collapse: __("Collapse", "rox-appointment-booking"),
  },
  Form: {
    ...enUS.Form,
    optional: __("(optional)", "rox-appointment-booking"),
  },
  Image: {
    ...enUS.Image,
    preview: __("Preview", "rox-appointment-booking"),
  },
});

/**
 * FullCalendar locale object for the `locale` option.
 *
 * `code` is the BCP 47 tag FullCalendar hands to `Intl` when it formats the
 * toolbar title and day headers, and `week.dow` makes the grid start on the
 * day configured in Settings > General. The button and helper texts go through
 * the plugin text domain for the same reason as the antd strings above.
 *
 * @return {object} FullCalendar locale.
 */
export const getFullCalendarLocale = () => ({
  code: getLocaleTag(),
  week: {
    dow: data.startOfWeek,
    doy: 4,
  },
  direction: data.direction,
  buttonText: {
    prev: __("Prev", "rox-appointment-booking"),
    next: __("Next", "rox-appointment-booking"),
    today: __("Today", "rox-appointment-booking"),
    year: __("Year", "rox-appointment-booking"),
    month: __("Month", "rox-appointment-booking"),
    week: __("Week", "rox-appointment-booking"),
    day: __("Day", "rox-appointment-booking"),
    list: __("List", "rox-appointment-booking"),
  },
  weekText: __("W", "rox-appointment-booking"),
  weekTextLong: __("Week", "rox-appointment-booking"),
  allDayText: __("All day", "rox-appointment-booking"),
  // Same string FullCalendarView's `moreLinkContent` renders, so a translator
  // only ever sees one "N more" phrase.
  moreLinkText: (n) =>
    sprintf(
      /* translators: %d: number of additional events */
      __("%d more", "rox-appointment-booking"),
      n,
    ),
  noEventsText: __("No events to display", "rox-appointment-booking"),
});
