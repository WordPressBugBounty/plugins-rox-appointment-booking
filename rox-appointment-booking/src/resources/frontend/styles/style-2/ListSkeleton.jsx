/**
 * Placeholder cards for a Style 2 step whose list is still loading.
 *
 * Each variant renders the step's REAL grid and card classes with placeholder
 * blocks inside, rather than a shape of its own. That is the whole point: the
 * placeholder occupies exactly the box the finished cards will, so the step
 * does not jump when the response lands, and the two cannot drift apart as the
 * card styles change.
 *
 * The blocks are plain elements shimmered by `.rbs1-skeleton-block` in
 * panel.scss rather than antd's `Skeleton` — this file is reachable from the
 * booking-panel block's editor bundle through the Style 2 preview, and pulling
 * antd in there would multiply that bundle's size.
 */

import { __ } from "@wordpress/i18n";

// A single shimmering block. Every dimension comes from the variant below, so
// each block matches the real element it stands in for.
const Block = ({ className = "", ...size }) => (
	<span className={`rbs1-skeleton-block ${className}`.trim()} style={size} />
);

const VARIANTS = {
	// A location card is a disc and a name — no second line, since the address
	// it used to carry is gone. One line here for the same reason: the
	// placeholder has to be the box the finished card will be.
	locations: {
		listClass: "rbs1-list rbs1-list--single rbs1-list--locations",
		cards: 4,
		body: (
			<>
				<span className="rbs1-card__icon">
					<Block width="100%" height="100%" borderRadius="50%" />
				</span>
				<span className="rbs1-card__body">
					<Block width={140} height={18} />
				</span>
			</>
		),
	},
	categories: {
		listClass: "rbs1-list rbs1-list--single",
		cards: 4,
		body: (
			<>
				<span className="rbs1-card__icon">
					<Block width="100%" height="100%" borderRadius="50%" />
				</span>
				<span className="rbs1-card__body">
					<Block width={140} height={15} />
					<Block width="60%" height={12} marginTop={8} />
				</span>
				<span className="rbs1-card__badge">
					<Block width={18} height={12} />
				</span>
			</>
		),
	},
	services: {
		listClass: "rbs1-list rbs1-list--single",
		cards: 4,
		body: (
			<>
				<span className="rbs1-card__icon">
					<Block width="100%" height="100%" borderRadius="50%" />
				</span>
				<span className="rbs1-card__body">
					<Block width={130} height={15} />
					<Block width={54} height={12} marginTop={8} />
				</span>
				<Block width={58} height={15} />
			</>
		),
	},
	// Two per row, so four rows stand in for eight agents.
	agents: {
		listClass: "rbs1-list rbs1-list--pair",
		cards: 8,
		body: (
			<>
				<span className="rbs1-agent-card__avatar">
					<Block width="100%" height="100%" borderRadius="50%" />
				</span>
				<span className="rbs1-agent-card__info">
					<Block width={96} height={15} />
					<Block width={64} height={12} marginTop={6} />
				</span>
			</>
		),
	},
};

const ListSkeleton = ({ variant = "services", count }) => {
	const config = VARIANTS[variant] || VARIANTS.services;
	const total = count || config.cards;

	return (
		<div
			className={`rbs1-pane ${config.listClass}`}
			role="status"
			aria-label={__("Loading…", "rox-appointment-booking")}
		>
			{Array.from({ length: total }, (_, index) => (
				<div className="rbs1-card rbs1-card--skeleton" key={index}>
					{config.body}
				</div>
			))}
		</div>
	);
};

export default ListSkeleton;
