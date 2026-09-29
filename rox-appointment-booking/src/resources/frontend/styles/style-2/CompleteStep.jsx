/**
 * Style 2's Complete step — the receipt shown once the booking is paid for.
 *
 * Its own file rather than Style 1's `OrderConfirmation`, which the panel used
 * to bridge to: that receipt is built around a QR code, an Add to Calendar
 * link, a location block and a cost breakdown, none of which this design draws.
 * Restyling markup for parts that are not there would have left the rules and
 * the component disagreeing about what the step is.
 *
 * The layout is the Order Details step's, deliberately: the same tinted sheet
 * with white cards on it, the same label/value rows, the same hairlines. A
 * customer arriving here has just come from that step, and the receipt reading
 * as the review they confirmed is the point.
 */

import dayjs from "dayjs";
import { Tooltip } from "antd";
import { useSelect } from "@wordpress/data";
import { __, sprintf } from "@wordpress/i18n";
import { sanitize } from "../../../components/common/Sanitize.js";
import { formatPrice } from "../../../lib/price.js";
import { useBookingStore } from "../../../redux/booking-store-context.js";
import Icon from "../../../components/libs/Icon.jsx";

/**
 * `HH:mm[:ss]` as the site's own locale renders it. Same helper the Order
 * Details step carries, and for the same reason: the WordPress-derived Day.js
 * locale is registered before anything is drawn, so the AM/PM marker follows
 * the site language rather than the browser's default.
 *
 * @param {string} time Raw time.
 * @return {string} Formatted time, or '' when there is none.
 */
const formatTime = (time) => {
	if (!time) return "";

	const [hours, minutes] = String(time).split(":");

	return dayjs().hour(Number(hours)).minute(Number(minutes)).format("h:mm A");
};

/**
 * How the order was paid for, in the customer's words rather than the API's.
 *
 * `later` is "On-site" because that is what the customer chose — the money is
 * handed over at the venue. The Stripe branch has no single method string worth
 * printing (the card brand is not carried back), so anything that is not one of
 * the two known cases reads as paid online.
 *
 * @param {string} method Raw `payment.payment_method`.
 * @return {string} Label for the receipt.
 */
const paymentMethodLabel = (method) => {
	if (method === "later") {
		return __("On-site", "rox-appointment-booking");
	}
	if (method === "free") {
		return __("Free", "rox-appointment-booking");
	}
	return __("Paid online", "rox-appointment-booking");
};

const CompleteStep = ({ bookings, customerInfo, bookingResponse }) => {
	const bookingServiceStore = useBookingStore();
	const content = useSelect(
		(select) => select(bookingServiceStore).getContent(),
		[bookingServiceStore],
	);
	const currencySymbol = content.currencySymbol || "$";

	const list = sanitize(bookings, "array") || [];
	const orderId = bookingResponse?.order?.order_id;

	// What was actually collected, and how. The order's total is what the
	// customer owes; the payment's amount is what has changed hands. They differ
	// on a deposit, so the line reports the order rather than the payment — the
	// receipt is for the booking, not for the transaction.
	const paidLabel = paymentMethodLabel(bookingResponse?.payment?.payment_method);
	const orderTotal = bookingResponse?.order?.total_amount;

	// The browser's own print, on the panel alone. `no-print` on the footer and
	// on this button keeps the controls out of the printed sheet — everything
	// else on the step is the receipt.
	const handlePrint = () => window.print();

	/**
	 * One labelled row. Rendered through a helper rather than repeated so a row
	 * with no value to show drops out entirely instead of printing a label
	 * against an empty column.
	 *
	 * @param {string} label Row label.
	 * @param {*}      value Row value.
	 * @return {JSX.Element|null} The row, or null when there is nothing to say.
	 */
	const row = (label, value) => {
		if (value === undefined || value === null || value === "") {
			return null;
		}

		return (
			<div className="rbs1-done__row" key={label}>
				<span className="rbs1-done__label">{label}</span>
				<span className="rbs1-done__value">{value}</span>
			</div>
		);
	};

	return (
		<div className="rbs1-pane rbs1-done">
			<div className="rbs1-done__sheet">
				{/* The head is a card like the rest, so the print control has an
				    edge to sit against and the sheet's tint runs under it. */}
				<div className="rbs1-done__card rbs1-done__head">
					{/* The panel's own tooltip — antd's, the one the onboarding steps
					    and Style 1's date cells use. The icon carries no label of its
					    own, so this is the only thing naming it on sight; `aria-label`
					    is what names it to a screen reader.

					    Left to portal to the document rather than pinned to the
					    button's own parent, which is what the call sites that sit in a
					    still box do. This one does not: the button is in the top-right
					    corner of the first card, inside a column that scrolls — and a
					    scrolling box clips, since `overflow-y: auto` computes
					    `overflow-x` to auto with it. Held in there the bubble had the
					    card's corner and the scrollbar to fit itself around. From the
					    document it has the window, and antd keeps it on the button as
					    the column moves. Same reason the DOB picker portals out of the
					    fields area (see CustomerInfo). */}
					<Tooltip
						title={__("Print", "rox-appointment-booking")}
						placement="top"
					>
						<button
							type="button"
							className="rbs1-done__print no-print"
							onClick={handlePrint}
							aria-label={__("Print", "rox-appointment-booking")}
						>
							<Icon name="print" size="16" />
						</button>
					</Tooltip>

					{/* The set's own rosette — it is already the design's mark, drawn in
					    the design's blue, so there is nothing here to re-draw. */}
					<span className="rbs1-done__mark">
						<Icon name="checkmark" size="50" />
					</span>

					<h3 className="rbs1-done__title">
						{__("Booking Confirmed", "rox-appointment-booking")}
					</h3>

					{orderId && (
						<p className="rbs1-done__id">
							{sprintf(
								/* translators: %s: the order's id */
								__("Appointment ID: #%s", "rox-appointment-booking"),
								orderId,
							)}
						</p>
					)}
				</div>

				{/* One card per booking. An order can hold several — the panel lets
				    the customer add another before paying — and each is a set of
				    rows of its own rather than a run appended to the last. */}
				{list.map((booking, index) => {
					const date = booking.date ? dayjs(booking.date) : null;
					const when = date
						? `${date.format("ddd, MMM D")}${
								booking.start_time ? `, ${formatTime(booking.start_time)}` : ""
							}`
						: null;
					const extras = sanitize(booking.extraServices, "array");

					return (
						<div
							className="rbs1-done__card rbs1-done__booking"
							key={booking.id ?? `booking-${index}`}
						>
							{row(__("Date:", "rox-appointment-booking"), when)}
							{row(
								__("Service:", "rox-appointment-booking"),
								booking.service?.name,
							)}
							{extras.length > 0 &&
								row(
									__("Add-ons:", "rox-appointment-booking"),
									extras.map((extra) => extra.name).join(", "),
								)}
							{row(
								__("Agent:", "rox-appointment-booking"),
								booking.employee?.name,
							)}
							{row(
								__("Location:", "rox-appointment-booking"),
								booking.location?.name,
							)}
							{/* The order is paid once however many bookings it holds, so
							    the amount is only printed on the first card — repeating it
							    would read as each booking costing the whole order. */}
							{index === 0 &&
								orderTotal !== undefined &&
								row(
									__("Payment:", "rox-appointment-booking"),
									`${formatPrice(orderTotal, currencySymbol)} - ${paidLabel}`,
								)}
						</div>
					);
				})}

				{/* Who it was booked for, as its own card: it belongs to the order
				    rather than to any one booking in it. */}
				{(customerInfo?.first_name ||
					customerInfo?.last_name ||
					customerInfo?.email) && (
					<div className="rbs1-done__card">
						{row(
							__("Your Name:", "rox-appointment-booking"),
							[customerInfo?.first_name, customerInfo?.last_name]
								.filter(Boolean)
								.join(" "),
						)}
						{row(
							__("Email Address:", "rox-appointment-booking"),
							customerInfo?.email,
						)}
					</div>
				)}
			</div>
		</div>
	);
};

export default CompleteStep;
