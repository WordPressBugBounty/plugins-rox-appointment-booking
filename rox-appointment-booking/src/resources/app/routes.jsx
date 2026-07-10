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
import { applyConfigFilters, HOOKS } from "../config/hooks.js";
import DashboardPage from "../pages/dashboard/DashboardPage.jsx";
import CustomersPage from "../pages/customers/CustomersPage.jsx";
import AgentsPage from "../pages/agents/AgentsPage.jsx";
import ServicesPage from "../pages/services/ServicesPage.jsx";
import AppointmentsPage from "../pages/appointments/AppointmentsPage.jsx";
import CalendarPage from "../pages/calendar/CalendarPage.jsx";
import OrdersPage from "../pages/orders/OrdersPage.jsx";
import ProfilePage from "../pages/profile/ProfilePage.jsx";
import SettingsPage from "../pages/settings/SettingsPage.jsx";
import LocationsPage from "../pages/locations/LocationsPage.jsx";
import CouponsPage from "../pages/coupons/CouponsPage.jsx";

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
    { path: "/services", element: <ServicesPage /> },
    { path: "/appointment", element: <AppointmentsPage /> },
    // Deep-link target for notifications ("/appointment/{id}"): the same page,
    // which reads the :id param and opens the read-only view drawer on mount.
    { path: "/appointment/:id", element: <AppointmentsPage /> },
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
  ];
}

/**
 * Build the final page-route list, after the `HOOKS.pageRoutes` extension
 * filter.
 *
 * @return {Array<{path: string, element: React.ReactElement}>}
 */
export function getPageRoutes() {
  return applyConfigFilters(HOOKS.pageRoutes, baseRoutes());
}
