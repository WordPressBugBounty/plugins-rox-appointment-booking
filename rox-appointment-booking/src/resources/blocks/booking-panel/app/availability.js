/**
 * Location / category picker data for the block's "Availability" controls.
 *
 * The booking panel lets an editor restrict which locations and categories a
 * visitor is offered. Rendering those controls needs three things: the option
 * rows, the id <-> token translation FormTokenField works in, and the knowledge
 * of whether a location step exists on this site at all.
 */

import { useEffect, useMemo, useState } from "@wordpress/element";
import apiFetch from "@wordpress/api-fetch";

/**
 * FormTokenField identifies a token by its label, so two rows sharing a name
 * would be indistinguishable and the second could never be picked. Options are
 * built up front, disambiguating only the names that actually clash.
 *
 * @param {Array} rows Rows from the REST list endpoint.
 * @return {Array<{id:number,name:string}>} Picker options.
 */
export const toOptions = (rows) => {
	const labelled = (rows || []).map((row) => ({
		id: row?.id,
		name: row?.name || `#${row?.id}`,
	}));

	return labelled.map((option) => {
		const clashes =
			labelled.filter((other) => other.name === option.name).length > 1;

		return clashes
			? { ...option, name: `${option.name} (#${option.id})` }
			: option;
	});
};

/**
 * Turns a saved id list into the token labels FormTokenField renders. Ids whose
 * row no longer exists are dropped rather than shown as an unresolvable token.
 *
 * @param {number[]} ids     Saved ids.
 * @param {Array}    options Picker options.
 * @return {string[]} Token labels.
 */
export const idsToTokens = (ids, options) =>
	(ids || [])
		.map((id) => options.find((option) => option.id === id)?.name)
		.filter(Boolean);

/**
 * Translates the tokens FormTokenField hands back into ids.
 *
 * @param {Array} tokens  Tokens from onChange.
 * @param {Array} options Picker options.
 * @return {number[]} Ids.
 */
export const tokensToIds = (tokens, options) =>
	(tokens || [])
		.map(
			(token) =>
				options.find(
					(option) =>
						option.name === (typeof token === "string" ? token : token?.value),
				)?.id,
		)
		.filter((id) => Number.isInteger(id));

/**
 * Loads the panel structure plus the location and category lists the
 * Availability controls offer.
 *
 * @return {{
 *   structure: Object|null,
 *   categories: Array,
 *   locationOptions: Array,
 *   categoryOptions: Array,
 *   canRestrictLocations: boolean,
 *   loading: boolean
 * }}
 */
export const useAvailabilityOptions = () => {
	const [structure, setStructure] = useState(null);
	const [categories, setCategories] = useState([]);
	// Only populated when the panel actually starts from a Location step — see
	// the locationsApi fetch below. An empty list hides the location control.
	const [locations, setLocations] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		let active = true;

		// The pickers list everything the site has, so ask for a page big enough
		// to hold it rather than the endpoints' 20-row default. These URLs arrive
		// from the structure endpoint as absolute values, so they already carry
		// the site's subdirectory — but on plain permalinks they come through as
		// `?rest_route=…`, so pick the separator instead of assuming "?".
		const listAll = (apiUrl) =>
			apiFetch({
				url: `${apiUrl}${apiUrl.includes("?") ? "&" : "?"}per_page=100`,
			})
				.then((res) => (Array.isArray(res?.data) ? res.data : []))
				.catch(() => []);

		(async () => {
			try {
				// `path`, not a hand-built URL: the editor bundle is not localised
				// with `window.rox_appointment_booking`, so reading restBaseUrl here
				// would fall through to the *site* root, which 404s whenever
				// WordPress lives in a subdirectory or runs on plain permalinks.
				const res = await apiFetch({
					path: "/rox-appointment-booking/v1/booking-panel-structure",
				});
				const data = res?.data || {};
				if (!active) return;
				setStructure(data);

				const catApi = data?.content?.categoriesApi;
				if (catApi) {
					const list = await listAll(catApi);
					if (active) setCategories(list);
				}

				// `location` is only true when Pro is active, the location module is
				// on and at least one location exists — i.e. exactly when the panel
				// starts from a Location step. Anything else and the location picker
				// has nothing to restrict, so the list stays empty and it stays hidden.
				const locApi = data?.content?.locationsApi;
				if (data?.location && locApi) {
					const list = await listAll(locApi);
					if (active) setLocations(list);
				}
			} finally {
				if (active) setLoading(false);
			}
		})();

		return () => {
			active = false;
		};
	}, []);

	const locationOptions = useMemo(() => toOptions(locations), [locations]);
	const categoryOptions = useMemo(() => toOptions(categories), [categories]);

	return {
		structure,
		// The rows themselves, not just the picker options: the editor preview
		// draws real category cards, which need the icon, description and
		// service count that toOptions() drops.
		categories,
		locationOptions,
		categoryOptions,
		// A single location is auto-selected by the panel and its step skipped, so
		// there is no choice to restrict — the control only earns its place from
		// two locations up.
		canRestrictLocations: locations.length > 1,
		loading,
	};
};
