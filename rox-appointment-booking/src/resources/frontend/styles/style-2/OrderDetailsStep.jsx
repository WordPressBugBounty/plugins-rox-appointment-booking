/**
 * Style 2's Order Details step — the review that sits between Information and
 * Payment in the step list.
 *
 * Every action here is the engine's, not this file's: removing a booking,
 * opening the extra-services picker, dropping an extra, adding another booking
 * and the totals are the same handlers Style 1's SummarySidebar calls, so the
 * two designs can never disagree about what a click does. This file only
 * decides how the review looks.
 */

import dayjs from "dayjs";
import { Fragment } from "@wordpress/element";
import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import { sanitize } from "../../../components/common/Sanitize.js";
import { panelText } from "../../../lib/panelContent.js";
import { formatPrice, priceValue } from "../../../lib/price.js";
import { useBookingStore } from "../../../redux/booking-store-context.js";

/**
 * `HH:mm[:ss]` as the site's own locale renders it — the WordPress-derived
 * Day.js locale is registered by the bundle before anything is drawn, so the
 * AM/PM marker follows the site language rather than the browser's default.
 *
 * @param {string} time Raw time.
 * @return {string} Formatted time, or '' when there is none.
 */
const formatTime = (time) => {
	if (!time) return "";

	const [hours, minutes] = String(time).split(":");

	return dayjs().hour(Number(hours)).minute(Number(minutes)).format("h:mm A");
};

// The two "add" marks. Inline rather than from the icon set: at this size each
// is a couple of line segments, and matching the design's weight matters more
// than the indirection. The bare cross adds a line to something already on the
// order; the ringed one starts a whole booking, and reads as the heavier of
// the two because it is.
const PlusIcon = () => (
	<svg width="12" height="12" viewBox="0 0 12 12" fill="none" aria-hidden="true">
		<path
			d="M6 1.5V10.5M10.5 6H1.5"
			stroke="currentColor"
			strokeWidth="1.6"
			strokeLinecap="round"
		/>
	</svg>
);

const PlusCircleIcon = () => (
	<svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
		<circle cx="9" cy="9" r="7.25" stroke="currentColor" strokeWidth="1.5" />
		<path
			d="M9 5.75V12.25M12.25 9H5.75"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
	</svg>
);

const CloseIcon = () => (
	<svg width="10" height="10" viewBox="0 0 10 10" fill="none" aria-hidden="true">
		<path
			d="M9 1L1 9M1 1L9 9"
			stroke="currentColor"
			strokeWidth="1.5"
			strokeLinecap="round"
		/>
	</svg>
);

const OrderDetailsStep = ({
	bookings,
	total,
	appliedCouponData,
	onDeleteBooking,
	onShowExtraServices,
	onDeleteExtraService,
	onAddNewBooking,
	canAddBooking,
	// The summary row labels an editor can rewrite. Style 1 applies the same
	// two keys in its SummarySidebar, which is the panel this screen replaces.
	panelContent,
}) => {
	const bookingServiceStore = useBookingStore();
	const content = useSelect(
		(select) => select(bookingServiceStore).getContent(),
		[bookingServiceStore],
	);
	const currencySymbol = content.currencySymbol || "$";

	const list = sanitize(bookings, "array");

	// The discount comes off the running total the same way the payment step
	// applies it, and is floored at zero — a coupon worth more than the basket
	// must not read as money owed back.
	const discount = appliedCouponData
		? priceValue(appliedCouponData.discountAmount)
		: 0;
	const grandTotal = Math.max(0, priceValue(total) - discount);

	const row = (label, value) =>
		value ? (
			<div className="rbs1-order__row" key={label}>
				<span className="rbs1-order__label">{label}</span>
				<span className="rbs1-order__value">{value}</span>
			</div>
		) : null;

	return (
		<div className="rbs1-pane rbs1-order">
		<div className="rbs1-order__sheet">
			{list.map((booking, index) => {
				const date = booking.date
					? dayjs(
							booking.date instanceof Date
								? booking.date
								: new Date(booking.date),
						)
					: null;
				const extras = sanitize(booking.extraServices, "array");

				return (
					<Fragment key={booking.id ?? index}>
					<div className="rbs1-order__card">
						<div className="rbs1-order__head">
							<div className="rbs1-order__heading">
								<p className="rbs1-order__service">
									{booking.service?.name ||
										__("Appointment", "rox-appointment-booking")}
								</p>
								{date && (
									<p className="rbs1-order__when">
										{date.format("dddd, MMM D")}
										{booking.start_time
											? `, ${formatTime(booking.start_time)}`
											: ""}
									</p>
								)}
							</div>
							{/* On every booking, the first included: taking the last one
							    out is allowed and drops the visitor back at the start of
							    the flow (see the engine's handleDeleteBooking). */}
							<button
								type="button"
								className="rbs1-order__remove"
								onClick={() => onDeleteBooking?.(booking.id)}
							>
								{__("Remove", "rox-appointment-booking")}
								<CloseIcon />
							</button>
						</div>

						<div className="rbs1-order__body">
							{/* Hidden outright when the surface says so, the same
							    switch Style 1's summary reads. */}
							{!panelContent?.summaryAgentLabelHidden &&
								row(
									panelText(
										panelContent,
										"summaryAgentLabel",
										__("Agent", "rox-appointment-booking"),
									),
									booking.employee?.name,
								)}
							{row(
								__("Location", "rox-appointment-booking"),
								booking.location?.name,
							)}
							{/* Only a group-capacity service tracks a headcount, and only
							    then is it worth a line of its own. */}
							{booking.service?.capacity === "group" &&
								row(
									__("Attendees", "rox-appointment-booking"),
									String(booking.attendees || 1),
								)}
							{!booking.service?.hide_price_booking_panel &&
								row(
									__("Price", "rox-appointment-booking"),
									formatPrice(booking.service?.price, currencySymbol),
								)}
						</div>
					</div>

					{/* A card of its own rather than a band on the booking's: the
					    extras are what can still be changed about it, and the design
					    sets them apart from the appointment they hang off. Its header
					    carries the "add" link when there are extras to head; with none,
					    the link stands alone rather than announcing an empty list. */}
					<div className="rbs1-order__card rbs1-order__extras">
							<div className="rbs1-order__row rbs1-order__row--head">
								{extras.length > 0 && (
									<span className="rbs1-order__label">
										{__("Extra Services", "rox-appointment-booking")}
									</span>
								)}
								<button
									type="button"
									className="rbs1-order__add"
									onClick={() =>
										onShowExtraServices?.(booking.service, booking.id)
									}
								>
									<PlusIcon />
									{__("Add Extra Service", "rox-appointment-booking")}
								</button>
							</div>

							{extras.map((extra) => (
								<div className="rbs1-order__row" key={extra.id}>
									<span className="rbs1-order__label">{extra.name}</span>
									<span className="rbs1-order__extra-value">
										<span className="rbs1-order__value">
											{formatPrice(extra.price, currencySymbol)}
										</span>
										<button
											type="button"
											className="rbs1-order__drop"
											onClick={() =>
												onDeleteExtraService?.(booking.id, extra.id)
											}
											aria-label={__("Remove", "rox-appointment-booking")}
										>
											<CloseIcon />
										</button>
									</span>
								</div>
							))}
					</div>
					</Fragment>
				);
			})}

			{/* The closing card: what can still be added to the order, then what
			    it comes to. */}
			<div className="rbs1-order__card rbs1-order__foot">
				{/* Same rule as Style 1's sidebar: another booking can only be
				    started once this one has a date and a time on it. */}
				{canAddBooking && (
					<button
						type="button"
						className="rbs1-order__new"
						onClick={() => onAddNewBooking?.()}
					>
						<PlusCircleIcon />
						{__("Add a New Booking", "rox-appointment-booking")}
					</button>
				)}

				{appliedCouponData && (
					<div className="rbs1-order__total-row">
						<span className="rbs1-order__label">
							{__("Discount:", "rox-appointment-booking")}
						</span>
						<span className="rbs1-order__value">
							{formatPrice(discount, currencySymbol)}
							{appliedCouponData.discountType === "percentage" &&
								` (${appliedCouponData.discountValue}%)`}
						</span>
					</div>
				)}
				<div className="rbs1-order__total-row rbs1-order__total-row--grand">
					<span>{__("Total Price", "rox-appointment-booking")}</span>
					<span>{formatPrice(grandTotal, currencySymbol)}</span>
				</div>
			</div>
		</div>
		</div>
	);
};

export default OrderDetailsStep;
