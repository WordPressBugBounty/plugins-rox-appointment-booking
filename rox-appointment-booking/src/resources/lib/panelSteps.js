/**
 * The booking flow's step names, in one place.
 *
 * The live panel derives which steps it actually *runs* inside the booking
 * engine, where the real data (does this site use locations? is there only one
 * category?) is known. What a panel *shows* in its step list can be a longer
 * list than that — Style 2 lists every step and marks the auto-resolved ones as
 * already done rather than hiding them — and the block editor's canvas preview
 * has no engine at all. All three read the labels from here rather than keeping
 * a copy each and drifting apart.
 */

import { __ } from "@wordpress/i18n";
import { panelText } from "./panelContent.js";

/**
 * Step key => translated label.
 *
 * Built on call rather than at module scope so the labels resolve in the
 * locale that is actually loaded by the time something renders.
 *
 * @param {Object} [panelContent] Editor's copy overrides, if the surface has
 *                                any. Order Details has no override field, so
 *                                it always shows its own label.
 * @return {Object<string, string>} Labels keyed by step key.
 */
export const panelStepLabels = (panelContent) => ({
	Location: panelText(panelContent, "stepLocationLabel", __("Location", "rox-appointment-booking")),
	Category: panelText(panelContent, "stepCategoryLabel", __("Category", "rox-appointment-booking")),
	Services: panelText(panelContent, "stepServicesLabel", __("Services", "rox-appointment-booking")),
	Agents: panelText(panelContent, "stepAgentsLabel", __("Agents", "rox-appointment-booking")),
	DateTime: panelText(panelContent, "stepDateTimeLabel", __("Date & Time", "rox-appointment-booking")),
	Information: panelText(panelContent, "stepInformationLabel", __("Information", "rox-appointment-booking")),
	OrderDetails: __("Order Details", "rox-appointment-booking"),
	Payment: panelText(panelContent, "stepPaymentLabel", __("Payment", "rox-appointment-booking")),
	Complete: panelText(panelContent, "stepCompleteLabel", __("Complete", "rox-appointment-booking")),
});

/**
 * Every step a panel lists, in order.
 *
 * Deliberately the *unshortened* flow. The engine drops Location or Category
 * when there is only one to choose from (it picks it and moves on) and drops
 * Agents for a service that needs none — but a step list that loses entries as
 * you walk through it reads as broken, so the list shows them all and the
 * caller marks the resolved ones done.
 *
 * Location is the one genuine conditional: a site with the location module off
 * has no such step at any point, so listing it would promise a screen that
 * never comes.
 *
 * @param {boolean} hasLocations Whether this site has a Location step at all.
 * @return {string[]} Step keys.
 */
export const fullStepKeys = (hasLocations) => [
	...(hasLocations ? ["Location"] : []),
	"Category",
	"Services",
	"Agents",
	"DateTime",
	"Information",
	"OrderDetails",
	"Payment",
	"Complete",
];
