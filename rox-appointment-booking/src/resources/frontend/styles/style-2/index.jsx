/**
 * Style 2 — the new booking panel design.
 *
 * Built on the shared booking engine (see BookingService/engine/useBookingEngine.js)
 * so every rule — availability, validation, submission — is identical to
 * Style 1. This file and its siblings only decide how the panel looks.
 *
 * Progress: Location, Category, Services, Agents, Date & Time, Order Details
 * and Complete have their own Style 2 design. Information and Payment don't
 * have one yet, so — rather than leave the flow dead-ended — they render the
 * exact Style 1 components for now (their CSS is already bundled:
 * `styles/index.js` imports both styles eagerly, so Style 1's stylesheet loads
 * regardless of which one is chosen). Swap each one out for its own Style 2
 * file as the design arrives; nothing else needs to change when you do.
 */

import { useEffect, useMemo, useRef, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { BookingStoreProvider } from "../../../redux/booking-store-context.js";
import { useBookingEngine } from "../../../components/BookingService/engine/useBookingEngine.js";
import { fullStepKeys, panelStepLabels } from "../../../lib/panelSteps.js";
import { panelText } from "../../../lib/panelContent.js";
import Sidebar, { PANEL_BRAND } from "./Sidebar.jsx";
import StepProgress from "./StepProgress.jsx";
import CardListStep from "./CardListStep.jsx";
import ServicesStep from "./ServicesStep.jsx";
import AgentsStep from "./AgentsStep.jsx";
import DateTimeStep from "./DateTimeStep.jsx";
import OrderDetailsStep from "./OrderDetailsStep.jsx";
import ExtraServicesStep from "./ExtraServicesStep.jsx";
import CompleteStep from "./CompleteStep.jsx";
import { style2PanelVars } from "./panelVars.js";
import { style2StepHeadings } from "./headings.js";
// The group-capacity sheet is deliberately Style 1's own component, not a
// Style 2 copy of it: the design for the two is identical, and one component
// with one stylesheet is the only way that stays true. Its CSS
// (`.group-attendees-panel` / `.ga-overlay`) is unscoped and self-contained —
// it needs nothing but a positioned ancestor, which `.rbs1-main` is.
import Attendees from "../../../components/BookingService/Attendees.jsx";
import "./panel.scss";

// Temporary bridges — see the file banner above.
import CustomerInfo from "../../../components/BookingService/CustomerInfo.jsx";
import StripePaymentForm from "../../../components/BookingService/StripePaymentForm.jsx";

const Style2Panel = ({
	instanceId,
	hideNavigation,
	// Service cards per row on the Services step, and the step heading's own
	// alignment and margin. Each reaches the stylesheet as a custom property on
	// the shell below; unset, every one of them leaves the panel as it was.
	serviceColumns,
	headingAlign,
	headingMargin,
	// The grey frame around the panel, and an optional colour for it. Already
	// resolved by the surface: this design sits flat on the page unless an
	// editor asked for the frame, which is the opposite of Style 1's default.
	showBackground = false,
	backgroundColor = "",
	allowedLocationIds,
	allowedCategoryIds,
	// The panel's own wording, as far as the surface let an editor rewrite it.
	// Every key is optional; an unset one leaves the built-in text.
	panelContent,
	singleAgentId,
	// The Complete step's second action. Off for a public panel whose visitors
	// have no account to reach; the label and target are the surface's when it
	// sets them, else the built-in text and the plugin's own dashboard page.
	showDashboardButton = true,
	dashboardButtonText = "",
	dashboardButtonUrl = "",
}) => {
	const engine = useBookingEngine({
		instanceId,
		singleAgentId,
		allowedLocationIds,
		allowedCategoryIds,
	});

	const {
		storeContext,
		dispatch,
		locations,
		selectedLocationId,
		categories,
		selectedCategory,
		services,
		selectedService,
		agents,
		selectedEmployee,
		viewingEmployeeDetails,
		employeeDetailsLoading,
		selectedDate,
		selectedStartTime,
		selectedEndTime,
		customerInfo,
		bookingProcess,
		currentStep,
		hasLocations,
		payLater,
		currency,
		bookingResponse,
		appliedCouponData,
		setAppliedCouponData,
		setPaymentAmountChoice,
		paymentFooterAction,
		setPaymentFooterAction,
		customerInfoAttempted,
		serviceIsGroup,
		attendeesOpen,
		extraServices,
		selectedExtraServices,
		showExtraServices,
		currentBookingForExtras,
		stepKeys,
		handleCustomerInfoSubmit,
		handleCustomerInfoNext,
		handleDateTimeSelect,
		handleCategorySelect,
		handleServiceSelect,
		handleAttendeesChange,
		handleAttendeesConfirm,
		handleAttendeesClose,
		handleEmployeeSelect,
		handleViewEmployeeDetails,
		handleBackFromEmployeeDetails,
		handleBackStep,
		handleNextStep,
		handleLocationSelect,
		handleRefreshLocations,
		handleRefreshCategories,
		handleRefreshAgents,
		handleContactSupport,
		handleBookAnother,
		handleAddNewBooking,
		handleDeleteBooking,
		handleShowExtraServices,
		handleExtraServiceSelect,
		handleDeleteExtraService,
		listStatus,
		handleRetryList,
		calculateGrandTotal,
		handleBookingSubmit,
		isSubmittingBooking,
	} = engine;

	// What the step list shows. Longer than the flow the engine actually runs:
	// a site with one location (or one category) has that step resolved for the
	// visitor and never shown, but dropping it from the list would make the
	// list shrink as they walk through it. Listing them all and marking the
	// resolved ones done is what the design asks for.
	const displayKeys = useMemo(
		() => fullStepKeys(Boolean(hasLocations)),
		[hasLocations],
	);
	const labels = panelStepLabels(panelContent);
	const displaySteps = useMemo(
		() => displayKeys.map((key) => labels[key]),
		// eslint-disable-next-line react-hooks/exhaustive-deps
		[displayKeys],
	);

	// What the panel's own heading says, which is not what the rail beside it
	// says. The rail names the step — one or two words, so eight of them stack
	// in a 220px column — while the heading tells the visitor what to do on the
	// screen they are looking at. Style 1 has no such heading, so this map is
	// Style 2's alone.
	//
	// Falls back to the rail's label for any key not listed: Complete draws no
	// heading at all, and the extra-services picker sets its own below.
	//
	// Each one an editor can rewrite through the surface's "Step headings"
	// fields; an unset override leaves the wording below. Order Details and
	// Payment have no field of their own — the first is Style 2's own screen,
	// which the shared override list does not name.
	const defaultHeadings = style2StepHeadings();
	const headerTitles = {
		Location: panelText(
			panelContent,
			"locationHeading",
			defaultHeadings.locationHeading,
		),
		Category: panelText(
			panelContent,
			"categoryHeading",
			defaultHeadings.categoryHeading,
		),
		Services: panelText(
			panelContent,
			"servicesHeading",
			defaultHeadings.servicesHeading,
		),
		Agents: panelText(
			panelContent,
			"agentsHeading",
			defaultHeadings.agentsHeading,
		),
		DateTime: panelText(
			panelContent,
			"dateTimeHeading",
			defaultHeadings.dateTimeHeading,
		),
		Information: panelText(
			panelContent,
			"informationHeading",
			defaultHeadings.informationHeading,
		),
		OrderDetails: __("Verify Order Details", "rox-appointment-booking"),
		Payment: __("Select Payment Type", "rox-appointment-booking"),
	};

	// Where the engine actually is.
	const engineKey = stepKeys[currentStep - 1];

	// Order Details is Style 2's own screen and the engine knows nothing about
	// it: it stands in front of the payment step until its Continue dismisses
	// it, so the flow gains a review screen without the engine — and therefore
	// Style 1 — gaining a step.
	//
	// Which means it is also the one position in the flow the engine does not
	// persist. Every other step is placed by `currentStep`, which the store
	// writes to sessionStorage; this flag is plain component state, so a reload
	// on the payment step used to restore the step, lose the flag, and put the
	// review back in front of it. It is persisted here for that reason.
	//
	// Its own key rather than a field on the store's: the store serialises its
	// whole snapshot on every action from its own state alone, so anything
	// merged into that entry from outside is dropped on the next write.
	const reviewKey = `${storeContext.sessionKey}:order-details-done`;

	const [orderDetailsDone, setOrderDetailsDone] = useState(() => {
		try {
			return sessionStorage.getItem(reviewKey) === "1";
		} catch (error) {
			// Private mode, or a browser set to block storage.
			return false;
		}
	});

	useEffect(() => {
		try {
			if (orderDetailsDone) {
				sessionStorage.setItem(reviewKey, "1");
			} else {
				sessionStorage.removeItem(reviewKey);
			}
		} catch (error) {
			// Not being able to remember costs a re-shown review, nothing more.
		}
	}, [orderDetailsDone, reviewKey]);

	// Leaving the payment step in either direction arms the review again, so
	// stepping back to Information and forward once more shows it a second time
	// rather than jumping straight to payment. The effect above clears the
	// stored flag with it.
	useEffect(() => {
		if (engineKey !== "Payment") {
			setOrderDetailsDone(false);
		}
	}, [engineKey]);

	const onOrderDetails = engineKey === "Payment" && !orderDetailsDone;
	const displayKey = onOrderDetails ? "OrderDetails" : engineKey;
	const displayStep = displayKeys.indexOf(displayKey) + 1;
	const isConfirmationStep = engineKey === "Complete";

	// The extra-services picker stands in front of the step that opened it
	// rather than being a step of its own — the engine's flag is what puts it
	// there, so the step list, the counter and the engine's position are all
	// untouched while it is up.
	const onExtraServices = Boolean(showExtraServices && currentBookingForExtras);

	// An agent's details stand in for the whole step, heading included: the
	// photo is the first thing in the panel, with only the gap the header used
	// to leave above it. Scoped to the Agents step so a stale flag cannot take
	// the heading off any other one. The footer goes with it — see `showNav`.
	// The placeholder counts as being on the details too: it stands in the same
	// pane, so leaving the heading up for the length of the fetch and taking it
	// away as the details land would move the whole view up under the visitor.
	const onAgentDetails =
		displayKey === "Agents" &&
		Boolean(viewingEmployeeDetails || employeeDetailsLoading);

	// Every step shares the one scrolling box (see `.rbs1-body`), so without this
	// a step entered after scrolling the last one to its end opens at that same
	// offset — halfway down a list it has never shown before. Each new step, and
	// the extras picker that stands in front of one, starts at the top.
	const bodyRef = useRef(null);

	useEffect(() => {
		if (bodyRef.current) {
			bodyRef.current.scrollTop = 0;
		}
	}, [displayKey, onExtraServices]);

	// Another booking can only be started once this one has a time on it, which
	// is the same gate Style 1's sidebar puts on its "New Appointment" button.
	const canAddBooking = Boolean(selectedDate && selectedStartTime);

	// Per-step "can we go on" + button config — the same rules Style 1 applies
	// (see BookingService/index.jsx's step configs), kept here only for the
	// steps Style 2 renders itself; the bridged steps below carry their own.
	let nextDisabled = false;
	let nextHidden;
	let nextText = __("Continue", "rox-appointment-booking");
	let backText = __("Back", "rox-appointment-booking");
	let headerTitle = headerTitles[displayKey] || labels[displayKey];
	let onNext = handleNextStep;
	let onBack = handleBackStep;
	let showNav = true;

	if (onExtraServices) {
		// Both actions do the same thing — the picker writes each choice through
		// as it is made, so there is nothing left to commit or undo on the way
		// out. Two buttons because the footer has two, not because they differ.
		const close = () => dispatch.setShowExtraServices(false, null);
		headerTitle = __("Extra Services", "rox-appointment-booking");
		backText = __("Cancel", "rox-appointment-booking");
		onNext = close;
		onBack = close;
	} else if (onOrderDetails) {
		onNext = () => setOrderDetailsDone(true);
	} else if (displayKey === "Location") {
		nextDisabled = !selectedLocationId;
	} else if (displayKey === "Category") {
		nextDisabled = !selectedCategory;
	} else if (displayKey === "Services") {
		nextDisabled = !selectedService;
		showNav = !attendeesOpen;
	} else if (displayKey === "Agents") {
		nextDisabled = !selectedEmployee;
		showNav = !viewingEmployeeDetails && !employeeDetailsLoading;
	} else if (displayKey === "DateTime") {
		nextDisabled = !selectedDate || !selectedStartTime;
	} else if (displayKey === "Information") {
		onNext = handleCustomerInfoNext;
	} else if (displayKey === "Payment") {
		nextText =
			paymentFooterAction?.label ||
			__("Confirm Payment", "rox-appointment-booking");
		// Pay Later also stays disabled from the first click until the booking
		// fails or the Complete step replaces it (`isSubmittingBooking`), so it
		// cannot be sent twice. Card payments report their own busy state.
		nextDisabled = paymentFooterAction
			? paymentFooterAction.disabled
			: !payLater || isSubmittingBooking;
		nextHidden = paymentFooterAction ? false : !payLater;
		onNext = paymentFooterAction
			? paymentFooterAction.onClick
			: () => handleBookingSubmit("later");
		// The engine has no step between Information and Payment, so going back
		// from payment means re-showing the review rather than moving the engine.
		onBack = () => setOrderDetailsDone(false);
	}

	const renderStep = () => {
		if (onExtraServices) {
			return (
				<ExtraServicesStep
					extraServices={extraServices}
					selectedExtraServices={selectedExtraServices}
					onExtraServiceSelect={handleExtraServiceSelect}
					onBack={() => dispatch.setShowExtraServices(false, null)}
					status={listStatus.extraServices}
					onRetry={handleRetryList("extraServices")}
				/>
			);
		}

		if (onOrderDetails) {
			return (
				<OrderDetailsStep
					bookings={bookingProcess}
					total={calculateGrandTotal()}
					appliedCouponData={appliedCouponData}
					onDeleteBooking={handleDeleteBooking}
					onShowExtraServices={handleShowExtraServices}
					onDeleteExtraService={handleDeleteExtraService}
					onAddNewBooking={handleAddNewBooking}
					canAddBooking={canAddBooking}
					panelContent={panelContent}
				/>
			);
		}

		switch (engineKey) {
			// No `getSubtitle` here, unlike every other list: a location card
			// carries its name and nothing else. The full postal address it used
			// to show ran past the card and was ellipsed mid-word anyway.
			case "Location":
				return (
					<CardListStep
						items={locations}
						onSelect={handleLocationSelect}
						selectedId={selectedLocationId}
						getIcon={(location) => location.iconPath}
						getTitle={(location) => location.name}
						className="rbs1-list--locations"
						emptyTitle={__("No Locations Found", "rox-appointment-booking")}
						emptyDescription={__("There are no available locations right now. Try refreshing or check back later.", "rox-appointment-booking")}
						emptyActions={[
							{ label: __("Refresh", "rox-appointment-booking"), onClick: handleRefreshLocations },
							{ label: __("Contact Support", "rox-appointment-booking"), onClick: handleContactSupport },
						]}
						skeletonVariant="locations"
						status={listStatus.locations}
						onRetry={handleRetryList("locations")}
						errorTitle={__("Couldn't Load Locations", "rox-appointment-booking")}
						errorDescription={__("Something went wrong while loading the locations. Check your connection and try again.", "rox-appointment-booking")}
					/>
				);

			case "Category":
				return (
					<CardListStep
						items={categories}
						onSelect={handleCategorySelect}
						selectedId={selectedCategory?.id}
						getIcon={(cat) => cat.iconPath}
						getTitle={(cat) => cat.name}
						getSubtitle={(cat) => cat.description}
						getBadge={(cat) => cat.services_count}
						emptyTitle={__("No Categories Available", "rox-appointment-booking")}
						emptyDescription={__("No service categories are available for the selected location. Go back and try a different location.", "rox-appointment-booking")}
						emptyActions={[
							{ label: __("Refresh", "rox-appointment-booking"), onClick: handleRefreshCategories },
						]}
						skeletonVariant="categories"
						status={listStatus.categories}
						onRetry={handleRetryList("categories")}
						errorTitle={__("Couldn't Load Categories", "rox-appointment-booking")}
						errorDescription={__("Something went wrong while loading the service categories. Check your connection and try again.", "rox-appointment-booking")}
					/>
				);

			case "Services":
				// The group-capacity sheet is NOT rendered here: it covers the whole
				// step column, footer included, so it belongs beside the column
				// rather than inside the step's own content. See `.rbs1-main` below.
				return (
					<ServicesStep
						services={services}
						selectedService={selectedService}
						onServiceSelect={handleServiceSelect}
						emptyTitle={__("No Services Found", "rox-appointment-booking")}
						emptyDescription={__("This category has no services listed at the moment. Try a different category or location.", "rox-appointment-booking")}
						emptyActions={[
							{ label: __("Go Back", "rox-appointment-booking"), onClick: handleBackStep },
							{ label: __("Contact Support", "rox-appointment-booking"), onClick: handleContactSupport },
						]}
						status={listStatus.services}
						onRetry={handleRetryList("services")}
					/>
				);

			case "Agents":
				return (
					<AgentsStep
						agents={agents}
						selectedEmployee={selectedEmployee}
						onEmployeeSelect={handleEmployeeSelect}
						onViewDetails={handleViewEmployeeDetails}
						viewingEmployeeDetails={viewingEmployeeDetails}
						employeeDetailsLoading={employeeDetailsLoading}
						onBackFromDetails={handleBackFromEmployeeDetails}
						emptyTitle={__("No Agents Available", "rox-appointment-booking")}
						emptyDescription={__("No agents are available for the selected service right now. Try a different time or service.", "rox-appointment-booking")}
						emptyActions={[
							{ label: __("Change Service", "rox-appointment-booking"), onClick: handleBackStep },
							{ label: __("Refresh", "rox-appointment-booking"), onClick: handleRefreshAgents },
						]}
						status={listStatus.agents}
						onRetry={handleRetryList("agents")}
					/>
				);

			case "DateTime":
				return (
					<DateTimeStep
						onDateTimeSelect={handleDateTimeSelect}
						selectedDate={selectedDate}
						selectedStartTime={selectedStartTime}
						selectedEndTime={selectedEndTime}
						serviceId={selectedService?.id}
						agentId={selectedEmployee ? selectedEmployee.id : null}
						bookingProcess={bookingProcess}
						isGroupService={serviceIsGroup}
					/>
				);

			// Style 1's component, wearing Style 2's clothes. Deliberately not a
			// copy of it: this step carries the login, the password reset, the
			// Google sign-in, eight kinds of admin-configured custom field and a
			// measured row fold, and a second implementation of all that would
			// drift from this one the first time any of it changed. Style 2's
			// look comes from the `.rox-booking-style-2` rules over the form in
			// panel.scss instead, so the behaviour here is the same behaviour by
			// construction rather than by careful copying.
			case "Information":
				return (
					<CustomerInfo
						onSubmit={handleCustomerInfoSubmit}
						formData={customerInfo}
						showValidationErrors={customerInfoAttempted}
						resetParams={engine.resetParams}
						onExitReset={engine.handleExitResetPassword}
						// No fold of its own: the step column scrolls this step the way
						// it scrolls every other one, and a second scrolling box inside
						// that one would put two scrollbars side by side. No heading of
						// its own either — the panel prints the step name in its header.
						capRows={false}
						showHeading={false}
						// One underline that travels between the tabs, the way the
						// dashboard's settings tabs move theirs.
						slidingTabs
						// The Google button in the panel's own face and icon set —
						// none of Google's presets is the grey row this design draws.
						googleCustomFace
					/>
				);

			case "Payment":
				return (
					<StripePaymentForm
						bookings={bookingProcess}
						setPayLater={(value) => dispatch.setPayLater(value)}
						calculateGrandTotal={calculateGrandTotal}
						setCurrency={(value) => dispatch.setCurrency(value)}
						currency={currency}
						onBookingSubmit={handleBookingSubmit}
						onCouponChange={setAppliedCouponData}
						// Read as well as written: this form is remounted every time the
						// step is entered, and this is what an applied coupon comes back
						// from after a trip to Order Details and back.
						appliedCouponData={appliedCouponData}
						onPaymentAmountChoiceChange={setPaymentAmountChoice}
						onFooterActionChange={setPaymentFooterAction}
						// Style 2's payment cards name what each method does under
						// its title; Style 1's show the title alone. No heading of its
						// own either — the panel header already carries the step name.
						showMethodDescriptions
						showHeading={false}
					/>
				);

			case "Complete":
				return (
					<CompleteStep
						bookings={bookingProcess}
						customerInfo={customerInfo}
						bookingResponse={bookingResponse}
					/>
				);

			default:
				return null;
		}
	};

	// Built here rather than inline so the canvas preview can apply the exact
	// same properties; see panelVars.js.
	const panelVars = style2PanelVars({
		serviceColumns,
		headingAlign,
		headingMargin,
	});

	if (!engineKey) return null;

	return (
		<BookingStoreProvider value={storeContext}>
			<div
				className={`rox-booking-style-2 ${
					showBackground ? "has-background" : ""
				}`.trim()}
				// The frame's own colour, which only means anything while the
				// frame is drawn. Empty keeps the stylesheet's grey.
				style={
					showBackground && backgroundColor ? { backgroundColor } : undefined
				}
			>
				{/* Date & Time is the one step that is not the list steps' height: a
				    month has to be shown whole, and six weeks of it do not fit.
				    See `.rbs1-shell--tall`. */}
				<div
					className={`rbs1-shell ${
						displayKey === "DateTime" ? "rbs1-shell--tall" : ""
					}`}
					style={panelVars}
				>
					{/* On the Complete step too, unlike Style 1, which drops its rail
					    there: this one is the panel's own frame, and losing it would
					    leave the receipt floating in a box with no left column. Every
					    step reads as done beside it, which is what has just happened. */}
					{!hideNavigation && (
						<Sidebar
							brand={PANEL_BRAND}
							steps={displaySteps}
							currentStep={displayStep}
						/>
					)}

					<div className="rbs1-main">
						{!isConfirmationStep && !onAgentDetails && (
							<div className="rbs1-header">
								{/* Stands in for the rail once the layout drops it; hidden
								    above the breakpoint by the stylesheet. */}
								<StepProgress
									steps={displaySteps}
									currentStep={displayStep}
								/>
								<h2 className="rbs1-header__title">{headerTitle}</h2>
								<span className="rbs1-header__counter">
									{sprintf(
										/* translators: 1: current step number, 2: total number of steps */
										__("Step %1$d of %2$d", "rox-appointment-booking"),
										displayStep,
										displaySteps.length,
									)}
								</span>
							</div>
						)}

						{/* The two states above that drop the header leave this column
						    starting at the panel's own top edge, where the shell's corner
						    clips its scrollbar — see `.rbs1-body--flush`. */}
						<div
							ref={bodyRef}
							className={`rbs1-body ${
								isConfirmationStep || onAgentDetails ? "rbs1-body--flush" : ""
							}`}
						>
							{renderStep()}
						</div>

						{showNav && !isConfirmationStep && (
							<div
								className={`rbs1-footer ${
									displayStep === 1 ? "rbs1-footer--end" : ""
								}`}
							>
								{/* Nothing to go back to on the first step — but the extras
								    picker always has a way out, whichever step it opened
								    over. */}
								{(displayStep > 1 || onExtraServices) && (
									<button
										type="button"
										className="rbs1-btn rbs1-btn--secondary"
										onClick={onBack}
									>
										{backText}
									</button>
								)}
								<button
									type="button"
									className={`rbs1-btn rbs1-btn--primary ${nextHidden ? "rbs1-hidden" : ""}`}
									disabled={nextDisabled}
									onClick={onNext}
								>
									{nextText}
								</button>
							</div>
						)}

						{/* The Complete step's own footer: start again on the left, or go
						    on to the dashboard. `no-print` because the receipt above is
						    printable and these two are no part of it. */}
						{isConfirmationStep && (
							<div className="rbs1-footer no-print">
								<button
									type="button"
									className="rbs1-btn rbs1-btn--secondary"
									onClick={handleBookAnother}
								>
									{__("Book Another", "rox-appointment-booking")}
								</button>
								{showDashboardButton && (
									<button
										type="button"
										className="rbs1-btn rbs1-btn--primary"
										onClick={() => {
											const url =
												dashboardButtonUrl ||
												window.rox_appointment_booking?.config?.app?.dashboardUrl;
											if (url) {
												window.location.href = url;
											}
										}}
									>
										{dashboardButtonText ||
											__("Go to Dashboard", "rox-appointment-booking")}
									</button>
								)}
							</div>
						)}

						{/* The group-capacity sheet, as the last child of the step
						    column it covers. Gated on `attendeesOpen` alone: the engine
						    only ever opens it for a group service, and pairing it with
						    a flag read back out of the store would leave the sheet
						    unrendered for as long as the two disagreed. */}
						{attendeesOpen && (
							<Attendees
								maxCapacity={
									selectedService?.max_capacity
										? parseInt(selectedService.max_capacity, 10)
										: null
								}
								total={bookingProcess[0]?.attendees || 1}
								onChange={handleAttendeesChange}
								onConfirm={handleAttendeesConfirm}
								onClose={handleAttendeesClose}
							/>
						)}
					</div>
				</div>
			</div>
		</BookingStoreProvider>
	);
};

export default Style2Panel;
