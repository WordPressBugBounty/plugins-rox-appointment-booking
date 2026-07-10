import { __ } from "@wordpress/i18n";
import { useBlockProps, InspectorControls, PanelColorSettings } from "@wordpress/block-editor";
import {
	PanelBody,
	TextControl,
	ToggleControl,
	SelectControl,
	BoxControl as StableBoxControl,
	__experimentalBoxControl as ExperimentalBoxControl,
} from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";

// `BoxControl` was stabilized in newer WP; fall back to the experimental alias
// on older versions so the editor never crashes on an undefined component.
const BoxControl = StableBoxControl || ExperimentalBoxControl;

import ServicesStep from "./app/steps/ServicesStep.jsx";
import SummaryPanel from "./app/SummaryPanel.jsx";
import { fetchServices } from "./app/api.js";

// Shared frontend look + block-owned styles, so the editor preview matches the
// real frontend first step.
import "../../components/BookingService/bookingstyle.scss";
import "./app/service-list.scss";

const STEP_LABELS = [
	"Services",
	"Agents",
	"Date & Time",
	"Information",
	"Payment",
	"Complete",
];

const boxToShorthand = (box) => {
	if (!box || typeof box !== "object") return "";
	const sides = ["top", "right", "bottom", "left"];
	const hasValue = sides.some((s) => box[s]);
	if (!hasValue) return "";
	return sides.map((s) => box[s] || "0").join(" ");
};

const Edit = ({ attributes, setAttributes }) => {
	const blockProps = useBlockProps();

	const {
		showStepSidebar,
		showInfoSidebar,
		showServiceImage,
		serviceLayout,
		nextLabel,
		nextBg,
		nextColor,
		nextHoverBg,
		nextHoverColor,
		nextMargin,
		nextPadding,
		backLabel,
		backColor,
		backHoverColor,
		backMargin,
		backPadding,
	} = attributes;

	const [services, setServices] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		let active = true;
		fetchServices()
			.then((list) => {
				if (active) setServices(list);
			})
			.catch(() => {})
			.finally(() => {
				if (active) setLoading(false);
			});
		return () => {
			active = false;
		};
	}, []);

	// Build the --rab-* preview vars (PHP prints these on the frontend; here we
	// apply them inline so the editor preview reflects the controls live).
	const previewVars = {};
	if (nextBg) previewVars["--rab-next-bg"] = nextBg;
	if (nextColor) previewVars["--rab-next-color"] = nextColor;
	if (nextHoverBg) previewVars["--rab-next-bg-hover"] = nextHoverBg;
	if (nextHoverColor) previewVars["--rab-next-color-hover"] = nextHoverColor;
	if (backColor) previewVars["--rab-back-color"] = backColor;
	if (backHoverColor) previewVars["--rab-back-color-hover"] = backHoverColor;
	const nm = boxToShorthand(nextMargin);
	const np = boxToShorthand(nextPadding);
	const bm = boxToShorthand(backMargin);
	const bp = boxToShorthand(backPadding);
	if (nm) previewVars["--rab-next-margin"] = nm;
	if (np) previewVars["--rab-next-padding"] = np;
	if (bm) previewVars["--rab-back-margin"] = bm;
	if (bp) previewVars["--rab-back-padding"] = bp;

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Layout", "rox-appointment-booking")} initialOpen={true}>
					<ToggleControl
						label={__("Show step navigation (left)", "rox-appointment-booking")}
						checked={!!showStepSidebar}
						onChange={(value) => setAttributes({ showStepSidebar: value })}
					/>
					<ToggleControl
						label={__("Show info panel (right)", "rox-appointment-booking")}
						checked={!!showInfoSidebar}
						onChange={(value) => setAttributes({ showInfoSidebar: value })}
					/>
					<ToggleControl
						label={__("Show service image", "rox-appointment-booking")}
						checked={!!showServiceImage}
						onChange={(value) => setAttributes({ showServiceImage: value })}
					/>
					<SelectControl
						label={__("Service layout", "rox-appointment-booking")}
						value={serviceLayout || "grid"}
						options={[
							{ label: __("Grid", "rox-appointment-booking"), value: "grid" },
							{ label: __("List", "rox-appointment-booking"), value: "list" },
						]}
						onChange={(value) => setAttributes({ serviceLayout: value })}
					/>
				</PanelBody>

				<PanelBody title={__("Next button", "rox-appointment-booking")} initialOpen={false}>
					<TextControl
						label={__("Label", "rox-appointment-booking")}
						value={nextLabel}
						onChange={(value) => setAttributes({ nextLabel: value })}
					/>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: nextBg,
								onChange: (value) => setAttributes({ nextBg: value || "" }),
								label: __("Background", "rox-appointment-booking"),
							},
							{
								value: nextColor,
								onChange: (value) => setAttributes({ nextColor: value || "" }),
								label: __("Text", "rox-appointment-booking"),
							},
							{
								value: nextHoverBg,
								onChange: (value) => setAttributes({ nextHoverBg: value || "" }),
								label: __("Hover background", "rox-appointment-booking"),
							},
							{
								value: nextHoverColor,
								onChange: (value) => setAttributes({ nextHoverColor: value || "" }),
								label: __("Hover text", "rox-appointment-booking"),
							},
						]}
					/>
					<BoxControl
						label={__("Margin", "rox-appointment-booking")}
						values={nextMargin}
						onChange={(value) => setAttributes({ nextMargin: value || {} })}
					/>
					<BoxControl
						label={__("Padding", "rox-appointment-booking")}
						values={nextPadding}
						onChange={(value) => setAttributes({ nextPadding: value || {} })}
					/>
				</PanelBody>

				<PanelBody title={__("Back button", "rox-appointment-booking")} initialOpen={false}>
					<TextControl
						label={__("Label", "rox-appointment-booking")}
						value={backLabel}
						onChange={(value) => setAttributes({ backLabel: value })}
					/>
					<PanelColorSettings
						title={__("Colors", "rox-appointment-booking")}
						enableAlpha
						colorSettings={[
							{
								value: backColor,
								onChange: (value) => setAttributes({ backColor: value || "" }),
								label: __("Text", "rox-appointment-booking"),
							},
							{
								value: backHoverColor,
								onChange: (value) => setAttributes({ backHoverColor: value || "" }),
								label: __("Hover text", "rox-appointment-booking"),
							},
						]}
					/>
					<BoxControl
						label={__("Margin", "rox-appointment-booking")}
						values={backMargin}
						onChange={(value) => setAttributes({ backMargin: value || {} })}
					/>
					<BoxControl
						label={__("Padding", "rox-appointment-booking")}
						values={backPadding}
						onChange={(value) => setAttributes({ backPadding: value || {} })}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				<div
					className="service-layout-outer rab-sl-app rab-sl-editor-preview"
					style={previewVars}
				>
					<div className="service-layout">
						{showStepSidebar ? (
							<div className="service-sidebar step-container">
								<div className="rab-sl-steps">
									{STEP_LABELS.map((label, i) => (
										<div
											key={label}
											className={`rab-sl-step ${i === 0 ? "active" : ""}`}
										>
											<span className="rab-sl-step-dot">{i + 1}</span>
											<span className="rab-sl-step-label">{label}</span>
										</div>
									))}
								</div>
							</div>
						) : null}

						<div className="main with-navigation">
							<div className="main-content">
								<ServicesStep
									services={services}
									loading={loading}
									readOnly
									showImage={showServiceImage}
									layout={serviceLayout === "list" ? "list" : "grid"}
								/>
							</div>
							<div className="rab-sl-nav">
								<span />
								<button type="button" className="rab-sl-next" disabled>
									<span className="rab-sl-next-text">
										{nextLabel || __("Next", "rox-appointment-booking")}
									</span>
									<span className="rab-sl-next-arrow">→</span>
								</button>
							</div>
						</div>

						{showInfoSidebar ? (
							<div className="rab-sl-summary">
								<SummaryPanel currencySymbol="$" />
							</div>
						) : null}
					</div>
				</div>
			</div>
		</>
	);
};

export default Edit;
