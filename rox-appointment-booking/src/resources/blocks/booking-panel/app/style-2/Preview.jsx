/**
 * Style 2's editor preview.
 *
 * The Category step drawn from the real Style 2 components and the real
 * category rows, so the canvas matches the published page. Same approach as
 * Style 1's preview: only the on-page layout is drawn here — in popup mode the
 * shell shows the shared trigger preview instead, and the interactive panel is
 * mounted on the public side by BookingPanelBlock::renderBlock().
 *
 * Category rather than Location is previewed even on a site that starts from a
 * Location step (Style 1's preview does the same): the availability data the
 * editor loads carries full category rows, while its location rows are reduced
 * to id + name for the token pickers, which would draw a poorer card.
 */

import { useRef } from "@wordpress/element";
import { Spinner } from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";

import Sidebar, {
	PANEL_BRAND,
} from "../../../../frontend/styles/style-2/Sidebar.jsx";
import CardListStep from "../../../../frontend/styles/style-2/CardListStep.jsx";
import { fullStepKeys, panelStepLabels } from "../../../../lib/panelSteps.js";
import { resolveShowBackground } from "../../../../lib/panelStyle.js";
import { navButtonVars } from "../../../../lib/navButtonVars.js";
import { panelText } from "../../../../lib/panelContent.js";
import { style2PanelVars } from "../../../../frontend/styles/style-2/panelVars.js";
import { style2StepHeadings } from "../../../../frontend/styles/style-2/headings.js";

import { selectedFontFor, useWebFont } from "../common/font.js";

// Reuse the real frontend look so the editor preview matches the step
// rendered on the published page.
import "../../../../frontend/styles/style-2/panel.scss";

const Style2Preview = ({ attributes, availability }) => {
	const {
		hideNavigation,
		showBackground,
		backgroundColor,
		accentColor,
		fontFamily,
		categoryIds,
		panelContent,
		serviceColumns,
		headingAlign,
		headingMargin,
	} = attributes;

	const { structure, categories, loading } = availability;

	// Unset means nobody chose, which for this design is no frame. Resolved
	// through the shared helper so the canvas, the toggle and the published
	// page can never disagree about what "unset" means.
	const framed = resolveShowBackground(showBackground, "style-2");

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

	// `structure.location` is true only when the panel really does start from a
	// Location step, which is what decides whether the step list carries one.
	const stepKeys = fullStepKeys(Boolean(structure?.location));
	const labels = panelStepLabels(panelContent);
	const steps = stepKeys.map((key) => labels[key]);
	// Category is what the canvas previews, so it is the step shown as current —
	// a Location step ahead of it then reads as already done, exactly as it
	// would for a visitor who had just picked one.
	const currentStep = stepKeys.indexOf("Category") + 1;

	return (
		/* Static preview: clicks are disabled so the editor stays inert. */
		<div
			ref={previewRef}
			className={`rox-booking-style-2 rox-booking-panel-preview ${
				framed ? "has-background" : ""
			}`.trim()}
			style={{
				pointerEvents: "none",
				// The frame's own colour, on the frame rather than on the card
				// inside it — the same element the mounted panel puts it on.
				...(framed && backgroundColor ? { backgroundColor } : {}),
				// Read by every font-family rule in the panel stylesheet,
				// which falls back to Heebo when it is unset.
				...(selectedFont?.stack
					? { "--rox-font-family": selectedFont.stack }
					: {}),
				// The accent every other panel colour is derived from. PHP sets it
				// on the mount node for the published page; the preview root
				// stands in for that here.
				...(accentColor ? { "--rox-accent": accentColor } : {}),
				// The Back / Continue styling. PHP sets these on the mount node
				// for the published page; the preview root stands in for it,
				// and the panel stylesheet reads them the same way.
				...navButtonVars(attributes),
			}}
		>
			<div
				className="rbs1-shell"
				style={{
					// The same properties the mounted panel sets, so the heading
					// controls read the same here as on the published page.
					...style2PanelVars({
						serviceColumns,
						headingAlign,
						headingMargin,
					}),
				}}
			>
				{!hideNavigation && (
					<Sidebar
						brand={PANEL_BRAND}
						steps={steps}
						currentStep={currentStep}
					/>
				)}

				<div className="rbs1-main">
					<div className="rbs1-header">
						{/* The step's heading, which is not the rail's label for it —
						    the mounted panel draws "Available Category" here and
						    "Category" beside it, so the preview does the same. */}
						<h2 className="rbs1-header__title">
							{panelText(
								panelContent,
								"categoryHeading",
								style2StepHeadings().categoryHeading,
							)}
						</h2>
						<span className="rbs1-header__counter">
							{sprintf(
								/* translators: 1: current step number, 2: total number of steps */
								__("Step %1$d of %2$d", "rox-appointment-booking"),
								currentStep,
								steps.length,
							)}
						</span>
					</div>

					<div className="rbs1-body">
						{loading ? (
							<div className="rox-booking-panel-preview__loading">
								<Spinner />
							</div>
						) : (
							<CardListStep
								items={previewCategories}
								onSelect={() => {}}
								selectedId={null}
								getIcon={(category) => category.iconPath}
								getTitle={(category) => category.name}
								getSubtitle={(category) => category.description}
								getBadge={(category) => category.services_count}
								emptyTitle={__("No Categories Available", "rox-appointment-booking")}
								emptyDescription={__("No service categories are available yet.", "rox-appointment-booking")}
							/>
						)}
					</div>

					<div className="rbs1-footer">
						<button type="button" className="rbs1-btn rbs1-btn--secondary">
							{__("Back", "rox-appointment-booking")}
						</button>
						<button type="button" className="rbs1-btn rbs1-btn--primary">
							{__("Continue", "rox-appointment-booking")}
						</button>
					</div>
				</div>
			</div>
		</div>
	);
};

export default Style2Preview;
