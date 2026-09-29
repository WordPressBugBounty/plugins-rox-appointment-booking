/**
 * Style 1's editor preview.
 *
 * The booking panel's first step (selection sidebar + category cards), rendered
 * live from the real frontend components so the canvas matches the published
 * page. Only the on-page layout is drawn here: in popup mode the shell shows the
 * shared trigger preview instead, and the interactive panel itself is mounted on
 * the public side by BookingPanelBlock::renderBlock().
 */

import { useRef } from "@wordpress/element";
import { Spinner } from "@wordpress/components";

import SelectionSidebar from "../../../../components/BookingService/SelectionSidebar.jsx";
import CategoryCards from "../../../../components/BookingService/CategoryCards.jsx";
import NavButtonsPreview from "../../../shared/NavButtonsPreview.jsx";
import { navButtonVars } from "../../../../lib/navButtonVars.js";

import { selectedFontFor, useWebFont } from "../common/font.js";

// Reuse the real frontend look so the editor preview matches the first step
// rendered on the published page.
import "../../../../components/BookingService/bookingstyle.scss";

const Style1Preview = ({ attributes, availability }) => {
	const {
		hideNavigation,
		hideInfo,
		showBackground,
		backgroundColor,
		accentColor,
		fontFamily,
		categoryIds,
	} = attributes;

	const { structure, categories, loading } = availability;

	// The preview node, used to reach the document the editor canvas renders
	// in — an iframe of its own, whose <head> is where a web font must land.
	const previewRef = useRef(null);
	const selectedFont = selectedFontFor(fontFamily);

	useWebFont(previewRef, selectedFont);

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
		/* Static preview: clicks are disabled so the editor stays inert. */
		<div
			ref={previewRef}
			className={`service-layout-outer rox-booking-panel-preview${
				showBackground ? "" : " no-background"
			}`}
			style={{
				pointerEvents: "none",
				...(showBackground && backgroundColor
					? { backgroundColor }
					: {}),
				// Read by every font-family rule in the panel stylesheet,
				// which falls back to Heebo when it is unset.
				...(selectedFont?.stack
					? { "--rox-font-family": selectedFont.stack }
					: {}),
				// The accent every other panel colour is derived from.
				// PHP sets it on the mount node for the published page;
				// the preview root stands in for that here.
				...(accentColor ? { "--rox-accent": accentColor } : {}),
				// The Back / Next styling. On the published page PHP sets
				// these on the mount node; here the preview root stands in
				// for it, and the panel stylesheet reads them the same way.
				...navButtonVars(attributes),
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

				<div className="main with-navigation">
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

					{/* Shown on every step that has somewhere to go back to, so
					    the two style panels have something to preview. */}
					<NavButtonsPreview />
				</div>

				{!hideInfo && <div className="right-sidebar-content" />}
			</div>
		</div>
	);
};

export default Style1Preview;
