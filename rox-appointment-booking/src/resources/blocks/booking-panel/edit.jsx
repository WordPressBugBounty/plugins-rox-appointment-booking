/**
 * The booking panel block's editor shell.
 *
 * It owns the style picker (the first control in the sidebar, because the
 * canvas below it depends on the answer), the controls every style shares, and
 * the editor state those controls and the preview both need. The panel drawn on
 * the canvas comes from the chosen style's module in `app/styles.js` — except in
 * popup mode, where the published page shows nothing but the trigger and so does
 * the preview, whichever style is picked.
 */

import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import * as components from "@wordpress/components";
import { useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import { panelStyleOptions } from "../../lib/panelStyle.js";

import { useAvailabilityOptions } from "./app/availability.js";
import Controls from "./app/common/Controls.jsx";
import TriggerPreview from "./app/common/TriggerPreview.jsx";
import { getStyle } from "./app/styles.js";

const { PanelBody, SelectControl } = components;

// ToggleGroupControl has no stable export yet, so the picker falls back to a
// plain select on a WordPress that does not carry the experimental one.
const ToggleGroupControl =
	components.ToggleGroupControl || components.__experimentalToggleGroupControl;
const ToggleGroupControlOption =
	components.ToggleGroupControlOption ||
	components.__experimentalToggleGroupControlOption;

const Edit = ({ attributes, setAttributes }) => {
	const { styleVariant, displayMode } = attributes;

	const style = getStyle(styleVariant);
	const StyleControls = style.Controls;
	const StylePreview = style.Preview;

	const isPopup = displayMode === "popup";

	const blockProps = useBlockProps({
		className: isPopup
			? "rox-booking-button-block-editor"
			: style.className(attributes),
	});

	// Which device the responsive controls are editing. Editor-only — nothing
	// about it is saved — and held here rather than inside a style because the
	// controls and the preview both read it.
	const [device, setDevice] = useState("desktop");

	// Fetched once and handed to both the Availability controls and the style's
	// preview, so a canvas showing real category cards costs no second round of
	// requests.
	const availability = useAvailabilityOptions();

	const styleOptions = panelStyleOptions();

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Style", "rox-appointment-booking")}>
					{ToggleGroupControl && ToggleGroupControlOption ? (
						<ToggleGroupControl
							label={__("Panel style", "rox-appointment-booking")}
							value={style.name}
							onChange={(value) => setAttributes({ styleVariant: value })}
							isBlock
							help={__(
								"The design this block renders. Every control below applies to all of them.",
								"rox-appointment-booking",
							)}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							{styleOptions.map((option) => (
								<ToggleGroupControlOption
									key={option.value}
									value={option.value}
									label={option.label}
								/>
							))}
						</ToggleGroupControl>
					) : (
						<SelectControl
							label={__("Panel style", "rox-appointment-booking")}
							value={style.name}
							options={styleOptions}
							onChange={(value) => setAttributes({ styleVariant: value })}
							help={__(
								"The design this block renders. Every control below applies to all of them.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
					)}
				</PanelBody>
			</InspectorControls>

			{/* A style with panels of its own puts them between the picker and the
			    shared controls, which is the order the sidebar renders these
			    fills in. Neither style has any yet. */}
			{StyleControls && (
				<StyleControls
					attributes={attributes}
					setAttributes={setAttributes}
					device={device}
					setDevice={setDevice}
				/>
			)}

			<Controls
				attributes={attributes}
				setAttributes={setAttributes}
				device={device}
				setDevice={setDevice}
				availability={availability}
				// Shared controls ask this which of them the chosen design can
				// actually apply; see `styleSupports` in app/styles.js.
				style={style}
			/>

			<div {...blockProps}>
				{isPopup ? (
					<TriggerPreview attributes={attributes} device={device} />
				) : (
					<StylePreview
						attributes={attributes}
						device={device}
						availability={availability}
					/>
				)}
			</div>
		</>
	);
};

export default Edit;
