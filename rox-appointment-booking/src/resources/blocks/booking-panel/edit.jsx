import {
	useBlockProps,
	InspectorControls,
	BlockControls,
	AlignmentToolbar,
	ColorPalette,
	PanelColorSettings,
} from "@wordpress/block-editor";
import * as components from "@wordpress/components";
import { useEffect, useRef, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";

import SelectionSidebar from "../../components/BookingService/SelectionSidebar.jsx";
import CategoryCards from "../../components/BookingService/CategoryCards.jsx";
import { ensureWebFont, googleFontHref } from "../../lib/webFont.js";
import NavButtonControls from "../shared/NavButtonControls.jsx";
import NavButtonsPreview from "../shared/NavButtonsPreview.jsx";
import { navButtonVars } from "../../lib/navButtonVars.js";

import ButtonPreview, { ButtonIcon } from "./app/ButtonPreview.jsx";
import iconSet from "./icons.json";
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


// Devices the block can style separately. The suffix is appended to the base
// attribute name (`padding` -> `paddingTablet`), matching what block.json
// declares and what Supports\BookingButtonMarkup reads.
//
// Breakpoints live in the stylesheet: tablet is ≤1024px, mobile ≤767px. An
// attribute left empty on a device inherits the device above it, so an editor
// only fills in what actually differs.
const DEVICES = [
	{ value: "desktop", suffix: "" },
	{ value: "tablet", suffix: "Tablet" },
	{ value: "mobile", suffix: "Mobile" },
];

const deviceLabelFor = (device) =>
	({
		desktop: __("Desktop", "rox-appointment-booking"),
		tablet: __("Tablet", "rox-appointment-booking"),
		mobile: __("Mobile", "rox-appointment-booking"),
	})[device] || device;

const deviceSuffix = (device) =>
	DEVICES.find((item) => item.value === device)?.suffix ?? "";

/**
 * The value in force on a device: its own, or the nearest one set above it.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} base       Base attribute name.
 * @param {string} device     Active device.
 * @return {*} Resolved value.
 */
const resolved = (attributes, base, device) => {
	const chain =
		device === "mobile"
			? ["Mobile", "Tablet", ""]
			: device === "tablet"
				? ["Tablet", ""]
				: [""];

	for (const suffix of chain) {
		const value = attributes[base + suffix];
		if (value !== "" && value !== null && value !== undefined) {
			return value;
		}
	}

	return undefined;
};

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
/**
 * The five parts of a CSS shadow, as one control group.
 *
 * Stored as a single object attribute rather than five, because that is what
 * it is — one shadow — and it keeps the block's attribute list readable.
 *
 * @param {Object}   props          Component props.
 * @param {string}   props.label    Group heading.
 * @param {Object}   props.value    Current shadow.
 * @param {Function} props.onChange Receives the next shadow.
 * @return {JSX.Element} The control group.
 */
const ShadowControl = ({ label, value, onChange }) => {
	const shadow = value || {};
	const set = (key) => (next) => onChange({ ...shadow, [key]: next });

	return (
		<div className="rox-booking-button-shadow">
			<BaseControl.VisualLabel>{label}</BaseControl.VisualLabel>
			<ColorPalette
				value={shadow.color || undefined}
				onChange={(color) => onChange({ ...shadow, color: color || "" })}
				clearable
			/>
			{[
				["x", __("Horizontal", "rox-appointment-booking"), -100, 100],
				["y", __("Vertical", "rox-appointment-booking"), -100, 100],
				["blur", __("Blur", "rox-appointment-booking"), 0, 100],
				["spread", __("Spread", "rox-appointment-booking"), -100, 100],
			].map(([key, title, min, max]) => (
				<RangeControl
					key={key}
					label={title}
					value={shadow[key] ?? 0}
					onChange={(next) => set(key)(next ?? 0)}
					min={min}
					max={max}
					// Nothing is drawn until a colour is picked, so the
					// distances would be adjusting an invisible shadow.
					disabled={!shadow.color}
					__nextHasNoMarginBottom
				/>
			))}
			<ToggleControl
				label={__("Inset", "rox-appointment-booking")}
				checked={!!shadow.inset}
				onChange={set("inset")}
				disabled={!shadow.color}
				__nextHasNoMarginBottom
			/>
		</div>
	);
};

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

	// Which device the responsive button controls are editing. Editor-only:
	// nothing about it is saved, it just decides which attribute a control
	// reads and writes.
	const [device, setDevice] = useState("desktop");
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
						align={deviceAlign}
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
						padding={resolved(attributes, "buttonPadding", device) || {}}
						margin={resolved(attributes, "buttonMargin", device) || {}}
						borderWidth={resolved(attributes, "buttonBorderWidth", device) ?? 1}
						borderRadius={resolved(attributes, "buttonBorderRadius", device) ?? 6}
						borderWidthHover={attributes.buttonBorderWidthHover}
						buttonWidthSize={resolved(attributes, "buttonWidthSize", device)}
						iconSize={resolved(attributes, "buttonIconSize", device)}
						iconGap={resolved(attributes, "buttonIconGap", device)}
						iconOffsetY={resolved(attributes, "buttonIconOffsetY", device)}
						boxShadow={attributes.buttonBoxShadow}
						boxShadowHover={attributes.buttonBoxShadowHover}
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
