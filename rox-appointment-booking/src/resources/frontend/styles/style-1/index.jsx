/**
 * Style 1 — the multi-step booking panel the plugin has always rendered.
 *
 * Nothing of the panel itself lives here: it is the long-standing
 * `components/BookingService` tree, and this file is only the seam that lets a
 * surface pick between designs. Every prop is passed straight through, so a
 * page set to Style 1 renders exactly what it did before the styles existed.
 */

import BookingService from "../../../components/BookingService/index.jsx";

const Style1Panel = ({
	instanceId,
	type,
	hideNavigation,
	serviceColumns,
	showDashboardButton,
	dashboardButtonText,
	dashboardButtonUrl,
	contentMargin,
	headingAlign,
	headingMargin,
	hideInfo,
	showBackground,
	backgroundColor,
	allowedLocationIds,
	allowedCategoryIds,
	panelContent,
	singleAgentId,
}) => (
	<BookingService
		instanceId={instanceId}
		type={type}
		hideNavigation={hideNavigation}
		serviceColumns={serviceColumns}
		showDashboardButton={showDashboardButton}
		dashboardButtonText={dashboardButtonText}
		dashboardButtonUrl={dashboardButtonUrl}
		contentMargin={contentMargin}
		headingAlign={headingAlign}
		headingMargin={headingMargin}
		hideInfo={hideInfo}
		showBackground={showBackground}
		backgroundColor={backgroundColor}
		allowedLocationIds={allowedLocationIds}
		allowedCategoryIds={allowedCategoryIds}
		panelContent={panelContent}
		// Locking the panel to one agent skips the location/category/agent
		// steps entirely and opens on that agent's services. Absent on every
		// other surface, which leaves the normal multi-step flow untouched.
		singleAgentId={singleAgentId}
		singleAgentConfig={singleAgentId ? { agentId: singleAgentId } : null}
	/>
);

export default Style1Panel;
