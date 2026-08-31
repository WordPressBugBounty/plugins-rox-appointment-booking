import { PanelColorSettings } from "@wordpress/block-editor";
import * as components from "@wordpress/components";
import { __ } from "@wordpress/i18n";

import "./nav-button-controls.scss";

const { SelectControl, RangeControl, Notice, TabPanel } = components;

// BoxControl only became a stable export in a recent WordPress; the plugin
// supports 6.5, where it is still the experimental name. Picking at runtime
// keeps both alive without a version check.
const BoxControl = components.BoxControl || components.__experimentalBoxControl;

const SPACING_UNITS = [
	{ value: "px", label: "px", default: 0 },
	{ value: "em", label: "em", default: 0 },
	{ value: "rem", label: "rem", default: 0 },
	{ value: "%", label: "%", default: 0 },
];

const FONT_WEIGHTS = ["300", "400", "500", "600", "700", "800"];

/**
 * Colour / spacing / border / text controls for one of the booking panel's
 * navigation buttons.
 *
 * Every control writes an attribute named `<prefix><Setting>` and leaves it
 * empty when unset, which is what makes Supports\NavButtons skip the variable
 * and the panel stylesheet keep its own value.
 *
 * @param {Object}   props
 * @param {string}   props.prefix        Attribute prefix, e.g. "navNext".
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Block attribute setter.
 * @return {JSX.Element} The controls.
 */
const NavButtonControls = ({ prefix, attributes, setAttributes }) => {
	const get = (name) => attributes[prefix + name];
	const set = (name, value) => setAttributes({ [prefix + name]: value });

	// Clearing a colour hands back `undefined`; store "" so the attribute keeps
	// its declared string type.
	const colorSettings = (state) => [
		{
			value: get(`BgColor${state}`) || undefined,
			onChange: (value) => set(`BgColor${state}`, value || ""),
			label: __("Background", "rox-appointment-booking"),
		},
		{
			value: get(`TextColor${state}`) || undefined,
			onChange: (value) => set(`TextColor${state}`, value || ""),
			label: __("Text", "rox-appointment-booking"),
		},
		{
			value: get(`BorderColor${state}`) || undefined,
			onChange: (value) => set(`BorderColor${state}`, value || ""),
			label: __("Border", "rox-appointment-booking"),
		},
	];

	return (
		<>
			<TabPanel
				className="rox-nav-button-color-tabs"
				tabs={[
					{ name: "normal", title: __("Normal", "rox-appointment-booking") },
					{ name: "hover", title: __("Hover", "rox-appointment-booking") },
				]}
			>
				{(tab) => (
					<PanelColorSettings
						// Rendered inside a PanelBody already, so it must not draw a
						// second collapsible frame of its own.
						className="rox-nav-button-colors"
						title=""
						initialOpen
						colorSettings={colorSettings(tab.name === "hover" ? "Hover" : "")}
					/>
				)}
			</TabPanel>
			<p className="rox-nav-button-colors__help">
				{__(
					"Leave a color empty to keep the panel's default.",
					"rox-appointment-booking",
				)}
			</p>

			{BoxControl ? (
				<>
					<BoxControl
						label={__("Padding", "rox-appointment-booking")}
						values={get("Padding") || {}}
						onChange={(value) => set("Padding", value || {})}
						units={SPACING_UNITS}
						__next40pxDefaultSize
					/>
					<BoxControl
						label={__("Margin", "rox-appointment-booking")}
						values={get("Margin") || {}}
						onChange={(value) => set("Margin", value || {})}
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

			<SelectControl
				label={__("Border style", "rox-appointment-booking")}
				value={get("BorderStyle") || ""}
				options={[
					{ label: __("Default", "rox-appointment-booking"), value: "" },
					{ label: __("None", "rox-appointment-booking"), value: "none" },
					{ label: __("Solid", "rox-appointment-booking"), value: "solid" },
					{ label: __("Dashed", "rox-appointment-booking"), value: "dashed" },
					{ label: __("Dotted", "rox-appointment-booking"), value: "dotted" },
					{ label: __("Double", "rox-appointment-booking"), value: "double" },
				]}
				onChange={(value) => set("BorderStyle", value)}
				__nextHasNoMarginBottom
			/>
			<RangeControl
				label={__("Border width", "rox-appointment-booking")}
				value={get("BorderWidth") ?? undefined}
				// Resetting hands back undefined; store null so the attribute keeps
				// its declared type and the variable stays unset.
				onChange={(value) => set("BorderWidth", value ?? null)}
				min={0}
				max={20}
				allowReset
				__nextHasNoMarginBottom
			/>
			<RangeControl
				label={__("Corner radius", "rox-appointment-booking")}
				value={get("BorderRadius") ?? undefined}
				onChange={(value) => set("BorderRadius", value ?? null)}
				min={0}
				max={100}
				allowReset
				__nextHasNoMarginBottom
			/>
			<RangeControl
				label={__("Font size (px)", "rox-appointment-booking")}
				value={get("FontSize") ?? undefined}
				onChange={(value) => set("FontSize", value ?? null)}
				min={10}
				max={40}
				allowReset
				__nextHasNoMarginBottom
			/>
			<SelectControl
				label={__("Font weight", "rox-appointment-booking")}
				value={get("FontWeight") || ""}
				options={[
					{ label: __("Default", "rox-appointment-booking"), value: "" },
					...FONT_WEIGHTS.map((weight) => ({ label: weight, value: weight })),
				]}
				onChange={(value) => set("FontWeight", value)}
				__nextHasNoMarginBottom
			/>
		</>
	);
};

export default NavButtonControls;
