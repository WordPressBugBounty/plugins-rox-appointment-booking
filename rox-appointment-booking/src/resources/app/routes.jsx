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
import { can } from "../config/env.js";
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
import RolesPage from "../pages/roles/RolesPage.jsx";

/**
 * The capability each page path requires, mirroring the gate on the endpoints
 * behind it. A path absent from this map is a Pro-registered route and is left
 * to whoever added it.
 *
 * "/my-bookings" asks for `appointment.view` because it is the caller's own
 * customer-side activity: anyone who may open the panel's appointment pages may
 * read their own bookings. `/services` is here too, but agents do NOT get the
 * admin Services page — `baseRoutes()` swaps in the read-only
 * `AgentServicesPage` for them.
 */
const PATH_CAPABILITIES = {
  "/": "dashboard.view",
  "/orders": "order.view",
  "/orders/:id": "order.view",
  "/appointment": "appointment.view",
  "/appointment/:id": "appointment.view",
  "/my-bookings": "appointment.view",
  "/calendar": "calendar.view",
  "/customers": "customer.view",
  "/services": "service.view",
  "/agents": "agent.view",
  "/locations": "location.view",
  "/coupons": "coupon.view",
  "/profile": "profile.view",
  "/global-settings": "settings.view",
  "/form-fields": "custom_field.view",
  "/integrations": "integration.view",
  "/roles": "role.view",
};

/**
 * Whether the current user may open a page path. Unknown paths (routes added by
 * add-ons through `HOOKS.pageRoutes`) are left alone.
 *
 * @param {string} path
 * @return {boolean}
 */
function mayOpen(path) {
  const capability = PATH_CAPABILITIES[path];
  return capability ? can(capability) : true;
}

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
    // Two different pages behind one path: whoever may edit services gets the
    // category/service editor, everyone else gets a read-only list of the
    // services assigned to them. Keyed on the capability rather than the role so
    // a role that earns `service.edit` reaches the real editor.
    {
      path: "/services",
      element: can("service.edit") ? <ServicesPage /> : <AgentServicesPage />,
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
    // Same arrangement as "/locations": the page is bundled here, Pro-gates
    // itself, and its endpoints are served by the Pro plugin's PHP.
    { path: "/roles", element: <RolesPage /> },
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

  // A page the user has no capability for renders a redirect instead of the real
  // component, so a direct URL hash cannot reach it either. This only shapes the
  // UI — every endpoint behind these pages enforces the same capability.
  return routes.map((route) =>
    mayOpen(route.path)
      ? route
      : { ...route, element: <Navigate to="/appointment" replace /> },
  );
}
