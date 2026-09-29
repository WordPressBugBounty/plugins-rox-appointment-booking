/**
 * The canvas preview for popup mode, whichever style the block is set to.
 *
 * A published page in this mode shows nothing but the trigger until a visitor
 * clicks it — the panel is built in the modal on that first click — so the
 * editor shows the trigger alone, drawn by the real button stylesheet rather
 * than an approximation of it.
 */

import { __ } from "@wordpress/i18n";

import ButtonPreview from "./ButtonPreview.jsx";
import { resolved } from "./helpers.jsx";

// The trigger's own styles, so the preview looks exactly like the published
// button.
import "../booking-button.scss";

const TriggerPreview = ({ attributes, device }) => {
	const {
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
	} = attributes;

	// Blocks saved before width and alignment were separate controls carry the
	// width in the alignment value, so read the button's box through these two.
	const isLegacyFull = buttonAlign === "full";
	const align = isLegacyFull ? "left" : buttonAlign || "left";
	const width = isLegacyFull ? "full" : buttonWidth || "auto";
	const deviceAlign =
		device === "desktop"
			? align
			: resolved(attributes, "buttonAlign", device) || align;

	return (
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
	);
};

export default TriggerPreview;
