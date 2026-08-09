/**
 * Real-component page route registry.
 *
 * This is the new routing model: each entry is simply `{ path, element }` — a
 * URL path and the React element to render there. As pages are migrated from the
 * legacy config-driven `View` engine to real components, their routes are added
 * to `baseRoutes()` here and removed from `config/routes.js`.
 *
 * The registry is passed through the `HOOKS.pageRoutes` filter so add-ons (the
 * Pro plugin) can append whole pages via the shared wp.hooks registry — the same
 * extension mechanism used elsewhere. Anything not matched by a registered page
 * falls through to the legacy `View` engine (see AppContent), so old and new
 * routing coexist during the migration.
 */

import React from "react";
import { Navigate } from "react-router-dom";
import { applyConfigFilters, HOOKS } from "../config/hooks.js";
import { userRoles, uiRole } from "../config/env.js";
import DashboardPage from "../pages/dashboard/DashboardPage.jsx";
import CustomersPage from "../pages/customers/CustomersPage.jsx";
import AgentsPage from "../pages/agents/AgentsPage.jsx";
import ServicesPage from "../pages/services/ServicesPage.jsx";
import AgentServicesPage from "../pages/services/agent/AgentServicesPage.jsx";
import AppointmentsPage from "../pages/appointments/AppointmentsPage.jsx";
import MyBookingsPage from "../pages/appointments/mybookings/MyBookingsPage.jsx";
import CalendarPage from "../pages/calendar/CalendarPage.jsx";
import OrdersPage from "../pages/orders/OrdersPage.jsx";
import ProfilePage from "../pages/profile/ProfilePage.jsx";
import SettingsPage from "../pages/settings/SettingsPage.jsx";
import FormFieldsPage from "../pages/settings/FormFieldsPage.jsx";
import IntegrationsPage from "../pages/settings/IntegrationsPage.jsx";
import LocationsPage from "../pages/locations/LocationsPage.jsx";
import CouponsPage from "../pages/coupons/CouponsPage.jsx";

/**
 * Whether the current user may reach the admin-only pages. Mirrors the PHP
 * `Security::canManageBookings()` gate (admin/manager). Agents and customers
 * fail this check and are confined to `RESTRICTED_USER_PATHS`.
 *
 * @return {boolean}
 */
function canAccessAdminRoutes() {
  const roles = userRoles();
  return (
    roles.includes("administrator") ||
    roles.includes("rox_appointment_booking_manager")
  );
}

/**
 * The only page paths agents and customers may open. Every other route (admin
 * pages, Pro pages) is redirected away for them, even via a direct URL hash.
 *
 * `/services` is on the list, but agents do NOT get the admin Services page —
 * `baseRoutes()` swaps in the read-only `AgentServicesPage` for them.
 */
const RESTRICTED_USER_PATHS = [
  "/appointment",
  "/appointment/:id",
  "/my-bookings",
  "/calendar",
  "/services",
  "/profile",
];

/**
 * The free plugin's migrated pages. Pages are added here as they move off the
 * legacy View engine; anything not listed still resolves through View.
 *
 * @return {Array<{path: string, element: React.ReactElement}>}
 */
function baseRoutes() {
  return [
    { path: "/", element: <DashboardPage /> },
    { path: "/customers", element: <CustomersPage /> },
    { path: "/agents", element: <AgentsPage /> },
    // Two different pages behind one path: admins get the category/service editor,
    // agents get a read-only list of the services assigned to them.
    {
      path: "/services",
      element: uiRole() === "agent" ? <AgentServicesPage /> : <ServicesPage />,
    },
    { path: "/appointment", element: <AppointmentsPage /> },
    // Deep-link target for notifications ("/appointment/{id}"): the same page,
    // which reads the :id param and opens the read-only view drawer on mount.
    { path: "/appointment/:id", element: <AppointmentsPage /> },
    // The flip side of "/appointment" for a panel user: the same grouped table
    // scoped to what they booked as a CUSTOMER rather than what they serve as an
    // agent. Menu item is agent-only (sidebar.js) — admins see every booking on
    // "/appointment" already — but the route stays open to them, where it simply
    // lists their own bookings, if any.
    { path: "/my-bookings", element: <MyBookingsPage /> },
    { path: "/calendar", element: <CalendarPage /> },
    { path: "/orders", element: <OrdersPage /> },
    // Deep-link target for payment/order notifications ("/orders/{id}").
    { path: "/orders/:id", element: <OrdersPage /> },
    
    // "/locations" is a Pro feature. The page is bundled here but Pro-gates itself:
    // when unlicensed (`isProUser()` false) LocationsPage renders the ProUser upsell
    // instead of the table, and its REST endpoints are served by the Pro plugin's
    // PHP — so it only works when Pro is active (= licensed).
    { path: "/locations", element: <LocationsPage /> },
    { path: "/coupons", element: <CouponsPage /> },
    { path: "/profile", element: <ProfilePage /> },
    { path: "/global-settings", element: <SettingsPage /> },
    { path: "/form-fields", element: <FormFieldsPage /> },
    { path: "/integrations", element: <IntegrationsPage /> },
  ];
}

/**
 * Build the final page-route list, after the `HOOKS.pageRoutes` extension
 * filter.
 *
 * @return {Array<{path: string, element: React.ReactElement}>}
 */
export function getPageRoutes() {
  const routes = applyConfigFilters(HOOKS.pageRoutes, baseRoutes());

  // Admins/managers get every page. Agents and customers are confined to their
  // own pages; any other route (admin or Pro) renders a redirect to their
  // default page instead of the real component.
  if (canAccessAdminRoutes()) {
    return routes;
  }

  return routes.map((route) =>
    RESTRICTED_USER_PATHS.includes(route.path)
      ? route
      : { ...route, element: <Navigate to="/appointment" replace /> },
  );
}
