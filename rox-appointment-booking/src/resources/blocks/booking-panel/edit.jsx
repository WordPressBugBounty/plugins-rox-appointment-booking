import { useBlockProps, InspectorControls } from "@wordpress/block-editor";
import { PanelBody, ToggleControl, Spinner } from "@wordpress/components";
import { useEffect, useState } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import apiFetch from "@wordpress/api-fetch";

import SelectionSidebar from "../../components/BookingService/SelectionSidebar.jsx";
import CategoryCards from "../../components/BookingService/CategoryCards.jsx";

// Reuse the real frontend look so the editor preview matches the first step
// rendered on the published page.
import "../../components/BookingService/bookingstyle.scss";

// Editor preview: renders the booking panel's first step (selection sidebar +
// category cards) live, reusing the real frontend components. The actual
// interactive panel is mounted on the public side by
// BookingPanelBlock::renderBlock().
const Edit = ({ attributes, setAttributes }) => {
	const { hideNavigation, hideInfo } = attributes;

	const blockProps = useBlockProps({
		className: "rox-booking-panel-block-editor",
	});

	const [structure, setStructure] = useState(null);
	const [categories, setCategories] = useState([]);
	const [loading, setLoading] = useState(true);

	useEffect(() => {
		let active = true;
		const restBase =
			window?.rox_appointment_booking?.config?.app?.restBaseUrl || "/wp-json/";

		apiFetch({
			url: `${restBase}rox-appointment-booking/v1/booking-panel-structure`,
		})
			.then((res) => {
				const data = res?.data || {};
				if (active) setStructure(data);
				const catApi = data?.content?.categoriesApi;
				return catApi ? apiFetch({ url: catApi }) : null;
			})
			.then((catRes) => {
				if (active && catRes?.data) setCategories(catRes.data);
			})
			.catch(() => {})
			.finally(() => {
				if (active) setLoading(false);
			});

		return () => {
			active = false;
		};
	}, []);

	return (
		<>
			<InspectorControls>
				<PanelBody title={__("Layout", "rox-appointment-booking")}>
					<ToggleControl
						label={__("Hide left navigation", "rox-appointment-booking")}
						checked={!!hideNavigation}
						onChange={(value) => setAttributes({ hideNavigation: value })}
					/>
					<ToggleControl
						label={__("Hide right info section", "rox-appointment-booking")}
						checked={!!hideInfo}
						onChange={(value) => setAttributes({ hideInfo: value })}
						help={__(
							"The right info / booking summary appears from the Date & Time step onward, so it is not visible on this first-step preview.",
							"rox-appointment-booking",
						)}
					/>
				</PanelBody>
			</InspectorControls>

			<div {...blockProps}>
				{/* Static preview: clicks are disabled so the editor stays inert. */}
				<div
					className="service-layout-outer rox-booking-panel-preview"
					style={{ pointerEvents: "none" }}
				>
					<div className="service-layout">
						{!hideNavigation && (
							<div className="service-sidebar selection-step">
								{structure ? (
									<SelectionSidebar sidebarDetails={structure} />
								) : null}
							</div>
						)}

						<div className="main without-navigation">
							<div className="main-content">
								{loading ? (
									<div className="rox-booking-panel-preview__loading">
										<Spinner />
									</div>
								) : (
									<CategoryCards
										categories={categories}
										onCategorySelect={() => {}}
										selectedCategoryId={null}
									/>
								)}
							</div>
						</div>

						{!hideInfo && <div className="right-sidebar-content" />}
					</div>
				</div>
			</div>
		</>
	);
};

export default Edit;
