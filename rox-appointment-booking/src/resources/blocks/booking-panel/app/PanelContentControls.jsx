/**
 * The block's "Panel Content" controls.
 *
 * The booking panel's wording lives in its React components. This is where an
 * editor rewrites it: the first step's sidebar, the heading above each step's
 * cards, and the step list down the left from the second step onward.
 *
 * Every field is an override. Left empty it stays empty in the attribute, and
 * the panel renders its own copy — which is also what the placeholder shows, so
 * an editor can see what they are replacing before they type.
 */

import { MediaUpload, MediaUploadCheck } from "@wordpress/block-editor";
import * as components from "@wordpress/components";
import { __, sprintf } from "@wordpress/i18n";

import { SIDEBAR_IMAGE_WIDTH } from "../../../lib/panelContent.js";

const { BaseControl, Button, RangeControl, TextControl } = components;

/**
 * One group of fields under its own heading, so twenty-odd inputs still read as
 * the three parts of the panel they belong to.
 *
 * @param {Object}      props          Component props.
 * @param {string}      props.label    Group heading.
 * @param {JSX.Element} props.children The fields.
 * @return {JSX.Element} The group.
 */
const Group = ({ label, children }) => (
	<div className="rox-panel-content__group">
		<p className="rox-panel-content__group-title">{label}</p>
		{children}
	</div>
);

/**
 * The panel copy controls.
 *
 * @param {Object}   props                 Component props.
 * @param {Object}   props.content         Current overrides (the `panelContent` attribute).
 * @param {boolean}  props.hasLocationStep Whether the panel shows a Location step at all.
 * @param {Function} props.onChange        Receives the next overrides object.
 * @return {JSX.Element} The controls.
 */
const PanelContentControls = ({ content, hasLocationStep, onChange }) => {
	const overrides = content || {};

	// A cleared field is removed rather than stored as "", so the attribute
	// holds only what the editor actually rewrote.
	const set = (key) => (value) => {
		const next = { ...overrides };

		if (value) {
			next[key] = value;
		} else {
			delete next[key];
		}

		onChange(next);
	};

	// The field opens showing the panel's own text as its value, not just as a
	// placeholder, so an editor can edit the real wording instead of retyping
	// it. It is still only stored once they change it, and clearing the field
	// drops the override and brings the default straight back.
	const field = (key, label, defaultText, help) => (
		<TextControl
			label={label}
			value={overrides[key] || defaultText}
			placeholder={defaultText}
			onChange={set(key)}
			help={help}
			__nextHasNoMarginBottom
		/>
	);


	return (
		<>
			<Group label={__("First step sidebar", "rox-appointment-booking")}>
				<BaseControl
					id="rox-booking-panel-sidebar-image"
					label={__("Illustration", "rox-appointment-booking")}
					help={__(
						"Leave empty to keep the panel's own illustration.",
						"rox-appointment-booking",
					)}
					__nextHasNoMarginBottom
				>
					<div className="rox-panel-content__media">
						{overrides.sidebarImage && (
							<img
								className="rox-panel-content__media-preview"
								src={overrides.sidebarImage}
								alt=""
							/>
						)}
						<MediaUploadCheck>
							<MediaUpload
								allowedTypes={["image"]}
								value={overrides.sidebarImage}
								onSelect={(media) => set("sidebarImage")(media?.url || "")}
								render={({ open }) => (
									<Button variant="secondary" onClick={open}>
										{overrides.sidebarImage
											? __("Replace image", "rox-appointment-booking")
											: __("Select image", "rox-appointment-booking")}
									</Button>
								)}
							/>
						</MediaUploadCheck>
						{overrides.sidebarImage && (
							<Button
								variant="tertiary"
								isDestructive
								onClick={() => set("sidebarImage")("")}
							>
								{__("Remove", "rox-appointment-booking")}
							</Button>
						)}
					</div>
				</BaseControl>

				{/* Sits with the picker because it sizes what the picker chose.
				    Shown even with no image of its own: the panel's default
				    illustration is sized by the same value. */}
				<RangeControl
					label={__("Illustration width", "rox-appointment-booking")}
					value={overrides.sidebarImageWidth || SIDEBAR_IMAGE_WIDTH.default}
					min={SIDEBAR_IMAGE_WIDTH.min}
					max={SIDEBAR_IMAGE_WIDTH.max}
					onChange={(value) => set("sidebarImageWidth")(value || 0)}
					allowReset
					help={sprintf(
						/* translators: %d: default illustration width in pixels */
						__(
							"Pixels. The sidebar column is %d px wide, so the image is scaled down to fit rather than past it.",
							"rox-appointment-booking",
						),
						SIDEBAR_IMAGE_WIDTH.max,
					)}
					__nextHasNoMarginBottom
				/>

				{field(
					"sidebarTitle",
					__("Title", "rox-appointment-booking"),
					__("Location Selection", "rox-appointment-booking"),
					__(
						"Shown whichever step comes first, so a panel that starts on Category or Services uses this too.",
						"rox-appointment-booking",
					),
				)}
				{field(
					"sidebarSubtitle",
					__("Subtitle", "rox-appointment-booking"),
					__(
						"Select the location where you'd like to book your appointment.",
						"rox-appointment-booking",
					),
				)}
				{field(
					"helpTitle",
					__("Help box title", "rox-appointment-booking"),
					__("Need Help?", "rox-appointment-booking"),
				)}
				{field(
					"helpButtonText",
					__("Help button text", "rox-appointment-booking"),
					__("Help", "rox-appointment-booking"),
				)}
				{field(
					"helpButtonUrl",
					__("Help button link", "rox-appointment-booking"),
					"/help",
				)}
				{field(
					"helpNote",
					__("Help box note", "rox-appointment-booking"),
					__("If you have any questions", "rox-appointment-booking"),
				)}
			</Group>

			<Group label={__("Step headings", "rox-appointment-booking")}>
				{/* The Location step is Pro's, and it only reaches the visitor
				    with the module on and more than one location to choose
				    between — with one the panel auto-selects it and drops the
				    step. Anything less and this heading is never rendered, so
				    the field would rewrite nothing. */}
				{hasLocationStep &&
					field(
						"locationHeading",
						__("Location step", "rox-appointment-booking"),
						__("Select Location", "rox-appointment-booking"),
					)}
				{field(
					"categoryHeading",
					__("Category step", "rox-appointment-booking"),
					__("Available Category", "rox-appointment-booking"),
				)}
				{field(
					"servicesHeading",
					__("Services step", "rox-appointment-booking"),
					__("Services", "rox-appointment-booking"),
					__(
						"Follows the chosen category's name, as in “Haircut Services”.",
						"rox-appointment-booking",
					),
				)}
				{field(
					"agentsHeading",
					__("Agents step", "rox-appointment-booking"),
					__("Select Agent", "rox-appointment-booking"),
				)}
				{field(
					"dateTimeHeading",
					__("Date & Time step", "rox-appointment-booking"),
					__("Date & Time Selection", "rox-appointment-booking"),
				)}
				{field(
					"informationHeading",
					__("Information step", "rox-appointment-booking"),
					__("Customer Information", "rox-appointment-booking"),
				)}
			</Group>

			<Group label={__("Step list", "rox-appointment-booking")}>
				{/* Same gate as the heading above: no Location step, no row in
				    the list. */}
				{hasLocationStep &&
					field(
						"stepLocationLabel",
						__("Location", "rox-appointment-booking"),
						__("Location", "rox-appointment-booking"),
					)}
				{field(
					"stepCategoryLabel",
					__("Category", "rox-appointment-booking"),
					__("Category", "rox-appointment-booking"),
				)}
				{field(
					"stepServicesLabel",
					__("Services", "rox-appointment-booking"),
					__("Services", "rox-appointment-booking"),
				)}
				{field(
					"stepAgentsLabel",
					__("Agents", "rox-appointment-booking"),
					__("Agents", "rox-appointment-booking"),
				)}
				{field(
					"stepDateTimeLabel",
					__("Date & Time", "rox-appointment-booking"),
					__("Date & Time", "rox-appointment-booking"),
				)}
				{field(
					"stepInformationLabel",
					__("Information", "rox-appointment-booking"),
					__("Information", "rox-appointment-booking"),
				)}
				{field(
					"stepPaymentLabel",
					__("Payment", "rox-appointment-booking"),
					__("Payment", "rox-appointment-booking"),
				)}
				{field(
					"stepCompleteLabel",
					__("Complete", "rox-appointment-booking"),
					__("Complete", "rox-appointment-booking"),
				)}
			</Group>
		</>
	);
};

export default PanelContentControls;
