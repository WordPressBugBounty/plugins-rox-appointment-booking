/**
 * The block's inspector controls.
 *
 * Every style shares these: how the panel is shown (on the page or behind a
 * trigger button), how that button looks on each device, which of the panel's
 * columns are shown, what the visitor is offered, and how the panel itself is
 * coloured. A style adds its own panels through its module's `Controls`, which
 * the shell renders after the picker and before these.
 */

import {
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
	ColorPalette,
	PanelColorSettings,
} from "@wordpress/block-editor";
import {
	BaseControl,
	FormTokenField,
	Notice,
	PanelBody,
	RangeControl,
	SelectControl,
	TabPanel,
	TextControl,
	ToggleControl,
} from "@wordpress/components";
import { useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import NavButtonControls from "../../../shared/NavButtonControls.jsx";

import PanelContentControls from "../PanelContentControls.jsx";
import { resolveShowBackground } from "../../../../lib/panelStyle.js";
import { styleSupports } from "../styles.js";
import { idsToTokens, tokensToIds } from "../availability.js";
import { ButtonIcon } from "./ButtonPreview.jsx";
import { fontOptions } from "./font.js";
import iconSet from "../../icons.json";
import {
	ALIGNMENTS,
	BoxControl,
	SPACING_UNITS,
	ShadowControl,
	ToggleGroupControl,
	ToggleGroupControlOption,
	deviceLabelFor,
	deviceSuffix,
	resolved,
} from "./helpers.jsx";

// Icon names are shown as tooltips in the picker. Kept as an explicit map so
// they can be translated — a name derived from the JSON key could not be.
const ICON_LABELS = () => ({
	none: __("None", "rox-appointment-booking"),
	calendar: __("Calendar", "rox-appointment-booking"),
	clock: __("Clock", "rox-appointment-booking"),
	user: __("User", "rox-appointment-booking"),
	users: __("Users", "rox-appointment-booking"),
	phone: __("Phone", "rox-appointment-booking"),
	mail: __("Mail", "rox-appointment-booking"),
	chat: __("Chat", "rox-appointment-booking"),
	check: __("Check", "rox-appointment-booking"),
	"check-circle": __("Check circle", "rox-appointment-booking"),
	star: __("Star", "rox-appointment-booking"),
	heart: __("Heart", "rox-appointment-booking"),
	location: __("Location", "rox-appointment-booking"),
	scissors: __("Scissors", "rox-appointment-booking"),
	ticket: __("Ticket", "rox-appointment-booking"),
	bell: __("Bell", "rox-appointment-booking"),
	"arrow-right": __("Arrow", "rox-appointment-booking"),
	plus: __("Plus", "rox-appointment-booking"),
	sparkle: __("Sparkle", "rox-appointment-booking"),
});

const iconLabel = (name) => ICON_LABELS()[name] || name;

const Controls = ({
	attributes,
	setAttributes,
	device,
	setDevice,
	availability,
	// The chosen design's module. Controls that only some layouts can apply ask
	// it rather than testing the variant name, so adding a design means adding
	// one line to that design's module instead of editing this file.
	style,
}) => {
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
		buttonFillColor,
		buttonFillColorHover,
		buttonBorderStyle,
		modalWidth,
		resetOnClose,
		panelContent,
	} = attributes;

	const {
		locationOptions,
		categoryOptions,
		canRestrictLocations,
		loading,
	} = availability;

	const isPopup = displayMode === "popup";

	// The frame is one control, but its default is the design's: unset means
	// nobody chose, which is on for Style 1 and off for Style 2. The toggle has
	// to show what is actually in force, not the raw attribute.
	const framed = resolveShowBackground(showBackground, style?.name);

	// What the chosen design calls its footer buttons, so the two panels name
	// what the editor is looking at. A design that names neither keeps "Back
	// button" / "Next button".
	const navLabels = style?.navButtonLabels?.() || {};

	// Which device the responsive button controls are editing. Editor-only, and
	// owned by the block shell so the preview can follow along.
	const isDesktop = device === "desktop";
	const suffix = deviceSuffix(device);
	const deviceLabel = deviceLabelFor(device);

	// Eighteen icon tiles push every control below them off the screen, and
	// the icon is picked once and then left alone.
	const [iconPickerOpen, setIconPickerOpen] = useState(false);

	// Width and alignment used to share `buttonAlign`, which meant touching one
	// silently reset the other. They are separate attributes now; blocks saved
	// under the old scheme still carry "full" here, so read through these two
	// rather than the raw attributes, and write both on the next change.
	const isLegacyFull = buttonAlign === "full";
	const align = isLegacyFull ? "left" : buttonAlign || "left";
	const width = isLegacyFull ? "full" : buttonWidth || "auto";

	// On tablet and mobile the control shows what is actually in force — the
	// device's own value or the inherited one — and writes to that device only.
	const deviceAlign = isDesktop
		? align
		: resolved(attributes, "buttonAlign", device) || align;

	const setAlign = (value) =>
		isDesktop
			? setAttributes({ buttonAlign: value || "left", buttonWidth: width })
			: setAttributes({ [`buttonAlign${suffix}`]: value || "" });

	const setWidth = (value) =>
		setAttributes({ buttonWidth: value, buttonAlign: align });

	// Outline and link draw their label from the accent colour alone, so a
	// separate label colour would have nothing to do.
	const isFilled = buttonStyle === "filled";
	// A link variant is plain text: no box, so no border and no radius.
	const hasBox = buttonStyle !== "link";
	// Outline spends its accent on the label and border, which leaves the fill
	// itself with no control — this is that control. Not offered for link,
	// which is text with no surface to fill.
	const isOutline = buttonStyle === "outline";

	const colorSettings = (values, set) =>
		[
			{
				value: values.accent || undefined,
				// Clearing hands back `undefined`; store "" so the attribute keeps
				// its declared string type.
				onChange: (value) => set.accent(value || ""),
				label: isFilled
					? __("Background", "rox-appointment-booking")
					: __("Accent", "rox-appointment-booking"),
			},
			isFilled && {
				value: values.text || undefined,
				onChange: (value) => set.text(value || ""),
				label: __("Text", "rox-appointment-booking"),
			},
			isOutline && {
				value: values.fill || undefined,
				onChange: (value) => set.fill(value || ""),
				label: __("Background", "rox-appointment-booking"),
			},
			hasBox && {
				value: values.border || undefined,
				onChange: (value) => set.border(value || ""),
				label: __("Border", "rox-appointment-booking"),
			},
		].filter(Boolean);

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

				<PanelBody
					title={__("Panel content", "rox-appointment-booking")}
					initialOpen={false}
					className="rox-panel-content"
				>
					<PanelContentControls
						content={panelContent || {}}
						// The same answer the location picker is offered on:
						// more than one location is exactly when the panel
						// shows a Location step to rename.
						hasLocationStep={canRestrictLocations}
						// Style 2's first step is a card list with no
						// illustrated sidebar, so those fields are left out
						// rather than shown rewriting copy nothing renders.
						showFirstStepSidebar={styleSupports(
							style,
							"firstStepSidebar",
						)}
						// What the chosen design's headings say before anyone
						// rewrites them, so the fields offer its wording rather
						// than another design's. A style that names none keeps
						// the built-in defaults.
						stepHeadings={style?.stepHeadings?.()}
						servicesHeadingFollowsCategory={styleSupports(
							style,
							"servicesHeadingFollowsCategory",
						)}
						onChange={(value) => setAttributes({ panelContent: value })}
					/>
				</PanelBody>

				{isPopup && (
					<PanelBody title={__("Button", "rox-appointment-booking")}>
						{/* Alignment, spacing, border and icon metrics can differ per
						    device; everything else applies everywhere. */}
						{ToggleGroupControl && ToggleGroupControlOption && (
							<ToggleGroupControl
								label={__("Editing for", "rox-appointment-booking")}
								value={device}
								onChange={setDevice}
								isBlock
								help={
									isDesktop
										? __(
												"Alignment, spacing, border and icon settings below apply to every device unless you override them here.",
												"rox-appointment-booking",
											)
										: __(
												"Only alignment, spacing, border and icon metrics are editable per device. Leave a field empty to inherit the larger screen.",
												"rox-appointment-booking",
											)
								}
								__next40pxDefaultSize
								__nextHasNoMarginBottom
							>
								<ToggleGroupControlOption
									value="desktop"
									label={__("Desktop", "rox-appointment-booking")}
								/>
								<ToggleGroupControlOption
									value="tablet"
									label={__("Tablet", "rox-appointment-booking")}
								/>
								<ToggleGroupControlOption
									value="mobile"
									label={__("Mobile", "rox-appointment-booking")}
								/>
							</ToggleGroupControl>
						)}
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
						{/* On top of the choice above rather than instead of it: left
						    unset the choice decides, set it wins — which is what makes
						    it useful for lining a button up with something beside it. */}
						<RangeControl
							label={
								isDesktop
									? __("Width (px)", "rox-appointment-booking")
									: sprintf(
										/* translators: %s: device name, e.g. Tablet. */
										__("Width (px) — %s", "rox-appointment-booking"),
										deviceLabel,
									)
							}
							value={attributes[`buttonWidthSize${suffix}`] ?? undefined}
							onChange={(value) =>
								setAttributes({ [`buttonWidthSize${suffix}`]: value ?? null })
							}
							min={60}
							max={800}
							allowReset
							__nextHasNoMarginBottom
						/>
						{/* Also on the block toolbar, but an alignment nobody can find
						    is an alignment nobody has. */}
						{width === "auto" &&
							(ToggleGroupControl && ToggleGroupControlOption ? (
								<ToggleGroupControl
									label={
										isDesktop
											? __("Alignment", "rox-appointment-booking")
											: sprintf(
												/* translators: %s: device name, e.g. Tablet. */
												__("Alignment — %s", "rox-appointment-booking"),
												deviceLabel,
											)
									}
									value={deviceAlign}
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
									value={deviceAlign}
									options={[
										{ label: __("Left", "rox-appointment-booking"), value: "left" },
										{ label: __("Center", "rox-appointment-booking"), value: "center" },
										{ label: __("Right", "rox-appointment-booking"), value: "right" },
									]}
									onChange={setAlign}
									__nextHasNoMarginBottom
								/>
							))}
						<BaseControl
							label={__("Icon", "rox-appointment-booking")}
							id="rox-booking-button-icon"
							__nextHasNoMarginBottom
						>
							{/* Folded, the picker is a single row showing the current
							    choice; open, it is the whole set. */}
							<button
								type="button"
								className="rox-booking-button-icons__toggle"
								id="rox-booking-button-icon"
								aria-expanded={iconPickerOpen}
								onClick={() => setIconPickerOpen((open) => !open)}
							>
								<span className="rox-booking-button-icons__preview">
									{buttonIcon === "none" ? (
										<span className="rox-booking-button-icons__none">—</span>
									) : (
										<ButtonIcon name={buttonIcon} />
									)}
								</span>
								<span className="rox-booking-button-icons__name">
									{iconLabel(buttonIcon)}
								</span>
								<svg
									className="rox-booking-button-icons__chevron"
									width="16"
									height="16"
									viewBox="0 0 16 16"
									fill="none"
									stroke="currentColor"
									strokeWidth="1.5"
									strokeLinecap="round"
									strokeLinejoin="round"
									aria-hidden={true}
								>
									<path d="M4 6l4 4 4-4" />
								</svg>
							</button>

							{iconPickerOpen && (
								<div className="rox-booking-button-icons">
									{["none", ...Object.keys(iconSet)].map((name) => (
										<button
											key={name}
											type="button"
											className={`rox-booking-button-icons__item${
												buttonIcon === name ? " is-selected" : ""
											}`}
											aria-pressed={buttonIcon === name}
											aria-label={iconLabel(name)}
											title={iconLabel(name)}
											onClick={() => {
												setAttributes({ buttonIcon: name });
												setIconPickerOpen(false);
											}}
										>
											{name === "none" ? (
												<span className="rox-booking-button-icons__none">—</span>
											) : (
												<ButtonIcon name={name} />
											)}
										</button>
									))}
								</div>
							)}
						</BaseControl>
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
									label={
										isDesktop
											? __("Padding", "rox-appointment-booking")
											: sprintf(
												/* translators: %s: device name, e.g. Tablet. */
												__("Padding — %s", "rox-appointment-booking"),
												deviceLabel,
											)
									}
									values={attributes[`buttonPadding${suffix}`] || {}}
									onChange={(value) =>
										setAttributes({ [`buttonPadding${suffix}`]: value || {} })
									}
									units={SPACING_UNITS}
									__next40pxDefaultSize
								/>
								<BoxControl
									label={
										isDesktop
											? __("Margin", "rox-appointment-booking")
											: sprintf(
												/* translators: %s: device name, e.g. Tablet. */
												__("Margin — %s", "rox-appointment-booking"),
												deviceLabel,
											)
									}
									values={attributes[`buttonMargin${suffix}`] || {}}
									onChange={(value) =>
										setAttributes({ [`buttonMargin${suffix}`]: value || {} })
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
										label={
											isDesktop
												? __("Border width", "rox-appointment-booking")
												: sprintf(
													/* translators: %s: device name, e.g. Tablet. */
													__("Border width — %s", "rox-appointment-booking"),
													deviceLabel,
												)
										}
										value={resolved(attributes, "buttonBorderWidth", device) ?? 0}
										onChange={(value) =>
											setAttributes({
												[`buttonBorderWidth${suffix}`]:
													value ?? (isDesktop ? 0 : null),
											})
										}
										min={0}
										max={20}
										allowReset={!isDesktop}
										__nextHasNoMarginBottom
									/>
								)}
								{buttonBorderStyle !== "none" && (
									/* No per-device twin: this is the odd case of a border that
									   appears or thickens on hover, and that reads the same on
									   every screen. Left unset the resting width stands. */
									<RangeControl
										label={__("Border width — hover", "rox-appointment-booking")}
										value={attributes.buttonBorderWidthHover ?? undefined}
										onChange={(value) =>
											setAttributes({ buttonBorderWidthHover: value ?? null })
										}
										min={0}
										max={20}
										allowReset
										__nextHasNoMarginBottom
									/>
								)}
								<RangeControl
									label={
										isDesktop
											? __("Corner radius", "rox-appointment-booking")
											: sprintf(
												/* translators: %s: device name, e.g. Tablet. */
												__("Corner radius — %s", "rox-appointment-booking"),
												deviceLabel,
											)
									}
									value={resolved(attributes, "buttonBorderRadius", device) ?? 0}
									onChange={(value) =>
										setAttributes({
											[`buttonBorderRadius${suffix}`]:
												value ?? (isDesktop ? 0 : null),
										})
									}
									min={0}
									max={100}
									allowReset={!isDesktop}
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
						title={__("Button shadow", "rox-appointment-booking")}
						initialOpen={false}
					>
						<ShadowControl
							label={__("Box shadow", "rox-appointment-booking")}
							value={attributes.buttonBoxShadow}
							onChange={(value) => setAttributes({ buttonBoxShadow: value })}
						/>
						<ShadowControl
							label={__("Box shadow — hover", "rox-appointment-booking")}
							value={attributes.buttonBoxShadowHover}
							onChange={(value) =>
								setAttributes({ buttonBoxShadowHover: value })
							}
						/>
					</PanelBody>
				)}

				{isPopup && buttonIcon !== "none" && (
					<PanelBody
						title={__("Button icon", "rox-appointment-booking")}
						initialOpen={false}
					>
						<RangeControl
							label={
								isDesktop
									? __("Icon size", "rox-appointment-booking")
									: sprintf(
										/* translators: %s: device name, e.g. Tablet. */
										__("Icon size — %s", "rox-appointment-booking"),
										deviceLabel,
									)
							}
							value={attributes[`buttonIconSize${suffix}`] ?? undefined}
							onChange={(value) =>
								setAttributes({ [`buttonIconSize${suffix}`]: value ?? null })
							}
							min={8}
							max={64}
							allowReset
							__nextHasNoMarginBottom
						/>
						<RangeControl
							label={
								isDesktop
									? __("Space between", "rox-appointment-booking")
									: sprintf(
										/* translators: %s: device name, e.g. Tablet. */
										__("Space between — %s", "rox-appointment-booking"),
										deviceLabel,
									)
							}
							value={attributes[`buttonIconGap${suffix}`] ?? undefined}
							onChange={(value) =>
								setAttributes({ [`buttonIconGap${suffix}`]: value ?? null })
							}
							min={0}
							max={60}
							allowReset
							__nextHasNoMarginBottom
						/>
						<RangeControl
							label={
								isDesktop
									? __("Move icon vertically", "rox-appointment-booking")
									: sprintf(
										/* translators: %s: device name, e.g. Tablet. */
										__("Move icon vertically — %s", "rox-appointment-booking"),
										deviceLabel,
									)
							}
							value={attributes[`buttonIconOffsetY${suffix}`] ?? undefined}
							onChange={(value) =>
								setAttributes({
									[`buttonIconOffsetY${suffix}`]: value ?? null,
								})
							}
							min={-20}
							max={20}
							allowReset
							help={__(
								"An icon's visual centre rarely lands on the text baseline; a pixel either way settles it.",
								"rox-appointment-booking",
							)}
							__nextHasNoMarginBottom
						/>
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
											{
												accent: buttonBackgroundColor,
												text: buttonTextColor,
												fill: buttonFillColor,
												border: buttonBorderColor,
											},
											{
												accent: (value) =>
													setAttributes({ buttonBackgroundColor: value }),
												text: (value) => setAttributes({ buttonTextColor: value }),
												fill: (value) => setAttributes({ buttonFillColor: value }),
												border: (value) =>
													setAttributes({ buttonBorderColor: value }),
											},
										)}
									/>
								) : (
									<PanelColorSettings
										className="rox-booking-button-colors"
										title=""
										initialOpen
										colorSettings={colorSettings(
											{
												accent: buttonBackgroundColorHover,
												text: buttonTextColorHover,
												fill: buttonFillColorHover,
												border: buttonBorderColorHover,
											},
											{
												accent: (value) =>
													setAttributes({ buttonBackgroundColorHover: value }),
												text: (value) =>
													setAttributes({ buttonTextColorHover: value }),
												fill: (value) =>
													setAttributes({ buttonFillColorHover: value }),
												border: (value) =>
													setAttributes({ buttonBorderColorHover: value }),
											},
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
					{/* Only for a design that has a right-hand column to hide. */}
					{styleSupports(style, "hideInfo") && (
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
					)}
					{/* How many service cards sit side by side on the Services
					    step. The stylesheet keeps it one-per-row on narrow
					    screens whatever this says. */}
					<RangeControl
						label={__("Service columns", "rox-appointment-booking")}
						value={attributes.serviceColumns ?? 2}
						onChange={(value) =>
							setAttributes({ serviceColumns: value || 2 })
						}
						min={1}
						max={2}
						help={__(
							"Number of service cards per row on the Services step. Always one per row on narrow screens.",
							"rox-appointment-booking",
						)}
						__nextHasNoMarginBottom
					/>

					{/* Opt-in, and left alone by default: the heading sits where it
					    always has unless an editor says otherwise. */}
					<SelectControl
						label={__("Heading alignment", "rox-appointment-booking")}
						value={attributes.headingAlign || ""}
						options={[
							{
								label: __("Default (left)", "rox-appointment-booking"),
								value: "",
							},
							{ label: __("Left", "rox-appointment-booking"), value: "left" },
							{
								label: __("Center", "rox-appointment-booking"),
								value: "center",
							},
							{ label: __("Right", "rox-appointment-booking"), value: "right" },
						]}
						onChange={(value) =>
							setAttributes({ headingAlign: value || "" })
						}
						help={__(
							"The step title above each list — Select Location, Available Category, and so on.",
							"rox-appointment-booking",
						)}
						__nextHasNoMarginBottom
					/>

					{/* The content control below moves the whole column, heading
					    included; this moves the heading alone, which is what sets it
					    apart from the cards under it. Margin rather than padding: the
					    heading has no surface of its own for padding to show on, and a
					    negative value is what pulls it back out of the column. */}
					{BoxControl && (
						<BoxControl
							label={__("Heading margin", "rox-appointment-booking")}
							values={attributes.headingMargin || {}}
							onChange={(value) =>
								setAttributes({ headingMargin: value || {} })
							}
							units={SPACING_UNITS}
							allowReset
							__next40pxDefaultSize
						/>
					)}

					{/* Only once a column is gone: with both in place the panel is
					    full and there is nothing left over to nudge into. The content
					    is centred in that room already; this shifts it from there. */}
					{BoxControl && (hideNavigation || hideInfo) && (
						<BoxControl
							label={__("Content margin", "rox-appointment-booking")}
							values={attributes.contentMargin || {}}
							onChange={(value) =>
								setAttributes({ contentMargin: value || {} })
							}
							units={SPACING_UNITS}
							allowReset
							__next40pxDefaultSize
							help={__(
								"Nudges the step heading and its cards, which sit centred in the room the hidden column freed up. Negative values pull the other way.",
								"rox-appointment-booking",
							)}
						/>
					)}
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
							value={idsToTokens(attributes.locationIds, locationOptions)}
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
						value={idsToTokens(attributes.categoryIds, categoryOptions)}
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
							"Recolours every accent surface at once — the Next button, the active step markers, selected cards and time slots, links and focus rings. The Back and Next button panels override this for those two buttons. Leave empty to keep the panel default.",
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
						checked={framed}
						onChange={(value) => setAttributes({ showBackground: value })}
					/>
					{framed && (
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
					title={
						navLabels.back || __("Back button", "rox-appointment-booking")
					}
					initialOpen={false}
				>
					<NavButtonControls
						prefix="navBack"
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</PanelBody>
				<PanelBody
					title={
						navLabels.next || __("Next button", "rox-appointment-booking")
					}
					initialOpen={false}
				>
					<NavButtonControls
						prefix="navNext"
						attributes={attributes}
						setAttributes={setAttributes}
					/>
				</PanelBody>
				<PanelBody
					title={__("Go to Dashboard button", "rox-appointment-booking")}
					initialOpen={false}
				>
					{/* The confirmation screen's button. Off for a public panel
					    whose visitors have no account to reach; when on, its
					    label and target are the editor's. */}
					<ToggleControl
						label={__("Show button", "rox-appointment-booking")}
						checked={attributes.showDashboardButton !== false}
						onChange={(value) =>
							setAttributes({ showDashboardButton: value })
						}
						help={__(
							"On the booking confirmation screen. Turn off for a public panel where visitors have no account.",
							"rox-appointment-booking",
						)}
						__nextHasNoMarginBottom
					/>
					{attributes.showDashboardButton !== false && (
						<>
							<TextControl
								label={__("Button text", "rox-appointment-booking")}
								value={attributes.dashboardButtonText || ""}
								placeholder={__(
									"Go to Dashboard",
									"rox-appointment-booking",
								)}
								onChange={(value) =>
									setAttributes({ dashboardButtonText: value })
								}
								__nextHasNoMarginBottom
							/>
							<TextControl
								label={__("Button link", "rox-appointment-booking")}
								value={attributes.dashboardButtonUrl || ""}
								placeholder={__(
									"Customer dashboard page",
									"rox-appointment-booking",
								)}
								onChange={(value) =>
									setAttributes({ dashboardButtonUrl: value })
								}
								help={__(
									"Leave empty to use the plugin's customer dashboard page.",
									"rox-appointment-booking",
								)}
								__nextHasNoMarginBottom
							/>
						</>
					)}
				</PanelBody>
			</InspectorControls>
		</>
	);
};

export default Controls;
