import { __ } from "@wordpress/i18n";
import {
	useBlockProps,
	InspectorControls,
	PanelColorSettings,
} from "@wordpress/block-editor";
import {
	PanelBody,
	TextControl,
	SelectControl,
	ToggleControl,
	BoxControl as StableBoxControl,
	__experimentalBoxControl as ExperimentalBoxControl,
} from "@wordpress/components";

// `BoxControl` was stabilized in newer WP; fall back to the experimental alias
// on older versions so the editor never crashes on an undefined component.
const BoxControl = StableBoxControl || ExperimentalBoxControl;

// The block's own frontend styles, so the editor preview matches the real form.
import "./app/login-form.scss";

const boxToShorthand = (box) => {
	if (!box || typeof box !== "object") return "";
	const sides = ["top", "right", "bottom", "left"];
	if (!sides.some((s) => box[s])) return "";
	return sides.map((s) => box[s] || "0").join(" ");
};

const ALIGN_TO_JUSTIFY = {
	left: "flex-start",
	center: "center",
	right: "flex-end",
};

/**
 * Builds the `--rlf-*` custom properties from the block attributes.
 *
 * Mirrors `LoginFormBlock::buildCssVars()` on the PHP side — the editor applies
 * them inline for the preview, PHP prints the same set on the frontend. Only set
 * attributes emit a var, so unset controls fall through to the SCSS defaults and
 * keep the booking-panel look.
 *
 * @param {object} attributes Block attributes.
 * @return {object} React inline-style object.
 */
export const buildCssVars = (attributes) => {
	const vars = {};

	const colorMap = {
		"--rlf-bg": "bgColor",
		"--rlf-border-color": "borderColor",
		"--rlf-label": "labelColor",
		"--rlf-input-color": "inputColor",
		"--rlf-input-bg": "inputBg",
		"--rlf-input-border": "inputBorder",
		"--rlf-input-focus-border": "inputFocusBorder",
		"--rlf-btn-bg": "btnBg",
		"--rlf-btn-color": "btnColor",
		"--rlf-btn-hover-bg": "btnHoverBg",
		"--rlf-btn-hover-color": "btnHoverColor",
		"--rlf-link": "linkColor",
		"--rlf-link-hover": "linkHoverColor",
		"--rlf-error-color": "errorColor",
		"--rlf-error-bg": "errorBg",
		"--rlf-success-color": "successColor",
		"--rlf-success-bg": "successBg",
	};

	const lengthMap = {
		"--rlf-width": "formWidth",
		"--rlf-border-width": "borderWidth",
		"--rlf-border-style": "borderStyle",
		"--rlf-radius": "borderRadius",
		"--rlf-input-radius": "inputRadius",
		"--rlf-btn-radius": "btnRadius",
	};

	const boxMap = {
		"--rlf-margin": "formMargin",
		"--rlf-padding": "formPadding",
		"--rlf-btn-margin": "btnMargin",
		"--rlf-btn-padding": "btnPadding",
	};

	Object.entries({ ...colorMap, ...lengthMap }).forEach(([cssVar, key]) => {
		if (attributes[key]) vars[cssVar] = attributes[key];
	});

	Object.entries(boxMap).forEach(([cssVar, key]) => {
		const shorthand = boxToShorthand(attributes[key]);
		if (shorthand) vars[cssVar] = shorthand;
	});

	return vars;
};

/**
 * Editor preview: a static, non-interactive copy of the frontend `login` state,
 * reflecting every style control live.
 *
 * Deliberately not the real `LoginFormApp` — that would mount a live form (and
 * hit the auth endpoints) inside the editor. The markup mirrors
 * `app/LoginFormApp.jsx`'s login view and is wrapped in the frontend root class
 * so `login-form.scss` applies unchanged.
 *
 * @param {object} props
 * @return {React.ReactElement}
 */
const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();
	const {
		redirectUrl,
		loginLabel,
		formAlign,
		formWidth,
		formMargin,
		formPadding,
		bgColor,
		borderWidth,
		borderStyle,
		borderColor,
		borderRadius,
		labelColor,
		inputColor,
		inputBg,
		inputBorder,
		inputFocusBorder,
		inputRadius,
		btnBg,
		btnColor,
		btnHoverBg,
		btnHoverColor,
		btnRadius,
		btnMargin,
		btnPadding,
		linkColor,
		linkHoverColor,
		showGoogle,
		errorColor,
		errorBg,
		successColor,
		successBg,
	} = attributes;

	const previewVars = buildCssVars(attributes);
	const set = (key) => (value) => setAttributes({ [key]: value || "" });
	const setBox = (key) => (value) => setAttributes({ [key]: value || {} });

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Content", "rox-appointment-booking")} initialOpen={true}>
					<TextControl
						label={__("Redirect after login (URL)", "rox-appointment-booking")}
						help={__(
							"Leave empty to send the user to the WordPress admin.",
							"rox-appointment-booking",
						)}
						value={redirectUrl}
						onChange={set("redirectUrl")}
					/>
					<TextControl
						label={__("Login button label", "rox-appointment-booking")}
						value={loginLabel}
						onChange={(value) => setAttributes({ loginLabel: value })}
					/>
					<ToggleControl
						label={__("Show \"Sign in with Google\"", "rox-appointment-booking")}
						help={__(
							"Only has an effect when Google login is enabled in the Pro integration settings.",
							"rox-appointment-booking",
						)}
						checked={showGoogle !== false}
						onChange={(value) => setAttributes({ showGoogle: value })}
					/>
				</PanelBody>

				<PanelBody title={__("Container", "rox-appointment-booking")} initialOpen={false}>
					<TextControl
						label={__("Width", "rox-appointment-booking")}
						help={__("Any CSS length, e.g. 372px or 100%.", "rox-appointment-booking")}
						placeholder="372px"
						value={formWidth}
						onChange={set("formWidth")}
					/>
					<SelectControl
						label={__("Alignment", "rox-appointment-booking")}
						value={formAlign || "left"}
						options={[
							{ label: __("Left", "rox-appointment-booking"), value: "left" },
							{ label: __("Center", "rox-appointment-booking"), value: "center" },
							{ label: __("Right", "rox-appointment-booking"), value: "right" },
						]}
						onChange={(value) => setAttributes({ formAlign: value })}
					/>
					<BoxControl
						label={__("Margin", "rox-appointment-booking")}
						values={formMargin}
						onChange={setBox("formMargin")}
					/>
					<BoxControl
						label={__("Padding", "rox-appointment-booking")}
						values={formPadding}
						onChange={setBox("formPadding")}
					/>
					<TextControl
						label={__("Border width", "rox-appointment-booking")}
						placeholder="0"
						value={borderWidth}
						onChange={set("borderWidth")}
					/>
					<SelectControl
						label={__("Border style", "rox-appointment-booking")}
						value={borderStyle || "solid"}
						options={[
							{ label: __("Solid", "rox-appointment-booking"), value: "solid" },
							{ label: __("Dashed", "rox-appointment-booking"), value: "dashed" },
							{ label: __("Dotted", "rox-appointment-booking"), value: "dotted" },
							{ label: __("None", "rox-appointment-booking"), value: "none" },
						]}
						onChange={(value) => setAttributes({ borderStyle: value })}
					/>
					<TextControl
						label={__("Border radius", "rox-appointment-booking")}
						placeholder="0"
						value={borderRadius}
						onChange={set("borderRadius")}
					/>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: bgColor,
								onChange: set("bgColor"),
								label: __("Background", "rox-appointment-booking"),
							},
							{
								value: borderColor,
								onChange: set("borderColor"),
								label: __("Border", "rox-appointment-booking"),
							},
						]}
					/>
				</PanelBody>

				<PanelBody title={__("Fields", "rox-appointment-booking")} initialOpen={false}>
					<TextControl
						label={__("Input border radius", "rox-appointment-booking")}
						placeholder="6px"
						value={inputRadius}
						onChange={set("inputRadius")}
					/>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: labelColor,
								onChange: set("labelColor"),
								label: __("Label", "rox-appointment-booking"),
							},
							{
								value: inputColor,
								onChange: set("inputColor"),
								label: __("Input text", "rox-appointment-booking"),
							},
							{
								value: inputBg,
								onChange: set("inputBg"),
								label: __("Input background", "rox-appointment-booking"),
							},
							{
								value: inputBorder,
								onChange: set("inputBorder"),
								label: __("Input border", "rox-appointment-booking"),
							},
							{
								value: inputFocusBorder,
								onChange: set("inputFocusBorder"),
								label: __("Input focus border", "rox-appointment-booking"),
							},
						]}
					/>
				</PanelBody>

				<PanelBody title={__("Login button", "rox-appointment-booking")} initialOpen={false}>
					<TextControl
						label={__("Border radius", "rox-appointment-booking")}
						placeholder="6px"
						value={btnRadius}
						onChange={set("btnRadius")}
					/>
					<BoxControl
						label={__("Margin", "rox-appointment-booking")}
						values={btnMargin}
						onChange={setBox("btnMargin")}
					/>
					<BoxControl
						label={__("Padding", "rox-appointment-booking")}
						values={btnPadding}
						onChange={setBox("btnPadding")}
					/>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: btnBg,
								onChange: set("btnBg"),
								label: __("Background", "rox-appointment-booking"),
							},
							{
								value: btnColor,
								onChange: set("btnColor"),
								label: __("Text", "rox-appointment-booking"),
							},
							{
								value: btnHoverBg,
								onChange: set("btnHoverBg"),
								label: __("Hover background", "rox-appointment-booking"),
							},
							{
								value: btnHoverColor,
								onChange: set("btnHoverColor"),
								label: __("Hover text", "rox-appointment-booking"),
							},
						]}
					/>
				</PanelBody>

				<PanelBody title={__("Links", "rox-appointment-booking")} initialOpen={false}>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: linkColor,
								onChange: set("linkColor"),
								label: __("Forgot password link", "rox-appointment-booking"),
							},
							{
								value: linkHoverColor,
								onChange: set("linkHoverColor"),
								label: __("Hover", "rox-appointment-booking"),
							},
						]}
					/>
				</PanelBody>

				<PanelBody title={__("Messages", "rox-appointment-booking")} initialOpen={false}>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: errorColor,
								onChange: set("errorColor"),
								label: __("Error text", "rox-appointment-booking"),
							},
							{
								value: errorBg,
								onChange: set("errorBg"),
								label: __("Error background", "rox-appointment-booking"),
							},
							{
								value: successColor,
								onChange: set("successColor"),
								label: __("Success text", "rox-appointment-booking"),
							},
							{
								value: successBg,
								onChange: set("successBg"),
								label: __("Success background", "rox-appointment-booking"),
							},
						]}
					/>
				</PanelBody>
			</InspectorControls>

			<div
				{...blockProps}
				style={{
					...blockProps.style,
					display: "flex",
					justifyContent: ALIGN_TO_JUSTIFY[formAlign] || "flex-start",
				}}
			>
				<div
					className="rox-appointment-booking-login-form-root rlf-editor-preview"
					style={previewVars}
				>
					<div className="form-wrapper">
						<div className="form-fields">
							{/* Both message boxes are shown in the editor only, so their
							    color controls are previewable — the frontend renders them
							    on demand. */}
							<div className="rlf-message rlf-message-error">
								{__("Invalid email or password", "rox-appointment-booking")}
							</div>
							<div className="rlf-message rlf-message-success">
								{__(
									"Your password has been updated. Please log in.",
									"rox-appointment-booking",
								)}
							</div>
							<div className="form-group">
								<label>{__("Email", "rox-appointment-booking")}</label>
								<input type="email" placeholder={__("Enter email", "rox-appointment-booking")} readOnly />
							</div>
							<div className="form-group">
								<label>{__("Password", "rox-appointment-booking")}</label>
								<input type="password" placeholder={__("Enter password", "rox-appointment-booking")} readOnly />
							</div>
						</div>
						<div className="footer-btn-container">
							<div className="primary-submit-btn">
								{loginLabel || __("Login", "rox-appointment-booking")}
							</div>
							<div className="forgot-password-container">
								<div className="forgot-password-link">
									{__("Forgot password?", "rox-appointment-booking")}
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</>
	);
};

export default Edit;
