/**
 * The booking panel's design variants, on the JavaScript side.
 *
 * The mirror of Supports\PanelStyle: PHP writes the chosen variant onto the
 * mount node as `data-style-variant`, and the frontend bundle picks the panel
 * component that name maps to. Both lists have to agree, so keep this file and
 * that class in step.
 */

import { __ } from "@wordpress/i18n";

/**
 * Variant names, in the order a picker offers them.
 *
 * @type {string[]}
 */
export const PANEL_STYLES = ["style-1", "style-2"];

/**
 * What a surface renders when nobody has picked. Matches
 * PanelStyle::DEFAULT_STYLE.
 *
 * @type {string}
 */
export const DEFAULT_PANEL_STYLE = "style-1";

/**
 * Normalises a stored value to a known variant.
 *
 * @param {*} value Raw attribute / data-attribute value.
 * @return {string} A name from PANEL_STYLES.
 */
export const resolvePanelStyle = (value) =>
	PANEL_STYLES.includes(value) ? value : DEFAULT_PANEL_STYLE;

/**
 * Whether a variant draws the grey frame when nobody has chosen.
 *
 * The frame is one control shared by every design, but its default is not:
 * Style 1 has always framed the panel, and Style 2's design sits flat on the
 * page. Mirrors PanelStyle::BACKGROUND_DEFAULTS.
 *
 * @type {Object<string, boolean>}
 */
export const PANEL_STYLE_BACKGROUND = {
	"style-1": true,
	"style-2": false,
};

/**
 * Whether the frame is drawn, for a setting that may not have been chosen.
 *
 * The attribute is tri-state: `null` (or absent) is "nobody chose", which is
 * what resolves to the variant's own default; an explicit true / false is the
 * editor's answer and is honoured whichever design is showing.
 *
 * @param {*}      value        Raw `showBackground` attribute.
 * @param {string} styleVariant Variant name.
 * @return {boolean} Whether to draw the frame.
 */
export const resolveShowBackground = (value, styleVariant) =>
	value === null || value === undefined
		? PANEL_STYLE_BACKGROUND[resolvePanelStyle(styleVariant)]
		: Boolean(value);

/**
 * The variants as picker options. Built on call rather than at module scope so
 * the labels are translated in the locale that is actually loaded.
 *
 * @return {Array<{label: string, value: string}>} Options.
 */
export const panelStyleOptions = () => [
	{ label: __("Style 1", "rox-appointment-booking"), value: "style-1" },
	{ label: __("Style 2", "rox-appointment-booking"), value: "style-2" },
];
