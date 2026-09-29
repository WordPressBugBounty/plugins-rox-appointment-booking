/**
 * Style 2's Services step: the same row-card pattern as Location/Category,
 * with a duration subtitle and a price on the right instead of a badge.
 */

import { useSelect } from "@wordpress/data";
import { sprintf, __ } from "@wordpress/i18n";
import Icon from "../../../components/libs/Icon.jsx";
import { sanitize } from "../../../components/common/Sanitize.js";
import { formatPrice } from "../../../lib/price.js";
import { useBookingStore } from "../../../redux/booking-store-context.js";
import EmptyState from "./EmptyState.jsx";
import ListSkeleton from "./ListSkeleton.jsx";

const ServicesStep = ({
	services,
	selectedService,
	onServiceSelect,
	emptyTitle,
	emptyDescription,
	emptyActions,
	// See CardListStep for why "idle" is treated as loading.
	status = "ready",
	onRetry,
}) => {
	// The panel this component belongs to. Two panels on one page each have
	// their own store, so the store is read from context rather than imported.
	const bookingServiceStore = useBookingStore();
	const content = useSelect(
		(select) => select(bookingServiceStore).getContent(),
		[bookingServiceStore],
	);
	const currencySymbol = content.currencySymbol || "$";

	const list = sanitize(services, "array");

	if (status === "idle" || status === "loading") {
		return <ListSkeleton variant="services" />;
	}

	if (status === "error") {
		return (
			<div className="rbs1-pane">
				<EmptyState
					icon="noservice"
					title={__("Couldn't Load Services", "rox-appointment-booking")}
					description={__("Something went wrong while loading this category's services. Check your connection and try again.", "rox-appointment-booking")}
					actions={[
						{ label: __("Try Again", "rox-appointment-booking"), onClick: onRetry },
					]}
				/>
			</div>
		);
	}

	if (list.length === 0) {
		return (
			<div className="rbs1-pane">
				<EmptyState
					icon="noservice"
					title={emptyTitle}
					description={emptyDescription}
					actions={emptyActions}
				/>
			</div>
		);
	}

	return (
		<div className="rbs1-pane rbs1-list rbs1-list--single rbs1-list--services">
			{list.map((service) => {
				const isSelected = selectedService?.id === service.id;

				return (
					<div
						key={service.id}
						className={`rbs1-card ${isSelected ? "rbs1-card--selected" : ""}`}
						onClick={() => onServiceSelect(service)}
						role="button"
						tabIndex={0}
						onKeyDown={(event) => {
							if (event.key === "Enter" || event.key === " ") {
								event.preventDefault();
								onServiceSelect(service);
							}
						}}
					>
						<span className="rbs1-card__icon">
							{service.iconPath ? (
								<img src={service.iconPath} alt="" />
							) : (
								<Icon name="fallbackbody" size="26" />
							)}
						</span>
						<span className="rbs1-card__body">
							<p className="rbs1-card__title">{service.name}</p>
							<p className="rbs1-card__subtitle">
								{sprintf(
									/* translators: %s: service duration in minutes */
									__("%s min", "rox-appointment-booking"),
									service.duration,
								)}
							</p>
						</span>
						{!service.hide_price_booking_panel && (
							// Through the shared helper: a price arrives as a string on
							// some routes and a number on others, and `.toFixed()` on a
							// string throws inside render and takes the panel with it.
							<span className="rbs1-card__price">
								{formatPrice(service.price, currencySymbol)}
							</span>
						)}
					</div>
				);
			})}
		</div>
	);
};

export default ServicesStep;
