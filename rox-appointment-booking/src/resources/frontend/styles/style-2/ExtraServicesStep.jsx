/**
 * Style 2's extra-services picker — what "Add Extra Service" opens into.
 *
 * It stands in front of Order Details rather than beside it, exactly as Style 1
 * does: the engine's `showExtraServices` flag is what puts it there, the same
 * toggle handler records the choice, and Cancel/Continue both just lower the
 * flag. Nothing here knows anything the order step didn't already.
 *
 * The rows are the shared `.rbs1-card`, so an extra service reads as the same
 * kind of thing a service did two steps earlier.
 */

import { useSelect } from "@wordpress/data";
import { __ } from "@wordpress/i18n";
import Icon from "../../../components/libs/Icon.jsx";
import { sanitize } from "../../../components/common/Sanitize.js";
import { formatPrice } from "../../../lib/price.js";
import { useBookingStore } from "../../../redux/booking-store-context.js";
import EmptyState from "./EmptyState.jsx";
import ListSkeleton from "./ListSkeleton.jsx";

const ExtraServicesStep = ({
	extraServices,
	selectedExtraServices,
	onExtraServiceSelect,
	onBack,
	// See CardListStep for why "idle" is treated as loading.
	status = "ready",
	onRetry,
}) => {
	const bookingServiceStore = useBookingStore();
	const content = useSelect(
		(select) => select(bookingServiceStore).getContent(),
		[bookingServiceStore],
	);
	const currencySymbol = content.currencySymbol || "$";

	const list = sanitize(extraServices, "array");
	// Restored from sessionStorage, so its shape is only as trustworthy as
	// whatever is sitting in the browser — not necessarily an array.
	const selected = sanitize(selectedExtraServices, "array");

	const isSelected = (extra) => selected.some((item) => item.id === extra.id);

	// The whole selection every time, not a delta: the engine writes it straight
	// onto the booking, so it has to be the complete list after the click.
	const toggle = (extra) =>
		onExtraServiceSelect(
			isSelected(extra)
				? selected.filter((item) => item.id !== extra.id)
				: [...selected, extra],
		);

	if (status === "idle" || status === "loading") {
		return <ListSkeleton variant="services" />;
	}

	if (status === "error") {
		return (
			<div className="rbs1-pane">
				<EmptyState
					icon="noservice"
					title={__("Couldn't Load Extra Services", "rox-appointment-booking")}
					description={__("Something went wrong while loading the extra services. Check your connection and try again.", "rox-appointment-booking")}
					actions={[
						{ label: __("Try Again", "rox-appointment-booking"), onClick: onRetry },
						{ label: __("Go Back", "rox-appointment-booking"), onClick: onBack },
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
					title={__("No Extra Services Found", "rox-appointment-booking")}
					description={__("This service has no extra service listed at the moment.", "rox-appointment-booking")}
					actions={[
						{ label: __("Go Back", "rox-appointment-booking"), onClick: onBack },
					]}
				/>
			</div>
		);
	}

	return (
		<div className="rbs1-pane rbs1-list rbs1-list--single">
			{list.map((extra) => {
				const chosen = isSelected(extra);

				return (
					<div
						key={extra.id}
						className={`rbs1-card ${chosen ? "rbs1-card--selected" : ""}`}
						onClick={() => toggle(extra)}
						role="button"
						aria-pressed={chosen}
						tabIndex={0}
						onKeyDown={(event) => {
							if (event.key === "Enter" || event.key === " ") {
								event.preventDefault();
								toggle(extra);
							}
						}}
					>
						<span className="rbs1-card__icon">
							{extra.icon ? (
								<img src={extra.icon} alt="" />
							) : (
								<Icon name="fallbackbody" size="26" />
							)}
						</span>
						<span className="rbs1-card__body">
							<p className="rbs1-card__title">{extra.name}</p>
							{extra.duration ? (
								<p className="rbs1-card__subtitle">
									{`${extra.duration} ${__("min", "rox-appointment-booking")}`}
								</p>
							) : null}
						</span>
						<span className="rbs1-card__price">
							{formatPrice(extra.price, currencySymbol)}
						</span>
					</div>
				);
			})}
		</div>
	);
};

export default ExtraServicesStep;
