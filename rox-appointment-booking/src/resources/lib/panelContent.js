/**
 * Panel copy overrides shared by the booking panel surfaces.
 *
 * The booking panel's wording is fixed in its components — the first step's
 * sidebar, each step's heading, the left step list. The surfaces that embed the
 * panel let an editor rewrite any of it, and that choice reaches the frontend
 * as one JSON `data-panel-content` attribute, so the editor preview and the
 * mounted panel both read it through here.
 *
 * Mirrors the PHP `RoxAppointmentBooking\Supports\PanelContent`.
 */

/**
 * Every override an editor can set, in the order the panel shows them.
 *
 * An explicit list rather than "whatever the object holds": the value is
 * whatever was saved in the post, so only keys named here are ever read.
 */
export const PANEL_CONTENT_KEYS = [
	// Step 1's sidebar: the illustration, its title and subtitle, and the help
	// box under them.
	"sidebarImage",
	"sidebarImageWidth",
	"sidebarTitle",
	"sidebarSubtitle",
	"helpTitle",
	"helpButtonText",
	"helpButtonUrl",
	"helpNote",
	// The heading above each step's cards.
	"locationHeading",
	"categoryHeading",
	"servicesHeading",
	"agentsHeading",
	"dateTimeHeading",
	"informationHeading",
	// The left step list, from the second step onward.
	"stepLocationLabel",
	"stepCategoryLabel",
	"stepServicesLabel",
	"stepAgentsLabel",
	"stepDateTimeLabel",
	"stepInformationLabel",
	"stepPaymentLabel",
	"stepCompleteLabel",
];

/**
 * The keys holding a number rather than a label. Kept apart because they
 * survive different values: a blank string is "unset", but so is 0.
 */
export const PANEL_CONTENT_NUMBER_KEYS = ["sidebarImageWidth"];

/**
 * The illustration's width in pixels, as the control and the panel both bound
 * it. The upper bound is the 200px sidebar column it sits in.
 */
export const SIDEBAR_IMAGE_WIDTH = { min: 40, max: 200, default: 118 };

/**
 * Reads the overrides from a `data-*` string or a block attribute object.
 *
 * Anything unrecognised, non-string or blank is dropped, so a missing key and
 * a key cleared back to empty both mean the same thing: keep the panel's own
 * wording.
 *
 * @param {string|Object} value Raw overrides.
 * @return {Object} Overrides, keyed by PANEL_CONTENT_KEYS.
 */
export const parsePanelContent = (value) => {
	let raw = value;

	if (typeof raw === "string") {
		try {
			raw = JSON.parse(raw);
		} catch (e) {
			return {};
		}
	}

	if (!raw || typeof raw !== "object") {
		return {};
	}

	return PANEL_CONTENT_KEYS.reduce((content, key) => {
		if (PANEL_CONTENT_NUMBER_KEYS.includes(key)) {
			// Comes back as a string from a `data-*` blob and as a number from
			// the block attribute, so both are read the same way. Anything that
			// is not a positive number is "unset".
			const number = parseInt(raw[key], 10);

			if (Number.isInteger(number) && number > 0) {
				content[key] = number;
			}

			return content;
		}

		const text = typeof raw[key] === "string" ? raw[key].trim() : "";

		if (text !== "") {
			content[key] = text;
		}

		return content;
	}, {});
};

/**
 * One override, or the panel's own wording when it is unset.
 *
 * @param {Object} content  Parsed overrides.
 * @param {string} key      Override name.
 * @param {string} fallback The component's own text.
 * @return {string} Text to render.
 */
export const panelText = (content, key, fallback) =>
	(content && content[key]) || fallback;
