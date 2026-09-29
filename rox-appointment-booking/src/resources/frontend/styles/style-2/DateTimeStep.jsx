/**
 * Style 2's Date & Time step.
 *
 * The rules are the older design's, unchanged: the same schedule request, the
 * same holiday / day-off / special-day precedence, the same booking-window
 * cutoff, the same per-day availability fill and the same slot list. Only the
 * markup and the reveal below differ — see `Calendar.jsx`, which Style 1 still
 * renders, for the same logic in Style 1's shape.
 *
 * The reveal: the calendar block is exactly as tall as the step column's
 * visible area (see `.rbs1-cal` in panel.scss), so "Pick a Time" sits just past
 * the fold and the panel opens on the month alone. Choosing a day scrolls the
 * column down to the slots — the panel keeps its 519px whatever is showing.
 */

import { useEffect, useMemo, useRef, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { useSelect } from "@wordpress/data";
import apiFetch from "@wordpress/api-fetch";
import dayjs from "dayjs";
import Icon from "../../../components/libs/Icon.jsx";
import SelectField from "../../../components/BookingService/SelectField.jsx";
import BookingNotice from "../../../components/BookingService/BookingNotice.jsx";
import TimezoneSelect from "../../../components/BookingService/TimezoneSelect.jsx";
import { sanitize } from "../../../components/common/Sanitize.js";
import { useBookingStore } from "../../../redux/booking-store-context.js";
import { getMonthNames, siteLocale, getSiteNow } from "../../../lib/locale.js";
import {
	asWireDate,
	formatSlotLabel,
	isTimezoneShiftOn,
	timezoneSelectorEnabled,
	toCustomer,
	useCustomerTimezone,
} from "../../../lib/timezone.js";

// The gap left above the slots heading once the column has scrolled to it, so
// the heading reads as the top of a section rather than being flush to the edge.
const REVEAL_INSET = 8;

// How far the year picker reaches either side of the site's current year. The
// month arrows still walk anywhere they like — this only bounds the shortcut.
const YEARS_BACK = 1;
const YEARS_AHEAD = 1;

const DateTimeStep = ({
	onDateTimeSelect,
	selectedDate: propSelectedDate,
	selectedStartTime: propSelectedStartTime,
	selectedEndTime: propSelectedEndTime,
	serviceId = 1,
	agentId = 1,
	bookingProcess = [],
	extraServices = [],
	serviceDuration = null,
	isGroupService = false,
}) => {
	// Everything this calendar calls "today" is the site's date, not the
	// visitor's: the schedule, the slots and the bookings behind them are all
	// site-local wall time, so a customer in another timezone has to be shown the
	// business's day boundary rather than their own.
	const siteNow = getSiteNow();
	const [todayYear, todayMonth, todayDay] = siteNow.date.split("-").map(Number);

	const [selectedDate, setSelectedDate] = useState(propSelectedDate);
	const [selectedStartTime, setSelectedStartTime] = useState(propSelectedStartTime);
	const [selectedEndTime, setSelectedEndTime] = useState(propSelectedEndTime);
	const [currentMonth, setCurrentMonth] = useState(todayMonth - 1);
	const [currentYear, setCurrentYear] = useState(todayYear);
	const [scheduleData, setScheduleData] = useState(null);
	const [selectedDateSlots, setSelectedDateSlots] = useState([]);
	const [isLoading, setIsLoading] = useState(false);
	// Whether to offer the visitor a timezone of their own, and which one they
	// are on. Both answer from the config `setContent` handed the timezone
	// module, so they are settled by the time content exists.
	const showTimezone = timezoneSelectorEnabled();
	const customerTimezone = useCustomerTimezone();

	// The slots section, so choosing a day can bring it into view.
	const slotsRef = useRef(null);

	// The stem the two pickers build their aria ids from. Per instance rather
	// than a fixed string: two panels on one page would otherwise both label
	// their month list `rbs1-cal-month`, and a screen reader following the
	// second trigger's `aria-controls` would land on the first one's list.
	const idRef = useRef(null);
	if (!idRef.current) {
		idRef.current = `rbs1-cal-${Math.random().toString(36).slice(2, 8)}`;
	}
	const fieldId = idRef.current;

	// The panel this component belongs to. Two panels on one page each have
	// their own store, so the store is read from context rather than imported.
	const bookingServiceStore = useBookingStore();
	const content = useSelect(
		(select) => select(bookingServiceStore).getContent(),
		[bookingServiceStore],
	);

	// The schedule endpoint returns a per-weekday template, so it carries no
	// notion of "now" — without this, today's morning slots stay bookable all
	// afternoon and a slot two years out stays bookable at all. These two turn it
	// into a real window on the site's clock: nothing before `cutoff`, nothing
	// after `limit`. `limit` is null when the service caps nothing, leaving that
	// end open exactly as before.
	const cutoff = getSiteNow(Number(scheduleData?.minimum_advance_minutes) || 0);
	const maxAdvanceMinutes = Number(scheduleData?.maximum_advance_minutes) || 0;
	const limit = maxAdvanceMinutes > 0 ? getSiteNow(maxAdvanceMinutes) : null;

	// Once a customer is reading the calendar on their own clock, the grid is
	// THEIR calendar: a site slot at 08:00 in Dhaka is the evening before in Los
	// Angeles. Every rule below still decides on site dates — these are the same
	// three boundaries read on the customer's, so the right cells grey out.
	// Same as Style 1's Calendar.jsx.
	const shifting = isTimezoneShiftOn();
	const nowOn = shifting ? toCustomer(siteNow.date, siteNow.time) : siteNow;
	const cutoffOn = shifting ? toCustomer(cutoff.date, cutoff.time) : cutoff;
	const limitOn =
		limit === null ? null : shifting ? toCustomer(limit.date, limit.time) : limit;

	// The day the grid highlights and the slot list belongs to. While shifting
	// it is the customer's date, which no longer maps to one site date — the
	// site pair is recovered from the slot itself, when one is clicked. With no
	// shift it is simply the chosen date, exactly as before this existed.
	const [displayDate, setDisplayDate] = useState(null);
	const activeDate = shifting ? displayDate : selectedDate;
	// The chosen site date as a wire string, for matching the chosen slot while
	// shifting. The parent keeps it as a UTC-midnight Date, so it is read by its
	// UTC fields.
	const selectedSiteDate = selectedDate ? asWireDate(selectedDate) : null;

	// Tell the panel what has been chosen. Same contract as the older design:
	// a new date clears the times rather than carrying a slot from the day
	// before it over onto the new one.
	useEffect(() => {
		if (selectedDate !== propSelectedDate) {
			onDateTimeSelect(selectedDate, null, null);
		} else if (
			selectedDate &&
			selectedStartTime &&
			selectedEndTime &&
			(selectedStartTime !== propSelectedStartTime ||
				selectedEndTime !== propSelectedEndTime)
		) {
			onDateTimeSelect(selectedDate, selectedStartTime, selectedEndTime);
		}
	}, [
		selectedDate,
		selectedStartTime,
		selectedEndTime,
		propSelectedDate,
		propSelectedStartTime,
		propSelectedEndTime,
		onDateTimeSelect,
	]);

	// Helper function to format date to YYYY-MM-DD using local date components (not UTC)
	const formatLocalDate = (date) => {
		const d = date instanceof Date ? date : new Date(date);
		const year = d.getFullYear();
		const month = String(d.getMonth() + 1).padStart(2, "0");
		const day = String(d.getDate()).padStart(2, "0");
		return `${year}-${month}-${day}`;
	};

	// A cart booking's site date. With no shift it is read the way this step has
	// always read it. While shifting, every date it is compared against is a
	// true site date (a wire string), so the booking's UTC-midnight Date has to
	// be read by its UTC fields as well — local fields put it a day early for
	// any visitor west of UTC, the very audience the shift is for.
	const bookingDateOf = (booking) => {
		if (!booking.date) return null;
		return shifting ? asWireDate(booking.date) : formatLocalDate(booking.date);
	};

	// Helper function to check if a slot is already selected for this service+agent.
	// Group-capacity services skip this entirely — the same slot can legitimately
	// hold multiple cart items (separate group bookings) up to the service's
	// max_capacity, enforced server-side at submit; this single-occupancy dedup
	// check exists to stop a customer accidentally double-booking themselves into
	// the exact same slot for a normal (non-group) service.
	// `date` is a site date — a wire string while shifting, since a shifted slot
	// carries its own rather than sharing the list's.
	const isSlotAlreadySelected = (date, time) => {
		if (isGroupService) return false;
		if (!bookingProcess || bookingProcess.length === 0) return false;

		const dateString = typeof date === "string" ? date : formatLocalDate(date);

		return bookingProcess.some((booking) => {
			const bookingDateString = bookingDateOf(booking);

			return (
				booking.service?.id === serviceId &&
				booking.employee?.id === agentId &&
				bookingDateString === dateString &&
				booking.start_time === time
			);
		});
	};

	// Generate days for the current month using useMemo to avoid recalculation on every render
	const days = useMemo(() => {
		const daysInMonth = new Date(currentYear, currentMonth + 1, 0).getDate();
		const firstDayOfMonth = new Date(currentYear, currentMonth, 1).getDay();

		const daysArray = [];
		for (let i = 0; i < firstDayOfMonth; i++) {
			daysArray.push(null); // Empty cells for days before the 1st of the month
		}

		for (let i = 1; i <= daysInMonth; i++) {
			daysArray.push(i);
		}

		return daysArray;
	}, [currentMonth, currentYear]);

	// The years the picker offers. `currentYear` is folded in so navigating past
	// the range with the arrows still leaves the select showing where you are.
	const years = useMemo(() => {
		const list = [];
		for (let year = todayYear - YEARS_BACK; year <= todayYear + YEARS_AHEAD; year++) {
			list.push(year);
		}
		if (!list.includes(currentYear)) {
			list.push(currentYear);
			list.sort((a, b) => a - b);
		}
		return list;
	}, [todayYear, currentYear]);

	useEffect(() => {
		if (!selectedDate) {
			const todayDate = new Date(Date.UTC(todayYear, todayMonth - 1, todayDay));
			setSelectedDate(todayDate);
		}
	}, []);

	// Open on the customer's today rather than the site's, which can already be
	// tomorrow for them.
	useEffect(() => {
		if (!shifting || displayDate) {
			return;
		}

		const [year, month, day] = nowOn.date.split("-").map(Number);
		setDisplayDate(new Date(year, month - 1, day));
	}, [shifting, displayDate, nowOn.date]);

	// Changing zone re-reads every slot on the screen, so a time picked in the
	// old one no longer says what it said. The day survives; the time does not.
	// Guarded by a ref so restoring a saved booking on mount is left alone.
	const knownTimezone = useRef(customerTimezone);
	useEffect(() => {
		if (knownTimezone.current === customerTimezone) {
			return;
		}

		knownTimezone.current = customerTimezone;
		setSelectedStartTime(null);
		setSelectedEndTime(null);
		// Told directly: the effect that reports choices upward only fires for
		// a new date or a new time, never for a time being cleared on the same
		// date, so the panel would otherwise keep the old slot behind a list
		// that no longer shows it as chosen.
		onDateTimeSelect(selectedDate, null, null);
	}, [customerTimezone]);

	// Fetch appointment schedule once on component mount
	useEffect(() => {
		// Check if content is loaded before making API call
		if (!content || !content.content || !content.content.appointmentSchedulesApi) {
			return;
		}

		setIsLoading(true);

		const baseApi = content.content.appointmentSchedulesApi;
		const url = new URL(baseApi, window.location.origin);
		url.searchParams.set("service_id", serviceId);
		// Agent-less services pass no agentId → omit the param so the backend runs its
		// service-capacity (no-agent) branch instead of 400ing on a missing agent.
		if (agentId != null) {
			url.searchParams.set("agent_id", agentId);
		}

		apiFetch({
			url: url.toString(),
		})
			.then((response) => {
				if (response.success && response.data) {
					setScheduleData(response.data);
				}
				setIsLoading(false);
			})
			.catch((error) => {
				console.error("Error fetching schedule:", error);
				setIsLoading(false);
			});
	}, [serviceId, agentId, content]);

	// Helper function to get day name from date
	const getDayName = (date) => {
		const names = [
			"Sunday",
			"Monday",
			"Tuesday",
			"Wednesday",
			"Thursday",
			"Friday",
			"Saturday",
		];
		return names[date.getDay()];
	};

	// Check if a date is a holiday
	const isHoliday = (date) => {
		if (!scheduleData || !scheduleData.holidays) return false;
		const dateString = formatLocalDate(date);
		return scheduleData.holidays.includes(dateString);
	};

	// Helper function to get timeslots array for a specific date
	// Priority: special_days > weekly_schedule (by day of week)
	const getTimeslotsArrayForDate = (date) => {
		if (!scheduleData) return null;

		const dateString = formatLocalDate(date);

		// Check if date is a holiday
		if (
			scheduleData?.holidays &&
			Array.isArray(scheduleData.holidays) &&
			scheduleData.holidays.includes(dateString)
		) {
			return null; // Holiday - no slots
		}

		// Priority 1: Check special_days first
		if (scheduleData?.special_days && Array.isArray(scheduleData.special_days)) {
			const specialDay = scheduleData.special_days.find(
				(day) => day.date === dateString,
			);
			if (specialDay) {
				if (specialDay.day_off === true) {
					return null; // Special day is a day off
				}
				if (specialDay.timeslots && Array.isArray(specialDay.timeslots)) {
					return specialDay.timeslots;
				}
			}
		}

		// Priority 2: Fall back to weekly_schedule
		if (scheduleData?.weekly_schedule && Array.isArray(scheduleData.weekly_schedule)) {
			const dayName = getDayName(date);
			const daySchedule = scheduleData.weekly_schedule.find(
				(d) => d.day_name === dayName,
			);

			if (daySchedule) {
				if (daySchedule.day_off === true) {
					return null; // Day off
				}
				if (daySchedule.timeslots && Array.isArray(daySchedule.timeslots)) {
					return daySchedule.timeslots;
				}
			}
		}

		return null;
	};

	// Check if a date is a day off
	const isDayOff = (date) => {
		if (!scheduleData) return false;
		const dateString = formatLocalDate(date);

		// Check if it's a holiday first
		if (
			scheduleData?.holidays &&
			Array.isArray(scheduleData.holidays) &&
			scheduleData.holidays.includes(dateString)
		) {
			return true;
		}

		// Check special_days first
		if (scheduleData?.special_days && Array.isArray(scheduleData.special_days)) {
			const specialDay = scheduleData.special_days.find(
				(day) => day.date === dateString,
			);
			if (specialDay) {
				return specialDay.day_off === true;
			}
		}

		// Fall back to weekly_schedule
		if (scheduleData?.weekly_schedule) {
			const dayName = getDayName(date);
			const daySchedule = scheduleData.weekly_schedule.find(
				(d) => d.day_name === dayName,
			);
			return daySchedule ? daySchedule.day_off : false;
		}

		return false;
	};

	// Helper function to calculate end time based on start time and duration
	const calculateEndTime = (startTime, durationMinutes) => {
		const [hours, minutes] = startTime.split(":").map(Number);
		const startDate = new Date();
		startDate.setHours(hours, minutes, 0, 0);

		const endDate = new Date(startDate.getTime() + durationMinutes * 60000);

		const endHours = String(endDate.getHours()).padStart(2, "0");
		const endMinutes = String(endDate.getMinutes()).padStart(2, "0");
		const endSeconds = "00";

		return `${endHours}:${endMinutes}:${endSeconds}`;
	};

	// Get timeslots for a specific date (prioritizing special_days)
	const getTimeSlotsForDate = (date) => {
		const timeslotsArray = getTimeslotsArrayForDate(date);

		if (!timeslotsArray || !Array.isArray(timeslotsArray)) return [];

		const dateString = formatLocalDate(date);
		const bookedSlots = sanitize(scheduleData.booked_timeslots, "array");

		// Normalize to "HH:MM" so a stray ":SS" or type difference between the
		// schedule's timeslots and the booked-timeslots payload can't silently
		// make every slot compare as "not booked".
		const normalizeTime = (value) =>
			(typeof value === "string" ? value : "").slice(0, 5);

		// Find booked timeslots for this specific date. booking.date may come
		// back as a full "YYYY-MM-DD HH:MM:SS" — compare on the date portion only.
		const bookedForDate = bookedSlots.find(
			(booking) =>
				typeof booking.date === "string" &&
				booking.date.slice(0, 10) === dateString,
		);
		// Times a booking holds, and times only its buffer holds. Both read as
		// taken: why a time is unavailable is the agent's business, but a time
		// that quietly disappeared from the list would read as a mistake. Style 1
		// does the same in components/BookingService/Calendar.jsx.
		const bookedTimeslots = bookedForDate?.timeslots || [];
		const bufferTimeslots = sanitize(bookedForDate?.buffer_timeslots, "array").map(
			normalizeTime,
		);
		// Calculate total duration: base slot duration + all extra services durations
		const baseSlotDuration = serviceDuration || scheduleData?.slot_duration || 30;
		const extrasDuration = extraServices.reduce(
			(sum, es) => sum + (es.duration || es.duration_minutes || 0),
			0,
		);
		const totalDuration = baseSlotDuration + extrasDuration;

		// Get the last timeslot as the schedule's end boundary
		const lastSlotTime = timeslotsArray[timeslotsArray.length - 1];

		const isPastDate = dateString < cutoff.date;
		const isCutoffDate = dateString === cutoff.date;

		// Convert timeslots to the expected format with booking status
		return sanitize(timeslotsArray, "array").map((time) => {
			// Fix: bookedTimeslots may be objects with a .time property, not plain strings
			let isBooked =
				bookedTimeslots.some(
					(t) =>
						normalizeTime(typeof t === "string" ? t : t.time || t.start_time) ===
						normalizeTime(time),
				) || bufferTimeslots.includes(normalizeTime(time));

			// Don't treat the currently selected time as "booked" — it's just selected.
			// Skipped entirely for group-capacity services: the same slot can hold
			// multiple cart items up to max_capacity, enforced server-side at submit.
			if (!isGroupService && !isBooked && bookingProcess && bookingProcess.length > 0) {
				isBooked = bookingProcess.some((booking) => {
					const bookingDateString = bookingDateOf(booking);
					return (
						booking.service?.id === serviceId &&
						booking.employee?.id === agentId &&
						bookingDateString === dateString &&
						booking.start_time === time &&
						time !== selectedStartTime
					);
				});
			}

			// Outside the booking window on the site's clock — too soon, or further
			// ahead than the service takes bookings. Reads as unavailable, the same
			// as a slot someone else has taken.
			const isElapsed =
				isPastDate ||
				(isCutoffDate && normalizeTime(time) <= normalizeTime(cutoff.time));

			const isBeyondLimit =
				limit !== null &&
				(dateString > limit.date ||
					(dateString === limit.date &&
						normalizeTime(time) > normalizeTime(limit.time)));

			// Check if slot has enough room for total duration (service + extras)
			// A slot is infeasible if its calculated end time exceeds the last available slot
			let isFeasible = true;
			if (extrasDuration > 0 && lastSlotTime) {
				const slotEnd = calculateEndTime(time, totalDuration);
				// calculateEndTime returns HH:MM:SS, normalize lastSlotTime for comparison
				const normalizedLastSlot =
					lastSlotTime.length === 5 ? lastSlotTime + ":00" : lastSlotTime;
				isFeasible = slotEnd <= normalizedLastSlot;
			}

			return {
				time: time,
				// Where this slot really is on the site's clock. `time` is what the
				// customer reads and can be re-written by the shift below; these
				// two are what gets posted, so they never move.
				siteDate: dateString,
				siteTime: time,
				bookedUsers: isBooked || !isFeasible || isElapsed || isBeyondLimit ? 1 : 0,
				maxUsers: 1,
			};
		});
	};

	/**
	 * Every slot in and around this month, filed under the customer's date.
	 *
	 * The schedule endpoint answers per site date, and a shift moves a slot onto
	 * the day before or after for the customer — so the only honest way to fill
	 * a day is to ask for the three site dates that could feed it and keep what
	 * lands. One day either side is enough: the widest gap between two zones is
	 * 26 hours.
	 *
	 * Built once per month (and per zone) rather than per cell, because the grid
	 * alone would otherwise re-derive this for 42 cells three times over.
	 */
	const shiftedSlots = useMemo(() => {
		if (!shifting || !scheduleData) {
			return null;
		}

		const byDate = new Map();
		// The last day of the previous month through the first of the next: one
		// site date either side of every cell the grid can render.
		const from = new Date(currentYear, currentMonth, 0);
		const to = new Date(currentYear, currentMonth + 1, 1);

		for (let at = from; at <= to; at.setDate(at.getDate() + 1)) {
			getTimeSlotsForDate(new Date(at)).forEach((slot) => {
				const on = toCustomer(slot.siteDate, slot.siteTime);

				if (!byDate.has(on.date)) {
					byDate.set(on.date, []);
				}

				byDate.get(on.date).push({ ...slot, time: on.time });
			});
		}

		byDate.forEach((slots) => slots.sort((a, b) => a.time.localeCompare(b.time)));

		return byDate;
	}, [
		shifting,
		customerTimezone,
		scheduleData,
		currentMonth,
		currentYear,
		bookingProcess,
		extraServices,
		serviceId,
		agentId,
		selectedStartTime,
	]);

	/**
	 * The slot list for a date on whichever calendar is being shown.
	 *
	 * @param {Date} date Display date.
	 * @return {Array} Slots.
	 */
	const getSlotsFor = (date) => {
		if (!date) return [];

		return shiftedSlots
			? shiftedSlots.get(formatLocalDate(date)) || []
			: getTimeSlotsForDate(date);
	};

	// Update time slots when a date is selected. Declared down here, below the
	// memo it reads, so the dependency array is evaluated after it exists.
	useEffect(() => {
		if (activeDate && scheduleData) {
			setSelectedDateSlots(getSlotsFor(activeDate));
		} else {
			setSelectedDateSlots([]);
		}
	}, [activeDate, shiftedSlots, scheduleData, bookingProcess, serviceId, agentId]);

	const isDateInPast = (day) => {
		if (!day) return false;
		return formatLocalDate(new Date(currentYear, currentMonth, day)) < nowOn.date;
	};

	// A whole day the booking window rules out — before the minimum, or past the
	// maximum — has nothing pickable on it, so it greys out with the days off
	// instead of opening to a grid of dead slots.
	const isDateOutsideWindow = (day) => {
		if (!day) return false;
		const dateString = formatLocalDate(new Date(currentYear, currentMonth, day));
		return dateString < cutoffOn.date || (limitOn !== null && dateString > limitOn.date);
	};

	const isDateUnavailable = (day) => {
		if (!day) return false;
		const checkDate = new Date(currentYear, currentMonth, day);

		if (shiftedSlots) {
			// Holiday and day-off are facts about a SITE date, and a customer's day
			// can straddle two of them — so the honest test is whether anything
			// reached this day at all. A site holiday can still leave slots here,
			// carried over from the open day beside it; that is correct.
			return (
				isDateInPast(day) ||
				isDateOutsideWindow(day) ||
				getSlotsFor(checkDate).length === 0
			);
		}

		return (
			isDateInPast(day) ||
			isDateOutsideWindow(day) ||
			isHoliday(checkDate) ||
			isDayOff(checkDate)
		);
	};

	// Calculate availability percentage for a day
	const getDayAvailabilityPercentage = (day) => {
		if (!day) return 0;
		// Schedule not loaded yet — default to "fully available" (fill height
		// 100 - 100 = 0%) so the indicator starts empty and grows up to the real
		// value once scheduleData arrives, instead of starting full and shrinking
		// down to it.
		if (!scheduleData) return 100;

		const checkDate = new Date(currentYear, currentMonth, day);

		// Past, outside the booking window, holiday or day off — nothing to offer.
		if (isDateUnavailable(day)) return 0;

		const slots = getSlotsFor(checkDate);
		if (!slots || slots.length === 0) return 0;

		const totalSlots = slots.length;
		const availableSlots = slots.filter(
			(slot) => slot.bookedUsers < slot.maxUsers,
		).length;
		return (availableSlots / totalSlots) * 100;
	};

	// Get available slots count for a day
	const getAvailableSlotsCount = (day) => {
		if (!day || !scheduleData) return 0;

		const checkDate = new Date(currentYear, currentMonth, day);

		// Past, outside the booking window, holiday or day off — nothing to offer.
		if (isDateUnavailable(day)) return 0;

		const slots = getSlotsFor(checkDate);
		if (!slots || slots.length === 0) return 0;

		return slots.filter((slot) => slot.bookedUsers < slot.maxUsers).length;
	};

	// Bring "Pick a Time" into view. The step column is what scrolls on the
	// panel's own layout, so it is moved directly rather than through
	// `scrollIntoView` — which would drag the whole page up with it. Stacked on a
	// narrow screen the column no longer scrolls at all, and there the page IS
	// the scroller, so the fallback is the right answer rather than a compromise.
	const revealSlots = () => {
		const target = slotsRef.current;
		if (!target) return;

		const behavior = window.matchMedia?.("(prefers-reduced-motion: reduce)")?.matches
			? "auto"
			: "smooth";
		const scroller = target.closest(".rbs1-body");

		if (scroller && scroller.scrollHeight > scroller.clientHeight) {
			const delta =
				target.getBoundingClientRect().top - scroller.getBoundingClientRect().top;
			scroller.scrollTo({
				top: scroller.scrollTop + delta - REVEAL_INSET,
				behavior,
			});
			return;
		}

		target.scrollIntoView({ behavior, block: "start" });
	};

	const handleDateClick = (day) => {
		if (day && !isDateUnavailable(day)) {
			const newDate = new Date(Date.UTC(currentYear, currentMonth, day));

			// While shifting, the grid is the customer's calendar, so the day that
			// was clicked is kept here. The site date still goes up — a changed one
			// is what makes the panel drop the times — but it is provisional until
			// a slot names the real one.
			if (shifting) {
				setDisplayDate(new Date(currentYear, currentMonth, day));
			}

			setSelectedDate(newDate);
			setSelectedStartTime(null); // Clear selected times when date changes
			setSelectedEndTime(null);
			// After the slots for the new day have been laid out.
			window.requestAnimationFrame(revealSlots);
		}
	};

	const handleTimeClick = (slot) => {
		if (slot.bookedUsers < slot.maxUsers) {
			// Only allow selection if slot is not fully booked. Always the SITE
			// pair, never what the customer is reading: the server, the conflict
			// check and the booking row all speak site-local wall time.
			const startTime = slot.siteTime || slot.time;
			const slotDuration = scheduleData?.slot_duration || 30; // Default to 30 minutes if not available
			const endTime = calculateEndTime(startTime, slotDuration);

			// A shifted slot may belong to the site day before or after the one the
			// customer clicked, so the provisional date set then is replaced here.
			// Only when it actually differs: the effect that reports upward compares
			// dates by reference, so a fresh Date for the same day would send the
			// panel a cleared time before the real one.
			if (shifting && slot.siteDate && slot.siteDate !== selectedSiteDate) {
				const [year, month, day] = slot.siteDate.split("-").map(Number);
				setSelectedDate(new Date(Date.UTC(year, month - 1, day)));
			}

			setSelectedStartTime(startTime);
			setSelectedEndTime(endTime);
		}
	};

	// WordPress's own month names: translated on any site running a language
	// pack, with no per-plugin translation needed, and identical to the names the
	// admin calendar and antd's pickers render.
	const monthNames = getMonthNames();

	// The rows the two pickers offer. `SelectField` matches an option by strict
	// equality on `value`, so both sides travel as strings and are turned back
	// into numbers on the way out. Cheap enough at twelve and seven rows not to
	// be worth memoising.
	const monthOptions = monthNames.map((name, index) => ({
		value: String(index),
		label: name,
	}));
	const yearOptions = years.map((year) => ({
		value: String(year),
		label: String(year),
	}));

	const goToPreviousMonth = () => {
		if (currentMonth === 0) {
			setCurrentMonth(11);
			setCurrentYear(currentYear - 1);
		} else {
			setCurrentMonth(currentMonth - 1);
		}
	};

	const goToNextMonth = () => {
		setSelectedStartTime(null);
		setSelectedEndTime(null);
		if (currentMonth === 11) {
			setCurrentMonth(0);
			setCurrentYear(currentYear + 1);
		} else {
			setCurrentMonth(currentMonth + 1);
		}
	};

	// Format time from 24h to 12h format. Day.js runs on the WordPress-derived
	// locale, so the AM/PM marker comes from the site language rather than being
	// hardcoded English.
	const formatTime = (time) => {
		const [hours, minutes] = time.split(":");
		return dayjs()
			.hour(parseInt(hours, 10))
			.minute(parseInt(minutes, 10))
			.format("h:mm A");
	};

	// What a chip reads. The start is the time the customer is shown anyway —
	// their own clock while shifting, the site's otherwise — and with Settings →
	// General → Time Slot → "Show a time range" on it carries the end as well:
	// the appointment, extras included, plus the wrap-up when the service asks
	// for the buffer to be shown with it. That length is `slot_range_minutes`,
	// computed server-side; the preparation before a slot is never shown. Style
	// 1 reads the same two keys the same way in
	// components/BookingService/Calendar.jsx.
	const slotLabel = (slot) => {
		const siteDate = slot.siteDate;
		const siteTime = slot.siteTime || slot.time;
		const start = shifting ? formatSlotLabel(siteDate, siteTime) : formatTime(slot.time);
		const minutes =
			Number(scheduleData?.slot_range_minutes ?? scheduleData?.slot_duration) || 0;

		if (!scheduleData?.show_slot_time_range || minutes <= 0) {
			return start;
		}

		const end = calculateEndTime(siteTime, minutes);

		return `${start} - ${shifting ? formatSlotLabel(siteDate, end) : formatTime(end)}`;
	};

	const selectedDateObj =
		activeDate instanceof Date
			? activeDate
			: activeDate
				? new Date(activeDate)
				: null;

	// "Pick a Time — <date>", placed alone or beside the timezone selector below.
	const slotsTitle = (
		<h3 className="rbs1-slots__title">
			<span className="rbs1-slots__label">
				{__("Pick a Time", "rox-appointment-booking")} &mdash;
			</span>{" "}
			<span className="rbs1-slots__date">
				{activeDate ? dayjs(activeDate).format("MMMM D, YYYY") : ""}
			</span>
		</h3>
	);

	return (
		<div className="rbs1-pane rbs1-datetime">
			<div className="rbs1-cal">
				<div className="rbs1-cal__toolbar">
					{/* The shared dropdown, not a native `select`: the design gives
					    the open list a panel of its own with a highlighted row, and a
					    native select's list is drawn by the OS where no stylesheet can
					    reach it. Values travel as strings because that is what the
					    component compares options by. */}
					<span className="rbs1-select">
						<SelectField
							id={`${fieldId}-month`}
							value={String(currentMonth)}
							options={monthOptions}
							onChange={(next) => setCurrentMonth(Number(next))}
							ariaLabel={__("Month", "rox-appointment-booking")}
						/>
					</span>

					<span className="rbs1-select rbs1-select--narrow">
						<SelectField
							id={`${fieldId}-year`}
							value={String(currentYear)}
							options={yearOptions}
							onChange={(next) => setCurrentYear(Number(next))}
							ariaLabel={__("Year", "rox-appointment-booking")}
						/>
					</span>

					<span className="rbs1-cal__nav">
						<button
							type="button"
							className="rbs1-cal__nav-btn"
							onClick={goToPreviousMonth}
							aria-label={__("Previous month", "rox-appointment-booking")}
						>
							<Icon name="leftarrowicon" size="12" />
						</button>
						<button
							type="button"
							className="rbs1-cal__nav-btn"
							onClick={goToNextMonth}
							aria-label={__("Next month", "rox-appointment-booking")}
						>
							<Icon name="rightarrowicon" size="12" />
						</button>
					</span>
				</div>

				{/* Sunday-first to match the grid below, which is laid out from
				    Date#getDay(). Names come from WordPress, so they follow the site
				    language. */}
				<div className="rbs1-cal__weekdays">
					{siteLocale.weekdaysShort.map((day, index) => (
						<span key={index} className="rbs1-cal__weekday">
							{day}
						</span>
					))}
				</div>

				<div className="rbs1-cal__grid">
					{days.map((day, index) => {
						if (day === null) {
							return <span key={index} className="rbs1-cal__day-empty" />;
						}

						const availabilityPercentage = getDayAvailabilityPercentage(day);
						const hasAvailability = availabilityPercentage > 0;
						const isUnavailable = isDateUnavailable(day);
						const availableSlots = getAvailableSlotsCount(day);
						const isSelected =
							selectedDateObj &&
							selectedDateObj.getDate() === day &&
							selectedDateObj.getMonth() === currentMonth &&
							selectedDateObj.getFullYear() === currentYear;

						return (
							<button
								type="button"
								key={index}
								className={[
									"rbs1-cal__day",
									isUnavailable ? "rbs1-cal__day--off" : "",
									!isUnavailable && hasAvailability ? "rbs1-cal__day--open" : "",
									!isUnavailable && !hasAvailability ? "rbs1-cal__day--full" : "",
									isSelected ? "rbs1-cal__day--selected" : "",
								]
									.filter(Boolean)
									.join(" ")}
								disabled={isUnavailable}
								aria-pressed={Boolean(isSelected)}
								// The older design's hover count, as the browser's own
								// tooltip: a tooltip element of ours would be clipped by
								// the step column it scrolls inside, and the top row's —
								// the one most likely to be pointed at — would never show.
								title={
									isUnavailable
										? undefined
										: hasAvailability
											? sprintf(
													/* translators: %d: number of available slots */
													__("%d available", "rox-appointment-booking"),
													availableSlots,
												)
											: __("No slots available", "rox-appointment-booking")
								}
								onClick={() => handleDateClick(day)}
							>
								{/* The availability fill, drawn the same way as the older
								    design: one bar rising from the bottom of the cell whose
								    height is how much of the day is already taken. Only an
								    open day carries it — a day whose slots are all gone
								    reads as a plain cell, as the design has it. */}
								{!isUnavailable && hasAvailability && (
									<span className="rbs1-cal__fill">
										<span
											className="rbs1-cal__fill-bar"
											style={{ height: `${100 - availabilityPercentage}%` }}
										/>
									</span>
								)}
								<span className="rbs1-cal__day-number">{day}</span>
							</button>
						);
					})}
				</div>
			</div>

			<div className="rbs1-slots" ref={slotsRef}>
				{isLoading ? (
					<p className="rbs1-slots__message">
						{__("Loading time slots...", "rox-appointment-booking")}
					</p>
				) : activeDate ? (
					<>
						{/* With a timezone to offer, the heading shares its row with
						    the selector. Without one it is the bare heading, exactly
						    as before the selector existed. */}
						{showTimezone ? (
							<div className="rbs1-slots__head">
								{slotsTitle}
								<TimezoneSelect />
							</div>
						) : (
							slotsTitle
						)}

						{selectedDateSlots.length > 0 ? (
							<div
								className={`rbs1-slots__grid${
									scheduleData?.show_slot_time_range ? " rbs1-slots__grid--range" : ""
								}`}
							>
								{selectedDateSlots.map((slot) => {
									// The pair the server knows this slot by. Identical to
									// what is displayed unless the customer is on another
									// clock.
									const siteDate = slot.siteDate;
									const siteTime = slot.siteTime || slot.time;

									const isAvailable = slot.bookedUsers < slot.maxUsers;
									const isSelected =
										selectedStartTime === siteTime &&
										(!shifting || selectedSiteDate === siteDate);
									const isTaken = isSlotAlreadySelected(
										shifting ? siteDate : selectedDate,
										siteTime,
									);

									return (
										<button
											type="button"
											key={`${siteDate} ${siteTime}`}
											className={`rbs1-slot ${isSelected ? "rbs1-slot--selected" : ""}`}
											// A slot already in the cart for this service and agent
											// is out of reach for the same reason a booked one is,
											// so it reads the same way too.
											disabled={!isAvailable || (isTaken && !isSelected)}
											aria-pressed={isSelected}
											onClick={() => handleTimeClick(slot)}
										>
											{/* Read on the customer's clock while shifting; the
											    site's own label otherwise, as it always was — and
											    as a range when the site asks for one. */}
											{slotLabel(slot)}
										</button>
									);
								})}
							</div>
						) : (
							// The shared notice, not a line of text: a day with nothing
							// bookable on it is the one message here that reports a dead
							// end, and it reads the same as the missing-fields warning on
							// the Information step. Style 1 uses the same component in the
							// same place.
							<BookingNotice className="rbs1-slots__notice">
								{__("Not Available", "rox-appointment-booking")}
							</BookingNotice>
						)}
					</>
				) : (
					<p className="rbs1-slots__message">
						{__(
							"Please select a date to view available time slots.",
							"rox-appointment-booking",
						)}
					</p>
				)}
			</div>
		</div>
	);
};

export default DateTimeStep;
