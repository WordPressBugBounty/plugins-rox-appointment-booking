/**
 * Modal shell for the booking panel's popup mode.
 *
 * Deliberately hand-rolled rather than antd's `Modal`: this component ships in
 * the popup's own view bundle, which every page carrying a trigger downloads.
 * Pulling antd in here would drag the whole component library into that bundle
 * for the sake of an overlay and a close button.
 *
 * The booking panel is NOT a React child of this component. It is mounted by
 * the shared frontend bundle onto a plain DOM node this component appends to
 * `bodyRef`, which React then never touches (the ref'd element has no React
 * children of its own). That separation is what lets the modal close and
 * reopen without tearing the visitor's half-finished booking down.
 */

import { useCallback, useEffect, useRef, useState } from "@wordpress/element";

/**
 * Returns a panel to a clean slate — clears its persisted sessionStorage entry
 * and resets its store to the defaults.
 *
 * The panel bundle does this on our behalf: each panel instance has its own
 * store, and only that bundle knows which name belongs to which instance.
 * Importing the store module here would register a second copy of every store.
 *
 * @param {HTMLElement} root Panel mount node.
 * @return {void}
 */
const resetPanelState = (root) => {
	window.roxAppointmentBooking?.resetRoot?.(root);
};

/**
 * Tears a mounted panel down and removes its node.
 *
 * @param {HTMLElement} root Panel mount node.
 * @return {void}
 */
const destroyPanel = (root) => {
	if (!root) {
		return;
	}

	// Out of the page straight away so the swap is not visible…
	root.remove();

	// …but the React root behind it is unmounted a tick later. This runs from
	// inside an effect, and React refuses to synchronously unmount one root
	// while another is still committing ("Attempted to synchronously unmount a
	// root while React was already rendering"). The detached tree cannot affect
	// anything in the meantime.
	setTimeout(() => {
		window.roxAppointmentBooking?.unmountRoot?.(root);
	}, 0);
};

/**
 * The frontend bundle is a deferred webpack chunk, so `mountRoot` may not be
 * exposed yet on the first click. Retry briefly rather than dropping the click.
 */
const MOUNT_RETRY_MS = 100;
const MOUNT_MAX_ATTEMPTS = 50; // ~5s, then give up rather than spin forever.

const FOCUSABLE = [
	"a[href]",
	"button:not([disabled])",
	"input:not([disabled]):not([type='hidden'])",
	"select:not([disabled])",
	"textarea:not([disabled])",
	"[tabindex]:not([tabindex='-1'])",
].join(",");

/**
 * Mounts the booking panel on a root node, waiting for the frontend bundle to
 * expose its mount helper.
 *
 * @param {HTMLElement} root        Panel mount node.
 * @param {boolean}     resetFirst  Drop any progress persisted by an earlier
 *                                  page load before mounting. Both steps have
 *                                  to wait for the same bundle, which is why
 *                                  this rides along instead of running outside.
 * @param {number}      attempt     Current attempt (internal).
 * @return {void}
 */
const mountPanel = (root, resetFirst, attempt = 0) => {
	const api = window.roxAppointmentBooking;

	if (api && typeof api.mountRoot === "function") {
		if (resetFirst) {
			api.resetRoot?.(root);
		}

		api.mountRoot(root);
		return;
	}

	if (attempt >= MOUNT_MAX_ATTEMPTS) {
		// eslint-disable-next-line no-console
		console.warn(
			"[rox-appointment-booking] Booking panel bundle did not load; the button cannot open a panel.",
		);
		return;
	}

	setTimeout(() => mountPanel(root, resetFirst, attempt + 1), MOUNT_RETRY_MS);
};

/**
 * Buttons that have already opened during this page view.
 *
 * The panel persists its progress in sessionStorage, which survives a reload —
 * right for a panel sitting on the page, wrong for one behind a button, where
 * it means pressing "Book Appointment" on a freshly loaded page drops the
 * visitor into the middle of an old booking. So the first open after a page
 * load starts clean, while a close and reopen within the same view still
 * resumes, which is what makes an accidental close harmless.
 */
const openedThisPageView = new Set();

/**
 * Counts how many booking modals are open, so the scroll lock is only released
 * once the last of them closes.
 */
let openModalCount = 0;

const lockScroll = () => {
	openModalCount += 1;
	if (openModalCount === 1) {
		// Set here rather than in CSS: the build's CSSReplacementPlugin strips a
		// bare `body {}` rule out of the compiled stylesheet.
		document.body.style.overflow = "hidden";
		// The panel's antd dropdowns and date pickers render into <body> at
		// antd's own z-index (~1050), which sits far below this modal. The class
		// is what the stylesheet hooks the raised z-index onto.
		document.body.classList.add("rox-booking-modal-open");
	}
};

const unlockScroll = () => {
	openModalCount = Math.max(0, openModalCount - 1);
	if (openModalCount === 0) {
		document.body.style.overflow = "";
		document.body.classList.remove("rox-booking-modal-open");
	}
};

const BookingModal = ({
	settings,
	trigger,
	initialOpen = false,
	onRegister,
	onOpenChange,
}) => {
	const [open, setOpen] = useState(initialOpen);
	const bodyRef = useRef(null);
	const dialogRef = useRef(null);

	const close = useCallback(() => setOpen(false), []);
	const openModal = useCallback(() => setOpen(true), []);

	// The page's single modal is re-rendered with fresh props whenever another
	// button is pressed, and those props are inline callbacks — a new identity
	// every time. Holding them in refs keeps them out of the effect deps below,
	// so a re-render cannot tear down and re-run the scroll lock and focus
	// handling underneath a modal that is simply changing buttons.
	const onOpenChangeRef = useRef(onOpenChange);
	const triggerRef = useRef(trigger);

	onOpenChangeRef.current = onOpenChange;
	triggerRef.current = trigger;

	// Hand the open/close pair back to the click handler that rendered us.
	useEffect(() => {
		if (typeof onRegister === "function") {
			onRegister({ open: openModal, close });
		}
	});

	// Build the panel mount node the first time the modal is opened, then leave
	// it alone — reopening must land the visitor back on the step they left.
	//
	// One page shares one modal across every booking button, so `settings` can
	// change under us when a different button is pressed. The panel that is
	// already mounted then belongs to the wrong button: it must be torn down and
	// the store reset, or its restriction (and its half-finished selections)
	// would carry over into the new button's flow.
	useEffect(() => {
		if (!open) {
			return;
		}

		const host = bodyRef.current;
		if (!host) {
			return;
		}

		const mounted = host.querySelector(".rox-appointment-booking-frontend-root");

		if (mounted) {
			if (mounted.dataset.instance === String(settings.instance)) {
				return;
			}

			// No reset here: every button now has its own panel store, so the one
			// we are leaving keeps its own progress for when its button is
			// pressed again, and the one we are building starts on its own state.
			destroyPanel(mounted);
		}

		const root = document.createElement("div");
		root.className = "rox-appointment-booking-frontend-root";

		// The panel accent and the Back / Next variables, which the panel
		// stylesheet reads off the mount node. Set before the node is in the
		// document so the panel never paints in the default colours first.
		if (settings.panelStyle) {
			root.style.cssText = settings.panelStyle;
		}

		// Same variables, the other way round: a surface whose own stylesheet
		// sets them (Elementor) hands over the id its rule is keyed to. Matches
		// BookingButtonMarkup::PANEL_OWNER_SELECTOR.
		if (settings.panelOwner) {
			root.dataset.roxOwner = settings.panelOwner;
		}

		// The same data-* contract Supports\BookingButtonMarkup writes onto the
		// trigger and BookingPanelBlock writes onto its inline root.
		root.dataset.instance = settings.instance || "1";
		root.dataset.type = "booking-form";
		root.dataset.hideNavigation = settings.hideNavigation ? "true" : "false";
		root.dataset.serviceColumns = settings.serviceColumns || "";
		root.dataset.showDashboardButton =
			settings.showDashboardButton === false ? "false" : "true";
		root.dataset.dashboardButtonText = settings.dashboardButtonText || "";
		root.dataset.dashboardButtonUrl = settings.dashboardButtonUrl || "";
		root.dataset.contentMargin = settings.contentMargin || "";
		root.dataset.headingAlign = settings.headingAlign || "";
		root.dataset.headingMargin = settings.headingMargin || "";
		root.dataset.hideInfo = settings.hideInfo ? "true" : "false";
		// The modal already supplies a card (white surface, padding, shadow), so
		// the panel's own grey frame is off unless the surface asked for it.
		root.dataset.showBackground = settings.showBackground ? "true" : "false";
		root.dataset.backgroundColor = settings.backgroundColor || "";
		root.dataset.fontFamily = settings.fontFamily || "";
		root.dataset.locations = settings.locations || "";
		root.dataset.categories = settings.categories || "";
		root.dataset.panelContent = settings.panelContent || "";
		// Locks the panel to one agent; "0" leaves the normal multi-step flow.
		root.dataset.agentId = settings.agentId || "0";

		host.appendChild(root);

		// Only the first open of this button since the page loaded starts clean;
		// later opens in the same view resume where the visitor left off.
		const instanceKey = String(settings.instance || "1");
		const firstOpenThisPageView = !openedThisPageView.has(instanceKey);
		openedThisPageView.add(instanceKey);

		mountPanel(root, firstOpenThisPageView);
	}, [open, settings]);

	// Scroll lock, ESC to close, and focus handling for as long as we are open.
	useEffect(() => {
		onOpenChangeRef.current?.(open);

		if (!open) {
			return undefined;
		}

		lockScroll();

		const previouslyFocused = document.activeElement;

		// Focus the dialog itself rather than the first field: the panel mounts
		// asynchronously, so there may not be a field yet.
		dialogRef.current?.focus();

		const onKeyDown = (event) => {
			if (event.key === "Escape") {
				event.stopPropagation();
				close();
				return;
			}

			if (event.key !== "Tab") {
				return;
			}

			// Focus trap: keep Tab inside the dialog instead of letting it walk
			// out into the page behind the overlay.
			const dialog = dialogRef.current;
			if (!dialog) {
				return;
			}

			const items = Array.from(dialog.querySelectorAll(FOCUSABLE)).filter(
				(item) => item.offsetParent !== null,
			);

			if (items.length === 0) {
				event.preventDefault();
				dialog.focus();
				return;
			}

			const first = items[0];
			const last = items[items.length - 1];

			if (event.shiftKey && document.activeElement === first) {
				event.preventDefault();
				last.focus();
			} else if (!event.shiftKey && document.activeElement === last) {
				event.preventDefault();
				first.focus();
			}
		};

		document.addEventListener("keydown", onKeyDown, true);

		return () => {
			document.removeEventListener("keydown", onKeyDown, true);
			unlockScroll();

			// Send focus back where it came from, so a keyboard visitor is not
			// dropped at the top of the document.
			const owner = triggerRef.current;

			if (owner && typeof owner.focus === "function") {
				owner.focus();
			} else if (previouslyFocused && typeof previouslyFocused.focus === "function") {
				previouslyFocused.focus();
			}
		};
	}, [open, close]);

	// `resetOnClose`: throw the mounted panel away and reset its store, so the
	// next open starts a fresh booking rather than resuming. Clearing only the
	// sessionStorage entry would not be enough — the store keeps the selections
	// in memory and would hand them straight back.
	useEffect(() => {
		if (open || !settings.resetOnClose) {
			return;
		}

		const host = bodyRef.current;
		const root = host?.querySelector(".rox-appointment-booking-frontend-root");

		// Reset before the node goes: the panel bundle reads the instance id off
		// it to find the right store.
		resetPanelState(root);
		destroyPanel(root);
	}, [open, settings.resetOnClose]);

	return (
		<div
			className={`rox-booking-modal${open ? " is-open" : ""}`}
			// `hidden` rather than unmounting: the panel below has to survive a
			// close so the visitor's progress does.
			hidden={!open}
		>
			<div
				className="rox-booking-modal__overlay"
				onClick={close}
				// The overlay duplicates the close button, which is reachable.
				aria-hidden="true"
			/>
			<div
				className="rox-booking-modal__dialog"
				role="dialog"
				aria-modal="true"
				aria-label={settings.title}
				tabIndex={-1}
				ref={dialogRef}
				style={{ maxWidth: `${settings.modalWidth || 1100}px` }}
			>
				<button
					type="button"
					className="rox-booking-modal__close"
					onClick={close}
					aria-label={settings.closeLabel}
				>
					<svg
						width="20"
						height="20"
						viewBox="0 0 20 20"
						fill="none"
						stroke="currentColor"
						strokeWidth="1.75"
						strokeLinecap="round"
						aria-hidden="true"
					>
						<path d="M5 5l10 10M15 5L5 15" />
					</svg>
				</button>
				<div className="rox-booking-modal__body" ref={bodyRef} />
			</div>
		</div>
	);
};

export default BookingModal;
