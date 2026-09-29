/**
 * Style 2's left rail: the panel brand plus the step list.
 *
 * Every step of the flow is listed, including any the panel resolved without
 * asking (a site with one location, a category with one service) — those come
 * through as already done rather than vanishing from the list. Purely
 * presentational: the caller works out which step is which.
 */

/**
 * The name at the top of the step rail.
 *
 * A fixed product name rather than the install's configurable `appTitle`, and
 * not translated — it is a brand, the same way the social chips' labels are.
 * Exported so the block editor's canvas preview heads its rail with the very
 * same string instead of keeping a second copy.
 */
export const PANEL_BRAND = "Rox Appointment Booking";

/**
 * The mark on a step already behind the visitor.
 *
 * The path is centred on the viewBox rather than merely drawn inside it: both
 * arms run at 45°, and the three points are chosen so the ink spans 2.25–9.75
 * across and 3.5–8.5 down — dead centre of 12 either way. The round cap adds
 * its half-stroke evenly on every end, so it does not move that centre.
 *
 * Worth keeping true. The dot centres whatever box this reports, so any bias
 * inside the box is a bias the dot cannot correct, and at 9px in a 16px disc
 * there is little enough room for it to show.
 */
const CheckIcon = () => (
	<svg viewBox="0 0 12 12" fill="none" aria-hidden="true">
		<path
			d="M2.25 6L4.75 8.5L9.75 3.5"
			stroke="currentColor"
			strokeWidth="2"
			strokeLinecap="round"
			strokeLinejoin="round"
		/>
	</svg>
);

const Sidebar = ({ brand, steps, currentStep }) => (
	<div className="rbs1-sidebar">
		<p className="rbs1-sidebar__brand">{brand}</p>

		<ol className="rbs1-steps">
			{steps.map((label, index) => {
				const stepNumber = index + 1;
				const status =
					stepNumber < currentStep
						? "done"
						: stepNumber === currentStep
						? "active"
						: "upcoming";

				return (
					<li className={`rbs1-steps__item rbs1-steps__item--${status}`} key={label}>
						<span className="rbs1-steps__marker">
							<span className="rbs1-steps__dot">
								{status === "done" && <CheckIcon />}
							</span>
							<span className="rbs1-steps__line" aria-hidden="true" />
						</span>
						<span className="rbs1-steps__label">{label}</span>
					</li>
				);
			})}
		</ol>
	</div>
);

export default Sidebar;
