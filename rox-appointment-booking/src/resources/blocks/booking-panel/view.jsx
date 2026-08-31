/**
 * Frontend behaviour for the booking panel's popup mode.
 *
 * Drives every surface that renders a trigger through
 * Supports\BookingButtonMarkup — the block in popup mode and the Elementor
 * button widget — because they all carry the identical `data-*` contract this
 * file reads.
 *
 * Nothing is built until a visitor actually presses a button: the modal, its
 * React root and the booking panel inside it are all created on the first
 * click. A page of buttons costs one small listener each.
 */

import { createRoot } from "react-dom/client";
import { StrictMode } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import BookingModal from "./app/BookingModal.jsx";
import "./app/booking-button.scss";

/**
 * Marks a trigger as wired, so a second pass (Elementor re-running its handler
 * on the same widget) cannot attach a duplicate listener.
 */
const BOUND_FLAG = "roxBookingBound";

/**
 * True inside the Elementor editor's preview iframe.
 *
 * There, a click is how an editor selects the widget; opening a full-screen
 * booking modal on every selection would make the widget unusable. The Gutenberg
 * side stays inert for the same reason — its editor preview renders the button
 * disabled.
 *
 * @return {boolean} Whether we are in the Elementor editor preview.
 */
const isElementorEditPreview = () => {
	try {
		return window.location.search.indexOf("elementor-preview=") > -1;
	} catch (e) {
		return false;
	}
};

/**
 * Reads the panel settings the surface wrote onto the trigger.
 *
 * @param {HTMLElement} trigger Button element.
 * @return {Object} Settings for BookingModal.
 */
const readSettings = (trigger) => ({
	instance: trigger.dataset.instance || "1",
	modalWidth: parseInt(trigger.dataset.modalWidth, 10) || 1100,
	resetOnClose: trigger.dataset.resetOnClose === "true",
	hideNavigation: trigger.dataset.hideNavigation === "true",
	hideInfo: trigger.dataset.hideInfo === "true",
	locations: trigger.dataset.locations || "",
	categories: trigger.dataset.categories || "",
	agentId: trigger.dataset.agentId || "0",
	// The panel's own grey frame. Absent means off: the modal already supplies
	// a card, so only a surface that offers the toggle switches it on.
	showBackground: trigger.dataset.showBackground === "true",
	backgroundColor: trigger.dataset.backgroundColor || "",
	// A ready-to-use CSS font stack, already resolved server-side.
	fontFamily: trigger.dataset.fontFamily || "",
	// The panel's own custom properties (accent + Back / Next styling). They
	// belong on the mount node, which lives in the modal rather than inside
	// this trigger's wrapper, so they travel here as a declaration string.
	panelStyle: trigger.dataset.panelStyle || "",
	// Elementor styles that node through a stylesheet rule instead — it keeps
	// Global Colours and per-breakpoint values, which cannot survive being read
	// back in PHP — and needs only this id to aim the rule at the right panel.
	panelOwner: trigger.dataset.panelOwner || "",
	// The button's own label names the dialog for assistive tech, so a page with
	// several buttons gives each modal a meaningful name.
	title:
		trigger.textContent?.trim() ||
		__("Book Appointment", "rox-appointment-booking"),
	closeLabel: __("Close booking", "rox-appointment-booking"),
});

/**
 * The page's one and only booking modal.
 *
 * Deliberately shared by every button rather than one modal each. The booking
 * panel keeps its state in a store registered once per page, so two live panels
 * cannot hold different category / location restrictions at the same time —
 * whichever fetched last would win for both. One modal means one panel, and
 * BookingModal rebuilds that panel whenever a different button hands it new
 * settings.
 */
let modal = null;

/**
 * Opens the shared modal for a trigger, building it on the first click.
 *
 * Clicking the same button again resumes where the visitor left off; clicking a
 * different one starts that button's flow fresh.
 *
 * @param {HTMLElement} trigger Button element.
 * @return {void}
 */
const openFor = (trigger) => {
	if (modal) {
		modal.render(trigger);
		return;
	}

	const container = document.createElement("div");
	container.className = "rox-booking-modal-container";
	document.body.appendChild(container);

	const root = createRoot(container);
	// Assigned before the first render so a second click arriving mid-render
	// re-renders the one modal rather than building a second.
	modal = { api: null, trigger: null, render: () => {} };

	modal.render = (nextTrigger) => {
		// Same button, already built: just reopen it and keep the progress.
		if (modal.trigger === nextTrigger && modal.api) {
			modal.api.open?.();
			return;
		}

		// Moving to a different button leaves the old one showing collapsed.
		if (modal.trigger && modal.trigger !== nextTrigger) {
			modal.trigger.setAttribute("aria-expanded", "false");
		}

		// A modal that already exists keeps its own open state across a props
		// change — `initialOpen` only seeds it on the very first render — so it
		// has to be told to open again after the swap.
		const existing = modal.api;

		modal.trigger = nextTrigger;

		root.render(
			<StrictMode>
				<BookingModal
					settings={readSettings(nextTrigger)}
					trigger={nextTrigger}
					// Rendered already open: this call *is* the click, and the
					// modal's api only becomes available once React has committed.
					initialOpen
					onRegister={(api) => {
						modal.api = api;
					}}
					onOpenChange={(isOpen) => {
						nextTrigger.setAttribute(
							"aria-expanded",
							isOpen ? "true" : "false",
						);
					}}
				/>
			</StrictMode>,
		);

		existing?.open?.();
	};

	modal.render(trigger);
};

/**
 * Wires a single trigger. Exposed via `window.roxAppointmentBookingButton` so
 * buttons injected after this bundle has run — the Elementor editor's drag-in
 * and control changes — can be wired too.
 *
 * @param {HTMLElement} element Button element, or a container holding one.
 * @return {void}
 */
export const mountButton = (element) => {
	if (!element || isElementorEditPreview()) {
		return;
	}

	// Elementor hands its handlers the widget wrapper, not the button.
	const trigger = element.matches?.("[data-rox-booking-trigger]")
		? element
		: element.querySelector?.("[data-rox-booking-trigger]");

	if (!trigger || trigger.dataset[BOUND_FLAG] === "1") {
		return;
	}

	trigger.dataset[BOUND_FLAG] = "1";
	trigger.addEventListener("click", (event) => {
		event.preventDefault();
		openFor(trigger);
	});
};

/**
 * Wires every trigger currently in the document.
 *
 * @return {void}
 */
export const mountAll = () => {
	document
		.querySelectorAll("[data-rox-booking-trigger]")
		.forEach((trigger) => mountButton(trigger));
};

mountAll();

window.roxAppointmentBookingButton = window.roxAppointmentBookingButton || {};
window.roxAppointmentBookingButton.mountButton = mountButton;
window.roxAppointmentBookingButton.mountAll = mountAll;
