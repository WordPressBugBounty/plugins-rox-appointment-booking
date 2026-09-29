/**
 * Style 1 — the multi-step booking panel the block has always rendered.
 *
 * The module the block shell loads when this variant is picked. Every control
 * the block offers is shared by both styles, so this one contributes only its
 * canvas preview; `Controls` is where panels that belong to this design alone
 * would go.
 */

import Preview from "./Preview.jsx";

export default {
	name: "style-1",
	// The editor wrapper's class. Style-specific so a design can style its own
	// canvas without reaching into another's.
	className: () => "rox-booking-panel-block-editor",
	Controls: null,
	Preview,
};
