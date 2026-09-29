import { __, sprintf } from "@wordpress/i18n";

/**
 * Style 2's step indicator for small screens.
 *
 * The rail is a 220px column of labelled steps, and the layout drops it below
 * 991px — a flow that runs to nine steps has no way to show nine labels on a
 * phone, and a horizontal scroller would hide the very steps it exists to
 * advertise while putting a sideways touch drag beside the vertical one the
 * page already owns.
 *
 * So what survives the width is one segment per step: how far along you are and
 * how much is left. The panel's own header carries "Step 5 of 9" beside the
 * step's name a few pixels below, so neither is repeated here.
 *
 * Rendered at every width and hidden by the stylesheet above the breakpoint,
 * rather than gated on a media query in JS that could drift out of step with
 * the one in the stylesheet.
 *
 * Not interactive, matching the rail it stands in for: that list is display
 * only, and Back is the way back.
 *
 * @param {object}   props             Component props.
 * @param {string[]} props.steps       Step labels, in order.
 * @param {number}   props.currentStep The active step, 1-based.
 * @return {JSX.Element|null} The indicator, or null when there is none to show.
 */
const StepProgress = ({ steps, currentStep }) => {
	const total = Array.isArray(steps) ? steps.length : 0;

	// A single-step flow has no progress to report.
	if (total < 2) {
		return null;
	}

	return (
		<div
			className="rbs1-progress"
			role="progressbar"
			aria-valuemin={1}
			aria-valuemax={total}
			aria-valuenow={currentStep}
			// The bar alone would read as a bare number with no idea what that
			// step is; the name is added here so assistive tech gets what the
			// header gives everyone else.
			aria-valuetext={sprintf(
				/* translators: 1: current step number, 2: total steps, 3: step name */
				__("Step %1$d of %2$d: %3$s", "rox-appointment-booking"),
				currentStep,
				total,
				steps[currentStep - 1] || "",
			)}
		>
			{steps.map((label, index) => (
				<span
					key={label}
					// aria-hidden on each: the track is a picture of the value the
					// wrapper already reports, so exposing every segment would only
					// add noise.
					aria-hidden="true"
					className={`rbs1-progress__seg ${
						index < currentStep ? "rbs1-progress__seg--done" : ""
					}`}
				/>
			))}
		</div>
	);
};

export default StepProgress;
