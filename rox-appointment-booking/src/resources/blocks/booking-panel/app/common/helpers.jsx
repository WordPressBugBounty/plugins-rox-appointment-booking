/**
 * Pieces shared by Style 1's controls and its editor preview.
 *
 * The responsive helpers live here rather than in either file because both need
 * them: the controls to decide which attribute a field writes to, the preview to
 * decide which value is actually in force on the device being edited.
 */

import * as components from "@wordpress/components";
import { ColorPalette } from "@wordpress/block-editor";
import { __ } from "@wordpress/i18n";

const { BaseControl, RangeControl, ToggleControl } = components;

// BoxControl only became a stable export in a recent WordPress; the plugin
// supports 6.5, where it is still the experimental name. Picking at runtime
// keeps both alive without a version check. ToggleGroupControl has no stable
// export at all yet, so it falls back to a plain select.
export const BoxControl =
	components.BoxControl || components.__experimentalBoxControl;
export const ToggleGroupControl =
	components.ToggleGroupControl || components.__experimentalToggleGroupControl;
export const ToggleGroupControlOption =
	components.ToggleGroupControlOption ||
	components.__experimentalToggleGroupControlOption;

export const ALIGNMENTS = ["left", "center", "right"];

// Devices the block can style separately. The suffix is appended to the base
// attribute name (`padding` -> `paddingTablet`), matching what block.json
// declares and what Supports\BookingButtonMarkup reads.
//
// Breakpoints live in the stylesheet: tablet is ≤1024px, mobile ≤767px. An
// attribute left empty on a device inherits the device above it, so an editor
// only fills in what actually differs.
export const DEVICES = [
	{ value: "desktop", suffix: "" },
	{ value: "tablet", suffix: "Tablet" },
	{ value: "mobile", suffix: "Mobile" },
];

export const deviceLabelFor = (device) =>
	({
		desktop: __("Desktop", "rox-appointment-booking"),
		tablet: __("Tablet", "rox-appointment-booking"),
		mobile: __("Mobile", "rox-appointment-booking"),
	})[device] || device;

export const deviceSuffix = (device) =>
	DEVICES.find((item) => item.value === device)?.suffix ?? "";

/**
 * The value in force on a device: its own, or the nearest one set above it.
 *
 * @param {Object} attributes Block attributes.
 * @param {string} base       Base attribute name.
 * @param {string} device     Active device.
 * @return {*} Resolved value.
 */
export const resolved = (attributes, base, device) => {
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

export const SPACING_UNITS = [
	{ value: "px", label: "px", default: 0 },
	{ value: "em", label: "em", default: 0 },
	{ value: "rem", label: "rem", default: 0 },
	{ value: "%", label: "%", default: 0 },
];

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
export const ShadowControl = ({ label, value, onChange }) => {
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
