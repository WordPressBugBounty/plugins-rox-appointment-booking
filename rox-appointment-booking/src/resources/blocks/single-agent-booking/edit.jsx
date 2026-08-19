import {
	useBlockProps,
	InspectorControls,
	ColorPalette,
} from "@wordpress/block-editor";
import {
	PanelBody,
	SelectControl,
	ToggleControl,
	Spinner,
	BaseControl,
} from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import AgentInfoPanel from "../../components/BookingService/AgentInfoPanel.jsx";
// Reuse the real panel styles so the editor preview matches the published panel.
import "../../components/BookingService/bookingstyle.scss";

/**
 * Block editor UI for the Single Agent Booking panel.
 *
 * The centrepiece is the agent-selection dropdown (the block equivalent of the
 * shortcode's `agent_id`): the editor picks which agent the frontend panel is
 * locked to. Display toggles control the left agent card, and a live preview
 * reuses the real AgentInfoPanel. The interactive panel itself is mounted on the
 * public side by the Pro `SingleAgentBookingBlock::renderBlock()`.
 */
const Edit = ({ attributes, setAttributes }) => {
	const {
		agentId,
		showBio,
		showStats,
		showSocials,
		showWorkDays,
		showContact,
		showBackground,
		// NOTE: named `backgroundColor` because it is ours alone — the block does
		// not opt into `supports.color`, which would reserve that same attribute
		// name for a palette slug. Rename this if colour support is ever added.
		backgroundColor,
	} = attributes;

	const blockProps = useBlockProps({
		className: "rox-single-agent-block-editor",
	});

	const [agents, setAgents] = useState([]);
	const [agentsLoading, setAgentsLoading] = useState(true);
	const [agent, setAgent] = useState(null);
	const [agentLoading, setAgentLoading] = useState(false);

	// Agent list for the dropdown.
	useEffect(() => {
		let active = true;
		apiFetch({
			path: "/rox-appointment-booking/v1/public/agent?mode=list&per_page=100",
		})
			.then((res) => {
				if (active) setAgents(Array.isArray(res?.data) ? res.data : []);
			})
			.catch(() => {})
			.finally(() => {
				if (active) setAgentsLoading(false);
			});
		return () => {
			active = false;
		};
	}, []);

	// Selected agent's detail for the preview card.
	useEffect(() => {
		if (!agentId) {
			setAgent(null);
			return undefined;
		}
		let active = true;
		setAgentLoading(true);
		apiFetch({
			path: `/rox-appointment-booking/v1/public/agent/${agentId}`,
		})
			.then((res) => {
				if (active) setAgent(res?.success ? res.data : null);
			})
			.catch(() => {
				if (active) setAgent(null);
			})
			.finally(() => {
				if (active) setAgentLoading(false);
			});
		return () => {
			active = false;
		};
	}, [agentId]);

	const agentOptions = [
		{ label: __("— Select an agent —", "rox-appointment-booking"), value: 0 },
		...agents.map((a) => ({ label: a.name || `#${a.id}`, value: a.id })),
	];

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Agent", "rox-appointment-booking")}>
					{agentsLoading ? (
						<Spinner />
					) : (
						<SelectControl
							label={__("Booking agent", "rox-appointment-booking")}
							help={__(
								"The panel is locked to this agent — the frontend shows only this agent's services.",
								"rox-appointment-booking",
							)}
							value={agentId || 0}
							options={agentOptions}
							onChange={(value) => setAttributes({ agentId: Number(value) })}
							__nextHasNoMarginBottom
						/>
					)}
				</PanelBody>
				<PanelBody
					title={__("Agent card", "rox-appointment-booking")}
					initialOpen={false}
				>
					<ToggleControl
						label={__("Show bio", "rox-appointment-booking")}
						checked={!!showBio}
						onChange={(value) => setAttributes({ showBio: value })}
					/>
					{/*
					 * Hidden for now: the redesigned agent sidebar no longer shows
					 * stats / contact / socials / work days, so the controls have
					 * nothing to switch. Everything behind them is left in place —
					 * the block.json attributes, the values passed to the preview
					 * below, and the Pro config — so restoring these controls is
					 * the only step needed when the sections come back.
					 *
					 <ToggleControl
					 	label={__("Show stats", "rox-appointment-booking")}
					 	checked={!!showStats}
					 	onChange={(value) => setAttributes({ showStats: value })}
					 />
					 <ToggleControl
					 	label={__("Show contact", "rox-appointment-booking")}
					 	checked={!!showContact}
					 	onChange={(value) => setAttributes({ showContact: value })}
					 />
					 <ToggleControl
					 	label={__("Show socials", "rox-appointment-booking")}
					 	checked={!!showSocials}
					 	onChange={(value) => setAttributes({ showSocials: value })}
					 />
					 <ToggleControl
					 	label={__("Show work days", "rox-appointment-booking")}
					 	checked={!!showWorkDays}
					 	onChange={(value) => setAttributes({ showWorkDays: value })}
					 />
					 */}
				</PanelBody>
				<PanelBody
					title={__("Appearance", "rox-appointment-booking")}
					initialOpen={false}
				>
					<ToggleControl
						label={__("Enable background", "rox-appointment-booking")}
						help={__(
							"Draws the grey frame (background, padding and shadow) around the panel. Turn it off to let the panel sit directly on the page.",
							"rox-appointment-booking",
						)}
						checked={!!showBackground}
						onChange={(value) => setAttributes({ showBackground: value })}
					/>
					{showBackground && (
						<BaseControl
							id="rox-single-agent-background-color"
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
			</InspectorControls>

			<div {...blockProps}>
				{!agentId ? (
					<div className="rox-single-agent-block-editor__placeholder">
						<strong>
							{__("Single Agent Booking", "rox-appointment-booking")}
						</strong>
						<p>
							{__(
								"Select an agent in the block settings to lock this booking panel to them.",
								"rox-appointment-booking",
							)}
						</p>
					</div>
				) : (
					<div
						className={`service-layout-outer single-agent rox-single-agent-preview${
							showBackground ? "" : " no-background"
						}`}
						style={{
							pointerEvents: "none",
							...(showBackground && backgroundColor
								? { backgroundColor }
								: {}),
						}}
					>
						<div className="service-layout">
							<div className="service-sidebar step-container">
								<AgentInfoPanel
									agent={agent}
									loading={agentLoading}
									error={
										!agentLoading && !agent
											? __("Agent not found.", "rox-appointment-booking")
											: ""
									}
									config={{
										showBio,
										showStats,
										showSocials,
										showWorkDays,
										showContact,
									}}
								/>
							</div>

							<div className="main without-navigation">
								<div className="main-content">
									<div className="rox-single-agent-preview__flow">
										<strong>
											{__("Services", "rox-appointment-booking")}
										</strong>
										<p>
											{__(
												"The visitor picks one of this agent's services, then Date & Time → Information → Payment.",
												"rox-appointment-booking",
											)}
										</p>
									</div>
								</div>
							</div>

							<div className="right-sidebar-content" />
						</div>
					</div>
				)}
			</div>
		</>
	);
};

export default Edit;
