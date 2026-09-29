/**
 * A vertical list of icon + title + subtitle rows — Style 2's shape for both
 * the Location and the Category step. No Location design was handed over, so
 * it reuses the exact same row Category was given rather than inventing a
 * second pattern; only the fields it reads (icon/title/subtitle/badge)
 * differ per step.
 *
 * One card per row, four rows tall — see `.rbs1-pane` / `.rbs1-list`.
 */

import { __ } from "@wordpress/i18n";
import Icon from "../../../components/libs/Icon.jsx";
import { sanitize } from "../../../components/common/Sanitize.js";
import EmptyState from "./EmptyState.jsx";
import ListSkeleton from "./ListSkeleton.jsx";

const CardListStep = ({
	items,
	onSelect,
	selectedId,
	getIcon,
	getTitle,
	getSubtitle,
	getBadge,
	// An extra class on the list, for a step that needs its rows to read
	// differently from the shared card — Location, whose cards carry a name and
	// nothing else.
	className = "",
	emptyTitle,
	emptyDescription,
	emptyActions,
	// Which step's placeholder to draw while the list is on its way.
	skeletonVariant = "categories",
	// "idle" counts as loading: before the request has even been made there is
	// nothing to show but no reason to claim the list is empty — which is what
	// put an empty step in front of anyone reloading the page.
	status = "ready",
	onRetry,
	errorTitle,
	errorDescription,
}) => {
	const list = sanitize(items, "array");

	// Which illustration the empty card carries, off the variant the step
	// already names for its skeleton — so a step says which list it is once and
	// both placeholders follow, rather than each call site passing an icon.
	const emptyIcon =
		skeletonVariant === "locations"
			? "nolocation"
			: skeletonVariant === "categories"
				? "nocategories"
				: "noservice";

	if (status === "idle" || status === "loading") {
		return <ListSkeleton variant={skeletonVariant} />;
	}

	if (status === "error") {
		return (
			<div className="rbs1-pane">
				<EmptyState
					icon={emptyIcon}
					title={errorTitle}
					description={errorDescription}
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
					icon={emptyIcon}
					title={emptyTitle}
					description={emptyDescription}
					actions={emptyActions}
				/>
			</div>
		);
	}

	return (
		<div className={`rbs1-pane rbs1-list rbs1-list--single ${className}`.trim()}>
			{list.map((item) => {
				const isSelected = selectedId === item.id;
				const icon = getIcon?.(item);
				const badge = getBadge?.(item);

				return (
					<div
						key={item.id}
						className={`rbs1-card ${isSelected ? "rbs1-card--selected" : ""}`}
						onClick={() => onSelect(item.id)}
						role="button"
						tabIndex={0}
						onKeyDown={(event) => {
							if (event.key === "Enter" || event.key === " ") {
								event.preventDefault();
								onSelect(item.id);
							}
						}}
					>
						<span className="rbs1-card__icon">
							{icon ? (
								<img src={icon} alt="" />
							) : (
								<Icon name="fallbackbody" size="26" />
							)}
						</span>
						<span className="rbs1-card__body">
							<p className="rbs1-card__title">{getTitle(item)}</p>
							{getSubtitle && (
								<p className="rbs1-card__subtitle">{getSubtitle(item)}</p>
							)}
						</span>
						{badge !== undefined && badge !== null && badge !== "" && (
							<span className="rbs1-card__badge">{badge}</span>
						)}
					</div>
				);
			})}
		</div>
	);
};

export default CardListStep;
