/**
 * The editor module each design variant provides.
 *
 * A style owns its inspector panels and its canvas preview; the block shell
 * owns the picker, the controls every style shares, and the state they both
 * need. Adding a design is a folder beside these two plus an entry here — the
 * shell reads this map and nothing else.
 */

import { resolvePanelStyle } from "../../../lib/panelStyle.js";

import style1 from "./style-1/index.js";
import style2 from "./style-2/index.js";

const STYLES = {
	"style-1": style1,
	"style-2": style2,
};

/**
 * The module for a variant name, falling back to the default for a block saved
 * with a name this build no longer knows.
 *
 * @param {string} styleVariant Saved variant name.
 * @return {Object} Style module.
 */
export const getStyle = (styleVariant) => STYLES[resolvePanelStyle(styleVariant)];

/**
 * Whether a style has somewhere to apply one of the shared controls.
 *
 * Absent means yes: a style lists only what its design cannot use, so a new
 * shared control reaches every variant until one says otherwise. A control
 * that is not supported is hidden rather than left on screen doing nothing.
 *
 * @param {Object} style Style module.
 * @param {string} key   Control name.
 * @return {boolean} Whether to offer the control.
 */
export const styleSupports = (style, key) => style?.supports?.[key] !== false;

export default STYLES;
