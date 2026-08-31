// Editor-side mirror of Supports\NavButtons::cssVars(), so the preview's Back /
// Next row is painted by exactly the variables the published page will set.
// PHP stays the authority: it re-derives all of this from the saved attributes.
//
// The row's own height is absent on purpose — the panel stylesheet derives it
// from these same variables.

const SIDES = ["top", "right", "bottom", "left"];

const BUTTONS = [
	["back", "navBack"],
	["next", "navNext"],
];

const COLORS = [
	["bg", "BgColor"],
	["fg", "TextColor"],
	["border", "BorderColor"],
	["bg-hover", "BgColorHover"],
	["fg-hover", "TextColorHover"],
	["border-hover", "BorderColorHover"],
];

const BORDER_STYLES = ["none", "solid", "dashed", "dotted", "double"];
const FONT_WEIGHTS = ["300", "400", "500", "600", "700", "800"];

const isSet = (value) =>
	value !== undefined && value !== null && String(value).trim() !== "";

// A unitless number is read as pixels: the spacing control hands one back
// before a unit has been picked.
const length = (value) => {
	if (!isSet(value)) {
		return "";
	}

	const raw = String(value).trim();

	if (/^-?\d+(\.\d+)?$/.test(raw)) {
		return parseFloat(raw) === 0 ? "0" : `${raw}px`;
	}

	return /^-?\d+(\.\d+)?(px|em|rem|%)$/.test(raw) ? raw : "";
};

const metric = (value, max) =>
	isSet(value) ? `${Math.max(0, Math.min(max, parseInt(value, 10) || 0))}px` : "";

/**
 * Builds the Back / Next custom properties as a React style object.
 *
 * @param {Object} attributes Block attributes.
 * @return {Object} Style object; empty when no control has been touched.
 */
export const navButtonVars = (attributes = {}) => {
	const style = {};

	BUTTONS.forEach(([key, prefix]) => {
		const base = `--rox-nav-${key}-`;

		COLORS.forEach(([suffix, attribute]) => {
			const color = attributes[`${prefix}${attribute}`];

			if (isSet(color)) {
				style[base + suffix] = color;
			}
		});

		// Written per side, so a side left empty keeps the stylesheet's padding.
		[
			["p", `${prefix}Padding`],
			["m", `${prefix}Margin`],
		].forEach(([letter, attribute]) => {
			const values = attributes[attribute] || {};

			SIDES.forEach((side) => {
				const value = length(values[side]);

				if (value !== "") {
					style[base + letter + side[0]] = value;
				}
			});
		});

		const borderStyle = attributes[`${prefix}BorderStyle`];
		if (BORDER_STYLES.includes(borderStyle)) {
			style[`${base}bs`] = borderStyle;
		}

		const borderWidth = metric(attributes[`${prefix}BorderWidth`], 20);
		if (borderWidth !== "") {
			style[`${base}bw`] = borderWidth;
		}

		const radius = metric(attributes[`${prefix}BorderRadius`], 100);
		if (radius !== "") {
			style[`${base}br`] = radius;
		}

		const fontSize = metric(attributes[`${prefix}FontSize`], 60);
		if (fontSize !== "") {
			style[`${base}fs`] = fontSize;
		}

		const fontWeight = attributes[`${prefix}FontWeight`];
		if (FONT_WEIGHTS.includes(fontWeight)) {
			style[`${base}fw`] = fontWeight;
		}
	});

	return style;
};

export default navButtonVars;
