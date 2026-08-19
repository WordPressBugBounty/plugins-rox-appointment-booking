/**
 * ID-list helpers shared by the booking panel surfaces.
 *
 * The booking-panel block and Elementor widget let an editor restrict which
 * locations / categories a visitor may pick from. That choice reaches the
 * frontend as a comma-separated `data-*` string and is sent back to the public
 * REST endpoints as an `ids` query param, so both directions normalise here.
 *
 * Mirrors the PHP `RoxAppointmentBooking\Supports\IdList`.
 */

/**
 * Parses a comma-separated id string (or an array) into unique positive ints.
 *
 * @param {string|Array} value Raw id list.
 * @return {number[]} Unique positive ids, in the order given.
 */
export const parseIdList = (value) => {
	const items = Array.isArray(value) ? value : String(value ?? "").split(",");

	return items.reduce((ids, item) => {
		const id = parseInt(item, 10);
		if (Number.isInteger(id) && id > 0 && !ids.includes(id)) ids.push(id);
		return ids;
	}, []);
};

/**
 * Appends an `ids` filter to a public list endpoint URL. An empty list means
 * "no restriction", so the URL is handed back untouched.
 *
 * @param {string}   url URL to filter.
 * @param {number[]} ids Allowed ids.
 * @return {string} URL, filtered when there is something to filter by.
 */
export const withIdFilter = (url, ids) => {
	if (!url || !Array.isArray(ids) || ids.length === 0) return url;

	return `${url}${url.includes("?") ? "&" : "?"}ids=${ids.join(",")}`;
};
