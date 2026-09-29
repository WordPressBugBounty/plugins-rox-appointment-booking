/**
 * Style 2's own step headings — the title above each step's content.
 *
 * Shared by the mounted panel, the block's canvas preview and the block's
 * "Step headings" fields, so all three agree on what this design says before an
 * editor rewrites anything. Without one source the fields end up offering
 * another design's wording as the default, which is what they did before this
 * file existed.
 *
 * Keyed by the `panelContent` override each heading answers to (see
 * lib/panelContent.js), not by step name, because that is the key the fields
 * and `panelText` both work in.
 *
 * Built on call rather than at module scope so the strings are translated in
 * the locale that is actually loaded.
 */

import { __ } from "@wordpress/i18n";

/**
 * @return {Object} Default heading per panelContent key.
 */
export const style2StepHeadings = () => ({
	locationHeading: __("Select Location", "rox-appointment-booking"),
	categoryHeading: __("Available Category", "rox-appointment-booking"),
	servicesHeading: __("Available Services", "rox-appointment-booking"),
	agentsHeading: __("Available Agents", "rox-appointment-booking"),
	dateTimeHeading: __("Date & Time Selection", "rox-appointment-booking"),
	informationHeading: __("Customer Information", "rox-appointment-booking"),
});

export default style2StepHeadings;
