import {
	useBlockProps,
	InspectorControls,
	ColorPalette,
} from "@wordpress/block-editor";
import {
	PanelBody,
	ToggleControl,
	Spinner,
	BaseControl,
	FormTokenField,
	Notice,
} from "@wordpress/components";
import { useEffect, useMemo, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import SelectionSidebar from "../../components/BookingService/SelectionSidebar.jsx";
import CategoryCards from "../../components/BookingService/CategoryCards.jsx";

// Reuse the real frontend look so the editor preview matches the first step
// rendered on the published page.
import "../../components/BookingService/bookingstyle.scss";

// FormTokenField identifies a token by its label, so two rows sharing a name
// would be indistinguishable and the second could never be picked. Build the
// picker options up front, disambiguating only the names that actually clash.
const toOptions = (rows) => {
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
// Turns a saved id list into the token labels FormTokenField renders, and
// back again on change. Ids whose row no longer exists are dropped rather
// than shown as an unresolvable token.
const idsToTokens = (ids, options) =>
	(ids || [])
		.map((id) => options.find((option) => option.id === id)?.name)
		.filter(Boolean);

const tokensToIds = (tokens, options) =>
	(tokens || [])
		.map(
			(token) =>
				options.find(
					(option) =>
						option.name === (typeof token === "string" ? token : token?.value),
				)?.id,
		)
		.filter((id) => Number.isInteger(id));

// Editor preview: renders the booking panel's first step (selection sidebar +
// category cards) live, reusing the real frontend components. The actual
// interactive panel is mounted on the public side by
// BookingPanelBlock::renderBlock().
const Edit = ({ attributes, setAttributes }) => {
	const {
		hideNavigation,
		hideInfo,
		showBackground,
		// NOTE: named `backgroundColor` because it is ours alone — the block does
		// not opt into `supports.color`, which would reserve that same attribute
		// name for a palette slug. Rename this if colour support is ever added.
		backgroundColor,
		locationIds,
		categoryIds,
	} = attributes;

	const blockProps = useBlockProps({
		className: "rox-booking-panel-block-editor",
	});

	const [structure, setStructure] = useState(null);
	const [categories, setCategories] = useState([]);
	// Only populated when the panel actually starts from a Location step —
	// see the locationsApi fetch below. An empty list hides the location
	// control entirely.
	const [locations, setLocations] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		let active = true;

		// The pickers list everything the site has, so ask for a page big enough
		// to hold it rather than the endpoints' 20-row default. These URLs come
		// from the structure endpoint as absolute `get_rest_url()` values, so they
		// already carry the site's subdirectory — but on plain permalinks they
		// arrive as `?rest_route=…`, so pick the separator instead of assuming "?".
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
				// always fell through to "/wp-json/" — the *site* root, which 404s
				// whenever WordPress lives in a subdirectory (…/booking/) or runs on
				// plain permalinks. apiFetch resolves `path` against the REST root
				// WordPress itself printed for the editor.
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

	// A single location is auto-selected by the panel and its step skipped, so
	// there is no choice to restrict — the control only earns its place from two
	// locations up.
	const canRestrictLocations = locations.length > 1;

	// What the preview's category step will actually offer. Ids left over from a
	// deleted category resolve to nothing, and an empty result means the panel
	// falls back to showing everything — mirrored here so the preview matches.
	const previewCategories = (() => {
		if (!categoryIds?.length) return categories;
		const picked = categories.filter((category) =>
			categoryIds.includes(category.id),
		);
		return picked.length > 0 ? picked : categories;
	})();

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Layout", "rox-appointment-booking")}>
					<ToggleControl
						label={__("Hide left navigation", "rox-appointment-booking")}
						checked={!!hideNavigation}
						onChange={(value) => setAttributes({ hideNavigation: value })}
					/>
					<ToggleControl
						label={__("Hide right info section", "rox-appointment-booking")}
						checked={!!hideInfo}
						onChange={(value) => setAttributes({ hideInfo: value })}
						help={__(
							"The right info / booking summary appears from the Date & Time step onward, so it is not visible on this first-step preview.",
							"rox-appointment-booking",
						)}
					/>
				</PanelBody>
				<PanelBody
					title={__("Availability", "rox-appointment-booking")}
					initialOpen={false}
				>
					{canRestrictLocations && (
						<FormTokenField
							label={__("Locations", "rox-appointment-booking")}
							value={idsToTokens(locationIds, locationOptions)}
							suggestions={locationOptions.map((option) => option.name)}
							onChange={(tokens) =>
								setAttributes({
									locationIds: tokensToIds(tokens, locationOptions),
								})
							}
							__experimentalExpandOnFocus
							__nextHasNoMarginBottom
							help={__(
								"Leave empty to offer every location. Pick one or more to limit the location step to just those.",
								"rox-appointment-booking",
							)}
						/>
					)}

					<FormTokenField
						label={__("Categories", "rox-appointment-booking")}
						value={idsToTokens(categoryIds, categoryOptions)}
						suggestions={categoryOptions.map((option) => option.name)}
						onChange={(tokens) =>
							setAttributes({ categoryIds: tokensToIds(tokens, categoryOptions) })
						}
						__experimentalExpandOnFocus
						__nextHasNoMarginBottom
						help={__(
							"Leave empty to offer every category. Pick one or more to limit the category step to just those.",
							"rox-appointment-booking",
						)}
					/>

					{!loading && categories.length === 0 && (
						<Notice status="warning" isDismissible={false}>
							{__(
								"No categories found. Add one under Rox Appointment Booking → Categories.",
								"rox-appointment-booking",
							)}
						</Notice>
					)}
				</PanelBody>
				<PanelBody
					title={__("Appearance", "rox-appointment-booking")}
					initialOpen={false}
				>
					<ToggleControl
						label={__("Enable background", "rox-appointment-booking")}
						help={__(
							"Draws the grey frame (background, padding and shadow) around the panel. Turn it off to let the panel sit directly on the page.",
							"rox-appointment-booking",
						)}
						checked={!!showBackground}
						onChange={(value) => setAttributes({ showBackground: value })}
					/>
					{showBackground && (
						<BaseControl
							id="rox-booking-panel-background-color"
							label={__("Background color", "rox-appointment-booking")}
							help={__(
								"Leave empty to keep the panel's default grey.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						>
							<ColorPalette
								value={backgroundColor || undefined}
								// Clearing hands back `undefined`; store "" so the
								// attribute keeps its declared string type.
								onChange={(value) =>
									setAttributes({ backgroundColor: value || "" })
								}
								clearable
							/>
						</BaseControl>
					)}
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{/* Static preview: clicks are disabled so the editor stays inert. */}
				<div
					className={`service-layout-outer rox-booking-panel-preview${
						showBackground ? "" : " no-background"
					}`}
					style={{
						pointerEvents: "none",
						...(showBackground && backgroundColor
							? { backgroundColor }
							: {}),
					}}
				>
					<div className="service-layout">
						{!hideNavigation && (
							<div className="service-sidebar selection-step">
								{structure ? (
									<SelectionSidebar sidebarDetails={structure} />
								) : null}
							</div>
						)}

						<div className="main without-navigation">
							<div className="main-content">
								{loading ? (
									<div className="rox-booking-panel-preview__loading">
										<Spinner />
									</div>
								) : (
									<CategoryCards
										categories={previewCategories}
										onCategorySelect={() => {}}
										selectedCategoryId={null}
									/>
								)}
							</div>
						</div>

						{!hideInfo && <div className="right-sidebar-content" />}
					</div>
				</div>
			</div>
		</>
	);
};

export default Edit;
