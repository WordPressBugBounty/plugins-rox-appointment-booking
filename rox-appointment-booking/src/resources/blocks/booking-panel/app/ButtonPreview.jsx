/**
 * React mirror of Supports\BookingButtonMarkup::render().
 *
 * The published button is server-rendered by PHP; this component exists so the
 * Gutenberg editor can show the same thing live while an editor drags the
 * controls around. Class names, variants and the inline-style rules are kept
 * deliberately in lockstep with the PHP builder — change one, change both.
 *
 * Colours are emitted as the same CSS custom properties the stylesheet reads,
 * which is what makes hover colours previewable here at all.
 */

import icons from "../icons.json";

/**
 * Draws one icon from the set.
 *
 * The set is the same JSON file Supports\BookingButtonMarkup reads, so the
 * editor can never offer an icon the published page cannot draw. The markup is
 * the plugin's own, not stored settings, which is why it can be injected.
 *
 * @param {Object} props      Component props.
 * @param {string} props.name Icon key.
 * @return {JSX.Element|null} The icon, or null when there is none to draw.
 */
export const ButtonIcon = ({ name }) => {
	if (!name || name === "none" || !icons[name]) {
		return null;
	}

	return (
		<span className="rox-booking-button__icon" aria-hidden="true">
			<svg
				width="20"
				height="20"
				viewBox="0 0 20 20"
				fill="none"
				stroke="currentColor"
				strokeWidth="1.5"
				strokeLinecap="round"
				strokeLinejoin="round"
				dangerouslySetInnerHTML={{ __html: icons[name] }}
			/>
		</span>
	);
};

const SIDES = ["top", "right", "bottom", "left"];

/**
 * Copies the sides a BoxControl actually set onto a style object, as the
 * longhand properties PHP writes. Sides left empty keep the size class's value.
 *
 * @param {Object} css      Style object to write into.
 * @param {string} property "padding" or "margin".
 * @param {Object} box      BoxControl value.
 * @return {void}
 */
const applySpacing = (css, property, box) => {
	if (!box) {
		return;
	}

	SIDES.forEach((side) => {
		const value = box[side];
		if (!value && value !== 0) {
			return;
		}

		// A unitless number is read as pixels, matching
		// BookingButtonMarkup::length(). React would otherwise emit
		// `padding-top: 10`, which is invalid CSS and silently ignored — the
		// spacing control would look broken.
		const length = /^-?\d+(\.\d+)?$/.test(String(value).trim())
			? `${value}px`
			: value;

		// paddingTop / marginRight …
		css[`${property}${side[0].toUpperCase()}${side.slice(1)}`] = length;
	});
};

/**
 * Renders a `{x, y, blur, spread, color, inset}` map as a CSS shadow.
 * Mirrors BookingButtonMarkup::shadow().
 *
 * @param {Object} value Shadow settings.
 * @return {string} Empty when there is no shadow to draw.
 */
const shadowValue = (value) => {
	// Without a colour there is nothing to draw — the browser would fall back
	// to the current text colour, which an untouched control never means.
	if (!value || !value.color) {
		return "";
	}

	// Blur is the one length that cannot be negative.
	const lengths = ["x", "y", "blur", "spread"].map((key) => {
		const raw = Number(value[key]) || 0;
		const floor = key === "blur" ? 0 : -200;

		return `${Math.max(floor, Math.min(200, raw))}px`;
	});

	return `${value.inset ? "inset " : ""}${lengths.join(" ")} ${value.color}`;
};

/**
 * Builds the button's inline style. Mirrors BookingButtonMarkup::buttonStyle().
 *
 * @param {Object} attrs Block attributes.
 * @return {Object} React style object.
 */
const buttonStyle = ({
	style,
	backgroundColor,
	textColor,
	borderColor,
	backgroundColorHover,
	textColorHover,
	borderColorHover,
	borderWidth,
	borderStyle,
	borderRadius,
	borderWidthHover,
	buttonWidthSize,
	iconSize,
	iconGap,
	iconOffsetY,
	boxShadow,
	boxShadowHover,
	padding,
	margin,
}) => {
	const css = {};

	// `backgroundColor` is the surface's primary colour, but what it paints
	// depends on the variant: a fill for `filled`, and the accent (label, and
	// through it the border) for `outline` / `link`, which have no fill.
	const colors =
		style === "filled"
			? {
					"--rox-btn-bg": backgroundColor,
					"--rox-btn-fg": textColor,
					"--rox-btn-bg-hover": backgroundColorHover,
					"--rox-btn-fg-hover": textColorHover,
				}
			: {
					"--rox-btn-fg": backgroundColor,
					"--rox-btn-fg-hover": backgroundColorHover,
				};

	// `link` draws no box, so a border colour would have nothing to paint.
	if (style !== "link") {
		colors["--rox-btn-border"] = borderColor;
		colors["--rox-btn-border-hover"] = borderColorHover;
	}

	let hasHover = false;

	Object.entries(colors).forEach(([property, value]) => {
		if (value) {
			css[property] = value;
			if (property.endsWith("-hover")) {
				hasHover = true;
			}
		}
	});

	// A hover colour that actually applies replaces the stylesheet's brightness
	// shift, which would otherwise tint that colour on top of itself.
	if (hasHover) {
		css["--rox-btn-hover-filter"] = "none";
	}

	applySpacing(css, "padding", padding);
	applySpacing(css, "margin", margin);

	// Width, icon size, icon gap and the icon's optical nudge, as the same
	// variables PHP writes. Only the nudge may be negative.
	const metrics = {
		"--rox-btn-w": [buttonWidthSize, 2000, false],
		"--rox-btn-icon": [iconSize, 200, false],
		"--rox-btn-gap": [iconGap, 200, false],
		"--rox-btn-icon-y": [iconOffsetY, 100, true],
	};

	Object.entries(metrics).forEach(([property, [value, max, signed]]) => {
		if (value === null || value === undefined || value === "") {
			return;
		}

		const floor = signed ? -max : 0;
		css[property] = `${Math.max(floor, Math.min(max, value))}px`;
	});

	const shadow = shadowValue(boxShadow);
	const shadowHover = shadowValue(boxShadowHover);

	if (shadow) {
		css["--rox-btn-shadow"] = shadow;
	}

	if (shadowHover) {
		css["--rox-btn-shadow-hover"] = shadowHover;
	}

	// `link` is a text button — there is no box to draw around it.
	if (style !== "link") {
		css.borderStyle = borderStyle || "solid";
		css.borderWidth = `${Math.max(0, Math.min(20, borderWidth || 0))}px`;

		if (borderWidthHover !== null && borderWidthHover !== undefined) {
			css["--rox-btn-bw-hover"] =
				`${Math.max(0, Math.min(20, borderWidthHover))}px`;
		}
		css.borderRadius = `${Math.max(0, Math.min(100, borderRadius || 0))}px`;
	}

	return css;
};

const ButtonPreview = (props) => {
	const {
		text,
		align = "left",
		width = "auto",
		size = "medium",
		style = "filled",
		icon = "calendar",
	} = props;

	// Blocks saved before width and alignment were separate controls carry the
	// width in the alignment value.
	const isLegacyFull = align === "full";
	const resolvedAlign = isLegacyFull ? "left" : align;
	const resolvedWidth = isLegacyFull ? "full" : width;

	const classes = [
		"rox-booking-button",
		`rox-booking-button--${style}`,
		`rox-booking-button--${size}`,
		resolvedWidth === "full" ? "rox-booking-button--full" : "",
	]
		.filter(Boolean)
		.join(" ");

	return (
		<div
			className="rox-booking-button-wrap"
			style={{ textAlign: resolvedAlign }}
		>
			<button
				type="button"
				className={classes}
				style={buttonStyle(props)}
				// The editor canvas is a preview, never a live trigger — but it is
				// still hovered, which is the only way an editor can see what the
				// hover colours do. `disabled` would block that: the stylesheet's
				// hover rule skips disabled buttons, so the controls would look
				// dead. Inert without being disabled: the click is swallowed and
				// the button is out of the tab order.
				aria-disabled="true"
				tabIndex={-1}
				onClick={(event) => event.preventDefault()}
			>
				<ButtonIcon name={icon} />
				<span className="rox-booking-button__label">{text}</span>
			</button>
		</div>
	);
};

export default ButtonPreview;
