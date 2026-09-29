/**
 * The panel component each design variant renders.
 *
 * A surface writes its choice onto the mount node as `data-style-variant`
 * (see Supports\PanelStyle); this is where that name becomes a component. A new
 * design is a new folder beside these two plus an entry here — every surface
 * already carries the name through.
 */

import { resolvePanelStyle } from "../../lib/panelStyle.js";

import Style1Panel from "./style-1/index.jsx";
import Style2Panel from "./style-2/index.jsx";

const PANELS = {
	"style-1": Style1Panel,
	"style-2": Style2Panel,
};

/**
 * The panel component for a variant name, falling back to the default for
 * anything unrecognised — markup cached before the variant existed, say.
 *
 * @param {string} styleVariant Variant name from the mount node.
 * @return {Function} Panel component.
 */
export const panelForStyle = (styleVariant) =>
	PANELS[resolvePanelStyle(styleVariant)];

export default PANELS;
