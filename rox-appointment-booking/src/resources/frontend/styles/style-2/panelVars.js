/**
 * Style 2's panel-level controls, as the custom properties its stylesheet
 * reads.
 *
 * Shared by the mounted panel and the block's canvas preview so the two cannot
 * drift: a control wired into one and not the other is a control that appears
 * to work in the editor and does nothing on the published page. Mirrors what
 * `lib/navButtonVars.js` does for Style 1's Back / Next buttons.
 *
 * A control left unset is left out of the object entirely, so the stylesheet
 * keeps its own value rather than being handed an empty one.
 */

/**
 * @param {Object} attributes                Surface attributes / props.
 * @param {number} [attributes.serviceColumns] Service cards per row (1–2).
 * @param {string} [attributes.headingAlign]   Step heading alignment.
 * @param {string} [attributes.headingMargin]  Four comma-separated lengths,
 *                                             top/right/bottom/left.
 * @return {Object} Custom properties, ready to spread onto a style object.
 */
export const style2PanelVars = ({
	serviceColumns,
	headingAlign,
	headingMargin,
} = {}) => {
	const vars = {};

	// The shape every surface writes and Style 1 already reads: a side left
	// empty keeps the stylesheet's own margin for that side.
	String(headingMargin || "")
		.split(",")
		.forEach((length, index) => {
			const side = ["t", "r", "b", "l"][index];

			if (side && length) {
				vars[`--rbs1-head-m${side}`] = length;
			}
		});

	if (headingAlign) {
		vars["--rbs1-head-align"] = headingAlign;
	}

	// The Services step's grid reads this. Outside the 1–2 the control offers,
	// the stylesheet keeps its own one-per-row.
	const cols = Number(serviceColumns);

	if (Number.isFinite(cols) && cols >= 1 && cols <= 2) {
		vars["--rbs1-service-cols"] = String(Math.round(cols));
	}

	return vars;
};

export default style2PanelVars;
