/**
 * Style 2 — the new booking panel design.
 *
 * A placeholder for now, and deliberately a whole style module already: the
 * shell asks every variant for the same things, so filling this one in means
 * replacing the preview and, if the design needs panels of its own, adding a
 * Controls component beside it. Every control the block already offers is
 * shared, so this style has them all without asking.
 */

import { __ } from "@wordpress/i18n";

import Preview from "./Preview.jsx";
import { style2StepHeadings } from "../../../../frontend/styles/style-2/headings.js";

export default {
	name: "style-2",
	// The editor wrapper's class. Style-specific so a design can style its own
	// canvas without reaching into another's.
	className: () => "rox-booking-panel-block-editor rox-booking-style-2-editor",
	// Shared controls this design has nowhere to apply, so the sidebar leaves
	// them out rather than showing a control that does nothing. Style 2 is a
	// step rail and one content column: no right-hand info panel, and a first
	// step that draws cards rather than an illustrated sidebar.
	supports: {
		hideInfo: false,
		firstStepSidebar: false,
		// Style 1 puts the chosen category's name in front of this one, so the
		// field there rewrites only the word after it. Style 2's Services step
		// carries no category name, so the field rewrites the whole heading.
		servicesHeadingFollowsCategory: false,
	},
	// What this design's headings say before an editor rewrites them, so the
	// "Step headings" fields offer Style 2's wording rather than another
	// design's. Same source the panel and the preview render from.
	stepHeadings: style2StepHeadings,
	// What this design calls its footer buttons. Style 1 labels the forward
	// one "Next"; this one says "Continue", so the control panel names the
	// button the editor is actually looking at.
	navButtonLabels: () => ({
		back: __("Back button", "rox-appointment-booking"),
		next: __("Continue button", "rox-appointment-booking"),
	}),
	Controls: null,
	Preview,
};
