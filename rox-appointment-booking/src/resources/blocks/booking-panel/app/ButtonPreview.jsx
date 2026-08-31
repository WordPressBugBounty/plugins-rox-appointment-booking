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

const ICONS = {
	calendar: (
		<>
			<rect x="3" y="4.5" width="14" height="12.5" rx="2" />
			<path d="M3 8.5h14M7 2.5v3M13 2.5v3" />
		</>
	),
	clock: (
		<>
			<circle cx="10" cy="10" r="7.25" />
			<path d="M10 5.75V10l2.75 1.75" />
		</>
	),
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

	// `link` is a text button — there is no box to draw around it.
	if (style !== "link") {
		css.borderStyle = borderStyle || "solid";
		css.borderWidth = `${Math.max(0, Math.min(20, borderWidth || 0))}px`;
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
				{ICONS[icon] && (
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
						>
							{ICONS[icon]}
						</svg>
					</span>
				)}
				<span className="rox-booking-button__label">{text}</span>
			</button>
		</div>
	);
};

export default ButtonPreview;
