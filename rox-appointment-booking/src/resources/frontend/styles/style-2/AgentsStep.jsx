/**
 * Style 2's Agents step: two agent cards per row, four rows tall, and — when
 * an agent's name is clicked — a small details view in its place.
 *
 * The card picks the agent; the name inside it opens their details. The
 * details view's "Close Details ×" closes it again, and is the one
 * interaction Style 2 was handed an explicit hover/focus reference for: the
 * label underlines on hover and keyboard focus (see `.rbs1-link`).
 */

import { __ } from "@wordpress/i18n";
import Icon from "../../../components/libs/Icon.jsx";
import { sanitize } from "../../../components/common/Sanitize.js";
import {
	getSocialEntries,
	socialLabel,
} from "../../../components/BookingService/SocialIcons.jsx";
import EmptyState from "./EmptyState.jsx";
import ListSkeleton from "./ListSkeleton.jsx";

const CloseIcon = () => (
	<svg width="10" height="10" viewBox="0 0 10 11" fill="none" aria-hidden="true">
		<path d="M9 1.5L1 9.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
		<path d="M1 1.5L9 9.5" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" />
	</svg>
);

// One shimmering block, same helper ListSkeleton carries — every dimension is
// passed in, so each block matches the element it stands in for.
const Block = ({ className = "", ...size }) => (
	<span className={`rbs1-skeleton-block ${className}`.trim()} style={size} />
);

/**
 * The details view while the agent's record is still being fetched.
 *
 * Built out of the REAL `rbs1-agent-details__*` classes with blocks inside,
 * exactly as ListSkeleton is built out of the real card classes: the
 * placeholder then occupies the box the finished view will, so nothing shifts
 * when the response lands and the two cannot drift apart as the details view
 * changes.
 *
 * The bio is the one band left out. Both it and the work days are conditional
 * in the real view, but an agent's days come off their schedule and are there
 * to be read for all but the unscheduled — where a bio is free text that is as
 * often empty, and of no length worth guessing when it is not.
 *
 * @return {JSX.Element} The placeholder details view.
 */
const AgentDetailsSkeleton = () => (
	<div
		className="rbs1-pane rbs1-agent-details rbs1-agent-details--skeleton"
		role="status"
		aria-label={__("Loading…", "rox-appointment-booking")}
	>
		<div className="rbs1-agent-details__cover">
			{/* Takes the photo's own rule, so it fills the band edge to edge and
			    sits under the gradient rather than over it. */}
			<Block className="rbs1-agent-details__photo" />

			{/* The pill is already a white surface of its own — only its label is
			    unknown — so the button's box is kept and the words stand in. */}
			<span className="rbs1-agent-details__close">
				<Block width={74} height={13} />
			</span>

			<div className="rbs1-agent-details__caption">
				<span className="rbs1-agent-details__identity">
					<Block width={150} height={20} />
					<Block width={88} height={14} marginTop={6} />
				</span>
			</div>
		</div>

		{/* Three, always: the stats row is unconditional, and each is a figure
		    over its label. The figure clears its own top margin — the stat's
		    `span` rule gives every span in here 2px, which the real view's
		    `<strong>` never takes. */}
		<div className="rbs1-agent-details__stats">
			{[0, 1, 2].map((index) => (
				<span className="rbs1-agent-details__stat" key={index}>
					<Block width={32} height={20} marginTop={0} />
					<Block width="80%" height={14} marginTop={6} />
				</span>
			))}
		</div>

		{/* Chip-shaped, and of uneven widths as the day names are — a row of
		    identical blocks reads as a table rather than as the words it is
		    waiting on. */}
		<div className="rbs1-agent-details__days">
			<p className="rbs1-agent-details__days-title">
				<Block width={72} height={14} />
			</p>
			<div className="rbs1-agent-details__days-list">
				{[68, 62, 82, 74, 58].map((width, index) => (
					<Block key={index} width={width} height={20} borderRadius={4} />
				))}
			</div>
		</div>
	</div>
);

const AgentsStep = ({
	agents,
	selectedEmployee,
	onEmployeeSelect,
	onViewDetails,
	viewingEmployeeDetails,
	// The details are fetched on the click, so there is a beat between it and
	// the view opening. Ahead of `viewingEmployeeDetails` below because during
	// that beat the details are not in hand yet and this is the only thing that
	// knows the step is no longer showing its list.
	employeeDetailsLoading,
	onBackFromDetails,
	emptyTitle,
	emptyDescription,
	emptyActions,
	// See CardListStep for why "idle" is treated as loading.
	status = "ready",
	onRetry,
}) => {
	if (employeeDetailsLoading) {
		return <AgentDetailsSkeleton />;
	}

	if (viewingEmployeeDetails) {
		const employee = viewingEmployeeDetails;

		// Whatever the admin picked in the agent form's Social Profiles repeater,
		// in that order — empty ones dropped, so an agent with no links shows no
		// chips. Same source and same order as Style 1's details view.
		const socialEntries = getSocialEntries(employee?.socials);
		const workDays = sanitize(employee?.work_days, "array");

		// The counts read as "at least this many" rather than exact figures, and
		// a four-digit customer count is grouped so it stays scannable.
		const stats = [
			{
				value: `${Number(employee?.experience_years || 0).toLocaleString()}+`,
				label: __("Experience Years", "rox-appointment-booking"),
			},
			{
				value: `${Number(employee?.happy_customers || 0).toLocaleString()}+`,
				label: __("Happy Customers", "rox-appointment-booking"),
			},
			{
				value: Number(employee?.certifications || 0).toLocaleString(),
				label: __("Certifications", "rox-appointment-booking"),
			},
		];

		return (
			// The pane keeps the step's height whatever the agent's bio runs to;
			// anything past it scrolls rather than growing the panel.
			<div className="rbs1-pane rbs1-agent-details">
				<div className="rbs1-agent-details__cover">
					{employee?.agent?.thumbnail ? (
						<img
							className="rbs1-agent-details__photo"
							src={employee.agent.thumbnail}
							alt={employee?.full_name || ""}
						/>
					) : (
						<span className="rbs1-agent-details__photo-fallback">
							<Icon name="fallbackcover" size="50" />
						</span>
					)}

					<button
						type="button"
						className="rbs1-agent-details__close"
						onClick={onBackFromDetails}
					>
						{/* Wrapped so the underline sits under the words alone, rather
						    than running on under the gap and the cross beside them. */}
						<span className="rbs1-agent-details__close-label">
							{__("Close Details", "rox-appointment-booking")}
						</span>
						<CloseIcon />
					</button>

					{/* Name and the social chips share one row so they stay aligned
					    to each other rather than each to the cover. */}
					<div className="rbs1-agent-details__caption">
						<span className="rbs1-agent-details__identity">
							<span className="rbs1-agent-details__name">
								{employee?.full_name}
							</span>
							<span className="rbs1-agent-details__role">
								{employee?.title || ""}
							</span>
						</span>

						{socialEntries.length > 0 && (
							<span className="rbs1-agent-details__socials">
								{socialEntries.map(([platform, url]) => (
									<a
										key={platform}
										href={url}
										target="_blank"
										rel="noopener noreferrer"
										aria-label={socialLabel(platform)}
										title={socialLabel(platform)}
									>
										<Icon name={platform} size={13} />
									</a>
								))}
							</span>
						)}
					</div>
				</div>

				<div className="rbs1-agent-details__stats">
					{stats.map((stat) => (
						<span className="rbs1-agent-details__stat" key={stat.label}>
							<strong>{stat.value}</strong>
							<span>{stat.label}</span>
						</span>
					))}
				</div>

				{workDays.length > 0 && (
					<div className="rbs1-agent-details__days">
						<p className="rbs1-agent-details__days-title">
							{__("Work Days:", "rox-appointment-booking")}
						</p>
						<div className="rbs1-agent-details__days-list">
							{workDays.map((day) => (
								<span className="rbs1-workday" key={day}>
									<span className="rbs1-workday__dot">•</span>
									{day}
								</span>
							))}
						</div>
					</div>
				)}

				{employee?.bio && (
					<p className="rbs1-agent-details__bio">{employee.bio}</p>
				)}
			</div>
		);
	}

	const list = sanitize(agents, "array");

	if (status === "idle" || status === "loading") {
		return <ListSkeleton variant="agents" />;
	}

	if (status === "error") {
		return (
			<div className="rbs1-pane">
				<EmptyState
					icon="noagents"
					title={__("Couldn't Load Agents", "rox-appointment-booking")}
					description={__("Something went wrong while loading the agents for this service. Check your connection and try again.", "rox-appointment-booking")}
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
					icon="noagents"
					title={emptyTitle}
					description={emptyDescription}
					actions={emptyActions}
				/>
			</div>
		);
	}

	return (
		<div className="rbs1-pane rbs1-list rbs1-list--pair">
			{list.map((agent) => {
				const isSelected = selectedEmployee?.id === agent.id;

				return (
					<div
						key={agent.id}
						className={`rbs1-card ${isSelected ? "rbs1-card--selected" : ""}`}
						onClick={() => onEmployeeSelect(agent)}
						role="button"
						tabIndex={0}
						onKeyDown={(event) => {
							if (event.key === "Enter" || event.key === " ") {
								event.preventDefault();
								onEmployeeSelect(agent);
							}
						}}
					>
						<span className="rbs1-agent-card__avatar">
							{agent.thumbnail ? (
								<img src={agent.thumbnail} alt={agent.name || ""} />
							) : (
								<Icon name="fallbackbody" size="24" />
							)}
						</span>
						<span className="rbs1-agent-card__info">
							{/* The name is the way into the agent's details — there is no
							    separate link — so the click that opens them must not also
							    be read as picking the agent. */}
							<button
								type="button"
								className="rbs1-agent-card__name"
								title={agent.name}
								onClick={(event) => {
									event.stopPropagation();
									onViewDetails(agent);
								}}
							>
								{agent.name}
							</button>
							<span className="rbs1-agent-card__role">{agent.title}</span>
						</span>
					</div>
				);
			})}
		</div>
	);
};

export default AgentsStep;
