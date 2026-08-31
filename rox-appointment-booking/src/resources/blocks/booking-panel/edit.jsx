import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
	ColorPalette,
	PanelColorSettings,
} from "@wordpress/block-editor";
import * as components from "@wordpress/components";
import { useEffect, useRef } from "@wordpress/element";
import { __ } from "@wordpress/i18n";

import SelectionSidebar from "../../components/BookingService/SelectionSidebar.jsx";
import CategoryCards from "../../components/BookingService/CategoryCards.jsx";
import { ensureWebFont, googleFontHref } from "../../lib/webFont.js";
import NavButtonControls from "../shared/NavButtonControls.jsx";
import NavButtonsPreview from "../shared/NavButtonsPreview.jsx";
import { navButtonVars } from "../../lib/navButtonVars.js";

import ButtonPreview from "./app/ButtonPreview.jsx";
import {
	useAvailabilityOptions,
	idsToTokens,
	tokensToIds,
} from "./app/availability.js";

// Reuse the real frontend look so the editor preview matches the first step
// rendered on the published page.
import "../../components/BookingService/bookingstyle.scss";
// The trigger's own styles, so the popup-mode preview looks exactly like the
// published button rather than an approximation.
import "./app/booking-button.scss";

const {
	PanelBody,
	SelectControl,
	RangeControl,
	ToggleControl,
	TextControl,
	FormTokenField,
	Notice,
	Spinner,
	BaseControl,
	TabPanel,
} = components;

// BoxControl only became a stable export in a recent WordPress; the plugin
// supports 6.5, where it is still the experimental name. Picking at runtime
// keeps both alive without a version check. ToggleGroupControl has no stable
// export at all yet, so it falls back to a plain select.
const BoxControl = components.BoxControl || components.__experimentalBoxControl;
const ToggleGroupControl =
	components.ToggleGroupControl || components.__experimentalToggleGroupControl;
const ToggleGroupControlOption =
	components.ToggleGroupControlOption ||
	components.__experimentalToggleGroupControlOption;

// Printed by BookingPanelBlock::registerEditorAssets(), so the picker here and
// the panel on the published page can never offer different fonts.
const fontOptions = window?.rox_appointment_booking?.fontFamilies || [];

const ALIGNMENTS = ["left", "center", "right"];

const SPACING_UNITS = [
	{ value: "px", label: "px", default: 0 },
	{ value: "em", label: "em", default: 0 },
	{ value: "rem", label: "rem", default: 0 },
	{ value: "%", label: "%", default: 0 },
];

// Editor preview. In general mode it renders the booking panel's first step
// (selection sidebar + category cards) live, reusing the real frontend
// components; in popup mode it renders the trigger button alone, because that
// is all the published page shows until a visitor clicks. Either way the
// interactive panel itself is mounted on the public side by
// BookingPanelBlock::renderBlock().
const Edit = ({ attributes, setAttributes }) => {
	const {
		displayMode,
		hideNavigation,
		hideInfo,
		showBackground,
		// NOTE: named `backgroundColor` because it is ours alone — the block does
		// not opt into `supports.color`, which would reserve that same attribute
		// name for a palette slug. Rename this if colour support is ever added.
		backgroundColor,
		accentColor,
		fontFamily,
		locationIds,
		categoryIds,
		buttonText,
		buttonAlign,
		buttonWidth,
		buttonSize,
		buttonStyle,
		buttonIcon,
		buttonBackgroundColor,
		buttonTextColor,
		buttonBorderColor,
		buttonBackgroundColorHover,
		buttonTextColorHover,
		buttonBorderColorHover,
		buttonBorderStyle,
		buttonBorderWidth,
		buttonBorderRadius,
		modalWidth,
		resetOnClose,
	} = attributes;

	const isPopup = displayMode === "popup";

	const blockProps = useBlockProps({
		className: isPopup
			? "rox-booking-button-block-editor"
			: "rox-booking-panel-block-editor",
	});

	// The preview node, used to reach the document the editor canvas renders
	// in — an iframe of its own, whose <head> is where a web font must land.
	const previewRef = useRef(null);

	// An unknown key (a font dropped from the list) resolves to nothing, which
	// leaves the preview on the stylesheet's default exactly as the frontend.
	const selectedFont =
		fontOptions.find((option) => option.value === fontFamily) || null;

	useEffect(() => {
		if (!selectedFont) {
			return;
		}

		ensureWebFont(
			previewRef.current?.ownerDocument,
			googleFontHref(selectedFont),
		);
	}, [selectedFont]);

	const {
		structure,
		categories,
		locationOptions,
		categoryOptions,
		canRestrictLocations,
		loading,
	} = useAvailabilityOptions();

	// Width and alignment used to share `buttonAlign`, which meant touching one
	// silently reset the other. They are separate attributes now; blocks saved
	// under the old scheme still carry "full" here, so read through these two
	// rather than the raw attributes, and write both on the next change.
	const isLegacyFull = buttonAlign === "full";
	const align = isLegacyFull ? "left" : buttonAlign || "left";
	const width = isLegacyFull ? "full" : buttonWidth || "auto";

	const setAlign = (value) =>
		setAttributes({ buttonAlign: value || "left", buttonWidth: width });

	const setWidth = (value) =>
		setAttributes({ buttonWidth: value, buttonAlign: align });

	// Outline and link draw their label from the accent colour alone, so a
	// separate label colour would have nothing to do.
	const isFilled = buttonStyle === "filled";
	// A link variant is plain text: no box, so no border and no radius.
	const hasBox = buttonStyle !== "link";

	const colorSettings = (bg, fg, border, onBg, onFg, onBorder) =>
		[
			{
				value: bg || undefined,
				// Clearing hands back `undefined`; store "" so the attribute keeps
				// its declared string type.
				onChange: (value) => onBg(value || ""),
				label: isFilled
					? __("Background", "rox-appointment-booking")
					: __("Accent", "rox-appointment-booking"),
			},
			isFilled && {
				value: fg || undefined,
				onChange: (value) => onFg(value || ""),
				label: __("Text", "rox-appointment-booking"),
			},
			hasBox && {
				value: border || undefined,
				onChange: (value) => onBorder(value || ""),
				label: __("Border", "rox-appointment-booking"),
			},
		].filter(Boolean);

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
			{isPopup && (
				<BlockControls>
					<AlignmentToolbar
						value={ALIGNMENTS.includes(align) ? align : undefined}
						// Clearing the toolbar hands back undefined; fall back to the
						// default so the attribute keeps its declared string type.
						onChange={setAlign}
					/>
				</BlockControls>
			)}

			<InspectorControls>
				<PanelBody title={__("Display", "rox-appointment-booking")}>
					{ToggleGroupControl && ToggleGroupControlOption ? (
						<ToggleGroupControl
							label={__("Display mode", "rox-appointment-booking")}
							value={isPopup ? "popup" : "general"}
							onChange={(value) => setAttributes({ displayMode: value })}
							isBlock
							help={
								isPopup
									? __(
											"Only a button is shown on the page; the panel opens in a popup when it is clicked.",
											"rox-appointment-booking",
										)
									: __(
											"The booking panel is laid out on the page itself.",
											"rox-appointment-booking",
										)
							}
							__next40pxDefaultSize
							__nextHasNoMarginBottom
						>
							<ToggleGroupControlOption
								value="general"
								label={__("General", "rox-appointment-booking")}
							/>
							<ToggleGroupControlOption
								value="popup"
								label={__("Popup", "rox-appointment-booking")}
							/>
						</ToggleGroupControl>
					) : (
						<SelectControl
							label={__("Display mode", "rox-appointment-booking")}
							value={isPopup ? "popup" : "general"}
							options={[
								{
									label: __("General", "rox-appointment-booking"),
									value: "general",
								},
								{
									label: __("Popup", "rox-appointment-booking"),
									value: "popup",
								},
							]}
							onChange={(value) => setAttributes({ displayMode: value })}
							help={__(
								"General lays the panel out on the page. Popup shows a button that opens it.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
					)}
				</PanelBody>

				{isPopup && (
					<PanelBody title={__("Button", "rox-appointment-booking")}>
						<TextControl
							label={__("Button text", "rox-appointment-booking")}
							value={buttonText}
							onChange={(value) => setAttributes({ buttonText: value })}
							help={__(
								"Leave empty to fall back to “Book Appointment”.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={__("Style", "rox-appointment-booking")}
							value={buttonStyle}
							options={[
								{ label: __("Filled", "rox-appointment-booking"), value: "filled" },
								{ label: __("Outline", "rox-appointment-booking"), value: "outline" },
								{ label: __("Link", "rox-appointment-booking"), value: "link" },
							]}
							onChange={(value) => setAttributes({ buttonStyle: value })}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={__("Size", "rox-appointment-booking")}
							value={buttonSize}
							options={[
								{ label: __("Small", "rox-appointment-booking"), value: "small" },
								{ label: __("Medium", "rox-appointment-booking"), value: "medium" },
								{ label: __("Large", "rox-appointment-booking"), value: "large" },
							]}
							onChange={(value) => setAttributes({ buttonSize: value })}
							help={__(
								"Sets the default padding and text size. The Spacing panel overrides the padding.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
						<SelectControl
							label={__("Width", "rox-appointment-booking")}
							value={width}
							options={[
								{ label: __("Fit to text", "rox-appointment-booking"), value: "auto" },
								{ label: __("Full width", "rox-appointment-booking"), value: "full" },
							]}
							onChange={setWidth}
							__nextHasNoMarginBottom
						/>
						{/* Also on the block toolbar, but an alignment nobody can find
						    is an alignment nobody has. */}
						{width === "auto" &&
							(ToggleGroupControl && ToggleGroupControlOption ? (
								<ToggleGroupControl
									label={__("Alignment", "rox-appointment-booking")}
									value={align}
									onChange={setAlign}
									isBlock
									__next40pxDefaultSize
									__nextHasNoMarginBottom
								>
									<ToggleGroupControlOption
										value="left"
										label={__("Left", "rox-appointment-booking")}
									/>
									<ToggleGroupControlOption
										value="center"
										label={__("Center", "rox-appointment-booking")}
									/>
									<ToggleGroupControlOption
										value="right"
										label={__("Right", "rox-appointment-booking")}
									/>
								</ToggleGroupControl>
							) : (
								<SelectControl
									label={__("Alignment", "rox-appointment-booking")}
									value={align}
									options={[
										{ label: __("Left", "rox-appointment-booking"), value: "left" },
										{ label: __("Center", "rox-appointment-booking"), value: "center" },
										{ label: __("Right", "rox-appointment-booking"), value: "right" },
									]}
									onChange={setAlign}
									__nextHasNoMarginBottom
								/>
							))}
						<SelectControl
							label={__("Icon", "rox-appointment-booking")}
							value={buttonIcon}
							options={[
								{ label: __("None", "rox-appointment-booking"), value: "none" },
								{ label: __("Calendar", "rox-appointment-booking"), value: "calendar" },
								{ label: __("Clock", "rox-appointment-booking"), value: "clock" },
							]}
							onChange={(value) => setAttributes({ buttonIcon: value })}
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				)}

				{isPopup && (
					<PanelBody
						title={__("Button spacing", "rox-appointment-booking")}
						initialOpen={false}
					>
						{BoxControl ? (
							<>
								<BoxControl
									label={__("Padding", "rox-appointment-booking")}
									values={attributes.buttonPadding || {}}
									onChange={(value) =>
										setAttributes({ buttonPadding: value || {} })
									}
									units={SPACING_UNITS}
									__next40pxDefaultSize
								/>
								<BoxControl
									label={__("Margin", "rox-appointment-booking")}
									values={attributes.buttonMargin || {}}
									onChange={(value) =>
										setAttributes({ buttonMargin: value || {} })
									}
									units={SPACING_UNITS}
									allowReset
									__next40pxDefaultSize
								/>
							</>
						) : (
							<Notice status="warning" isDismissible={false}>
								{__(
									"Spacing controls need WordPress 6.5 or newer.",
									"rox-appointment-booking",
								)}
							</Notice>
						)}
					</PanelBody>
				)}

				{isPopup && (
					<PanelBody
						title={__("Button border", "rox-appointment-booking")}
						initialOpen={false}
					>
						{hasBox ? (
							<>
								<SelectControl
									label={__("Border style", "rox-appointment-booking")}
									value={buttonBorderStyle}
									options={[
										{ label: __("None", "rox-appointment-booking"), value: "none" },
										{ label: __("Solid", "rox-appointment-booking"), value: "solid" },
										{ label: __("Dashed", "rox-appointment-booking"), value: "dashed" },
										{ label: __("Dotted", "rox-appointment-booking"), value: "dotted" },
										{ label: __("Double", "rox-appointment-booking"), value: "double" },
									]}
									onChange={(value) => setAttributes({ buttonBorderStyle: value })}
									__nextHasNoMarginBottom
								/>
								{buttonBorderStyle !== "none" && (
									<RangeControl
										label={__("Border width", "rox-appointment-booking")}
										value={buttonBorderWidth ?? 0}
										onChange={(value) =>
											setAttributes({ buttonBorderWidth: value ?? 0 })
										}
										min={0}
										max={20}
										__nextHasNoMarginBottom
									/>
								)}
								<RangeControl
									label={__("Corner radius", "rox-appointment-booking")}
									value={buttonBorderRadius ?? 0}
									onChange={(value) =>
										setAttributes({ buttonBorderRadius: value ?? 0 })
									}
									min={0}
									max={100}
									__nextHasNoMarginBottom
								/>
							</>
						) : (
							<Notice status="info" isDismissible={false}>
								{__(
									"The Link style is plain text, so it has no border or corners to style.",
									"rox-appointment-booking",
								)}
							</Notice>
						)}
					</PanelBody>
				)}

				{isPopup && (
					<PanelBody
						title={__("Button colors", "rox-appointment-booking")}
						initialOpen={false}
					>
						<TabPanel
							className="rox-booking-button-color-tabs"
							tabs={[
								{ name: "normal", title: __("Normal", "rox-appointment-booking") },
								{ name: "hover", title: __("Hover", "rox-appointment-booking") },
							]}
						>
							{(tab) =>
								tab.name === "normal" ? (
									<PanelColorSettings
										// Rendered inside the panel above, so it must not draw
										// a second collapsible frame of its own.
										className="rox-booking-button-colors"
										title=""
										initialOpen
										colorSettings={colorSettings(
											buttonBackgroundColor,
											buttonTextColor,
											buttonBorderColor,
											(value) => setAttributes({ buttonBackgroundColor: value }),
											(value) => setAttributes({ buttonTextColor: value }),
											(value) => setAttributes({ buttonBorderColor: value }),
										)}
									/>
								) : (
									<PanelColorSettings
										className="rox-booking-button-colors"
										title=""
										initialOpen
										colorSettings={colorSettings(
											buttonBackgroundColorHover,
											buttonTextColorHover,
											buttonBorderColorHover,
											(value) =>
												setAttributes({ buttonBackgroundColorHover: value }),
											(value) => setAttributes({ buttonTextColorHover: value }),
											(value) => setAttributes({ buttonBorderColorHover: value }),
										)}
									/>
								)
							}
						</TabPanel>
						<p className="rox-booking-button-colors__help">
							{__(
								"Leave the hover colors empty to keep the default: the button simply brightens on hover.",
								"rox-appointment-booking",
							)}
						</p>
					</PanelBody>
				)}

				{isPopup && (
					<PanelBody
						title={__("Popup", "rox-appointment-booking")}
						initialOpen={false}
					>
						<RangeControl
							label={__("Maximum width (px)", "rox-appointment-booking")}
							value={modalWidth}
							onChange={(value) => setAttributes({ modalWidth: value ?? 1100 })}
							min={600}
							max={2000}
							step={20}
							help={__(
								"The booking panel is a wide, multi-column layout. On small screens the popup always goes full-screen regardless of this value.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
						<ToggleControl
							label={__("Reset booking on close", "rox-appointment-booking")}
							checked={!!resetOnClose}
							onChange={(value) => setAttributes({ resetOnClose: value })}
							help={__(
								"Off: closing the popup keeps the visitor's progress, so reopening returns them to the same step. On: closing starts a fresh booking.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
					</PanelBody>
				)}

				<PanelBody
					title={__("Layout", "rox-appointment-booking")}
					initialOpen={!isPopup}
				>
					<ToggleControl
						label={__("Hide left navigation", "rox-appointment-booking")}
						checked={!!hideNavigation}
						onChange={(value) => setAttributes({ hideNavigation: value })}
						__nextHasNoMarginBottom
					/>
					<ToggleControl
						label={__("Hide right info section", "rox-appointment-booking")}
						checked={!!hideInfo}
						onChange={(value) => setAttributes({ hideInfo: value })}
						help={
							isPopup
								? __(
										"The right info / booking summary appears from the Date & Time step onward.",
										"rox-appointment-booking",
									)
								: __(
										"The right info / booking summary appears from the Date & Time step onward, so it is not visible on this first-step preview.",
										"rox-appointment-booking",
									)
						}
					/>
				</PanelBody>

				<PanelBody
					title={__("Availability", "rox-appointment-booking")}
					initialOpen={false}
				>
					{/* No agent lock here in either mode: a panel tied to one agent
					    is what the Single Agent Booking Panel block is for. */}
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

					{!loading && categoryOptions.length === 0 && (
						<Notice status="warning" isDismissible={false}>
							{__(
								"No categories found. Add one under Rox Appointment Booking → Categories.",
								"rox-appointment-booking",
							)}
						</Notice>
					)}
				</PanelBody>

				<PanelBody
					title={__("Panel appearance", "rox-appointment-booking")}
					initialOpen={false}
				>
					<BaseControl
						id="rox-booking-panel-accent-color"
						label={__("Panel color", "rox-appointment-booking")}
						help={__(
							"Recolours every accent surface at once — the Next button, the active step markers, selected cards and time slots, links and focus rings. The Back and Next button panels below override this for those two buttons. Leave empty to keep the panel default.",
							"rox-appointment-booking",
						)}
						__nextHasNoMarginBottom
					>
						<ColorPalette
							value={accentColor || undefined}
							// Clearing hands back `undefined`; store "" so the
							// attribute keeps its declared string type.
							onChange={(value) => setAttributes({ accentColor: value || "" })}
							clearable
						/>
					</BaseControl>
					{fontOptions.length > 0 && (
						<SelectControl
							label={__("Font family", "rox-appointment-booking")}
							value={fontFamily || ""}
							options={fontOptions.map(({ value, label }) => ({
								value,
								label,
							}))}
							onChange={(value) => setAttributes({ fontFamily: value })}
							help={__(
								"Applies to the whole panel. Pick \"Theme font\" to let it inherit the font of the page it sits on.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
					)}
					<ToggleControl
						label={__("Enable background", "rox-appointment-booking")}
						help={
							isPopup
								? __(
										"Draws the grey frame (background, padding and shadow) around the panel inside the popup. Turn it off to let the panel sit directly on the popup's own card.",
										"rox-appointment-booking",
									)
								: __(
										"Draws the grey frame (background, padding and shadow) around the panel. Turn it off to let the panel sit directly on the page.",
										"rox-appointment-booking",
									)
						}
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
				<PanelBody
					title={__("Back button", "rox-appointment-booking")}
					initialOpen={false}
				>
					<NavButtonControls
						prefix="navBack"
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</PanelBody>
				<PanelBody
					title={__("Next button", "rox-appointment-booking")}
					initialOpen={false}
				>
					<NavButtonControls
						prefix="navNext"
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{isPopup ? (
					/* Static preview: the real panel only exists once a visitor clicks
					   the published button, so the editor shows the trigger alone. */
					<ButtonPreview
						text={buttonText || __("Book Appointment", "rox-appointment-booking")}
						align={align}
						width={width}
						size={buttonSize}
						style={buttonStyle}
						icon={buttonIcon}
						backgroundColor={buttonBackgroundColor}
						textColor={buttonTextColor}
						borderColor={buttonBorderColor}
						backgroundColorHover={buttonBackgroundColorHover}
						textColorHover={buttonTextColorHover}
						borderColorHover={buttonBorderColorHover}
						borderStyle={buttonBorderStyle}
						padding={attributes.buttonPadding || {}}
						margin={attributes.buttonMargin || {}}
						borderWidth={buttonBorderWidth ?? 1}
						borderRadius={buttonBorderRadius ?? 6}
					/>
				) : (
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
				)}
			</div>
		</>
	);
};

export default Edit;
