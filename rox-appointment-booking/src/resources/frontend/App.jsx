import React from "react";
import { StrictMode, useMemo } from "react";
import { createRoot } from "react-dom/client";
import "./scss/app.scss";
import BookingService from "./../components/BookingService/index.jsx";


import { ConfigProvider } from "antd";
// Side effect: registers the WordPress-derived Day.js locale. Imported before
// any date is rendered so antd's pickers and every dayjs().format() pick it up.
import { getAntdLocale, siteLocale } from "../lib/locale.js";
import { dispatch } from "@wordpress/data";
import { getBookingServiceStore } from "../redux/booking-service-slice.js";
import { parseIdList } from "../lib/idList.js";
import { parsePanelContent } from "../lib/panelContent.js";
import { readAccent, withAccent } from "../lib/accentTheme.js";
import { registerLanguageMiddleware } from "../lib/apiLanguage.js";

// Registered at module scope, before any component can fire a request, so every
// public call carries the page's language. No-op on a single-language site.
registerLanguageMiddleware(
  window?.rox_appointment_booking?.config?.app?.language
);

const baseThemeConfig = {
  token: {
    borderRadius: 6,
    fontFamily: '"Heebo", sans-serif',
    colorPrimary: "#3560fb"
  },
  components: {
    Button: {
      colorPrimary: "rgb(53,96,251)",
      colorPrimaryHover: "rgb(81,117,248)",
      colorPrimaryActive: "rgb(53,96,251)"
    },
    Input: {
      colorBorder: "rgb(219,221,225)!important",
      activeBorderColor: "rgb(53,96,251)!important",
      borderRadiusSM: 6,
      controlHeight: 40,
      colorTextPlaceholder: "#97999E",
      colorText: "rgb(21,23,32)",
    },
    Select: {
      colorBorder: "rgb(219,221,225)",
      colorTextPlaceholder: "#494a4c",
      colorText: "rgb(21,23,32)",
    },
    DatePicker: {
      colorBorder: "rgb(219,221,225)",
      fontWeightStrong: 400,
      colorText: "rgb(0,0,0)",
      // The calendar is sized through antd's own tokens rather than by
      // overriding its CSS. antd derives the panel width from them —
      // cellWidth * 7 + pickerDatePanelPaddingHorizontal * 2 — so a stylesheet
      // that changes the padding without changing that computed width leaves
      // the grid and its gutters disagreeing, and one that changes both has to
      // keep guessing a relationship antd already knows. Defaults are 36 / 24 /
      // 18, which at 280px wide dwarf a 346px booking panel; these give a
      // 238px panel (30 * 7 + 14 * 2).
      cellWidth: 30,
      cellHeight: 26,
      pickerDatePanelPaddingHorizontal: 14,
    },
    Skeleton: {
      gradientFromColor: "rgb(226,229,239)",
      gradientToColor: "rgb(142,149,172)",
      paragraphLiHeight: 19
    }
  },
};

const App = ({
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
  fontFamily,
  accentColor,
  allowedLocationIds,
  allowedCategoryIds,
  singleAgentId,
  panelContent,
}) => {
  // Access frontend config from window object
  const config = window?.rox_appointment_booking?.config?.frontend || {};

  // antd renders selects, date pickers and toasts in a portal on <body>, where
  // the CSS variable set on the wrapper below cannot reach them — the theme
  // token is what carries the chosen font into those.
  const themeConfig = useMemo(() => {
    const themed = withAccent(baseThemeConfig, accentColor);

    return fontFamily
      ? { ...themed, token: { ...themed.token, fontFamily } }
      : themed;
  }, [fontFamily, accentColor]);

  return (
    <ConfigProvider
      theme={themeConfig}
      locale={getAntdLocale()}
      direction={siteLocale.direction}
    >
      <div
        className="rox-appointment-booking-frontend"
        data-instance={instanceId}
        // Every font-family in the panel stylesheet reads this variable and
        // falls back to Heebo, so an unset font leaves the panel as it was.
        style={fontFamily ? { "--rox-font-family": fontFamily } : undefined}
      >
        {/* Frontend booking components will go here */}
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
      </div>
    </ConfigProvider>
  );
};

// Roots keyed by their mount node, so a caller that tears a panel down again
// can reach the React root it needs to unmount. Without this a removed panel
// keeps its store subscription alive and goes on answering data effects from a
// detached tree — which is how two booking surfaces on one page end up
// overwriting each other's category/location lists.
const roots = new WeakMap();

// Mount the React app on a single root element (guarded against double-mount).
const mountRoot = (rootElement) => {
  if (!rootElement || rootElement.dataset.roxMounted === "true") {
    return;
  }
  rootElement.dataset.roxMounted = "true";

  const root = createRoot(rootElement);
  roots.set(rootElement, root);
  const instanceId = rootElement.dataset.instance;
  const type = rootElement.dataset.type;
  const hideNavigation = rootElement.dataset.hideNavigation === "true";
  // Service cards per row on the Services step. A missing / unparseable value
  // leaves the panel on the stylesheet's own default.
  const serviceColumns = parseInt(rootElement.dataset.serviceColumns, 10);
  // The confirmation screen's "Go to Dashboard" button. Absent attribute keeps
  // it on; an explicit "false" hides it. Blank text / url fall back to the
  // built-in label and the plugin's dashboard page.
  const showDashboardButton = rootElement.dataset.showDashboardButton !== "false";
  const dashboardButtonText = rootElement.dataset.dashboardButtonText || "";
  const dashboardButtonUrl = rootElement.dataset.dashboardButtonUrl || "";
  // Four comma-separated lengths each, top/right/bottom/left. Only read once
  // a column is hidden; the stylesheet is what enforces that.
  const contentMargin = rootElement.dataset.contentMargin || "";
  const headingAlign = rootElement.dataset.headingAlign || "";
  const headingMargin = rootElement.dataset.headingMargin || "";
  const hideInfo = rootElement.dataset.hideInfo === "true";
  // Absent attribute (the plain shortcode) keeps the frame — only an explicit
  // "false" from a surface that offers the toggle removes it.
  const showBackground = rootElement.dataset.showBackground !== "false";
  const backgroundColor = rootElement.dataset.backgroundColor || "";
  // A ready-to-use CSS font stack, already resolved server-side from the
  // FontFamily list. Absent (the plain shortcode) keeps the stylesheet default.
  const fontFamily = rootElement.dataset.fontFamily || "";
  const accentColor = readAccent(rootElement);
  // Optional "only offer these" lists set by the block / Elementor widget.
  // Absent (the plain shortcode) or empty means every location / category.
  const allowedLocationIds = parseIdList(rootElement.dataset.locations);
  const allowedCategoryIds = parseIdList(rootElement.dataset.categories);
  // Rewritten panel copy, as JSON. Absent (the plain shortcode) or unparsable
  // leaves every string on the panel's own wording.
  const panelContent = parsePanelContent(rootElement.dataset.panelContent);
  // Optional agent lock. `0` / absent means the normal flow, so anything that
  // does not parse to a positive id is treated as "no lock".
  const singleAgentId =
    parseInt(rootElement.dataset.agentId, 10) > 0
      ? parseInt(rootElement.dataset.agentId, 10)
      : null;

  root.render(
    <StrictMode>
      <App
        instanceId={instanceId}
        type={type}
        hideNavigation={hideNavigation}
        serviceColumns={serviceColumns > 0 ? serviceColumns : undefined}
        showDashboardButton={showDashboardButton}
        dashboardButtonText={dashboardButtonText}
        dashboardButtonUrl={dashboardButtonUrl}
        contentMargin={contentMargin}
        headingAlign={headingAlign}
        headingMargin={headingMargin}
        hideInfo={hideInfo}
        showBackground={showBackground}
        backgroundColor={backgroundColor}
        fontFamily={fontFamily}
        accentColor={accentColor}
        allowedLocationIds={allowedLocationIds}
        allowedCategoryIds={allowedCategoryIds}
        singleAgentId={singleAgentId}
        panelContent={panelContent}
      />
    </StrictMode>
  );
};

// Find all root elements and mount React app to each.
const mountAll = () => {
  document
    .querySelectorAll(".rox-appointment-booking-frontend-root")
    .forEach(mountRoot);
};

mountAll();

// Return a panel to a clean slate: clears its persisted sessionStorage entry
// and resets its store to the defaults.
//
// Lives here rather than in the caller because the store name is derived from
// the instance id, and only this bundle owns that mapping — the booking
// button's bundle cannot import the slice without registering a second copy of
// every store.
const resetRoot = (rootElement) => {
  if (!rootElement) {
    return;
  }

  try {
    dispatch(getBookingServiceStore(rootElement.dataset.instance))
      .clearSessionData();
  } catch (e) {
    // A root that was never mounted has no store to reset.
  }
};

// Tear a mounted panel down again, leaving the node free to be mounted afresh.
// Used by surfaces that swap one panel for another within a page — the booking
// button's modal does this when a different button is pressed.
const unmountRoot = (rootElement) => {
  const root = rootElement && roots.get(rootElement);

  if (!root) {
    return;
  }

  root.unmount();
  roots.delete(rootElement);
  delete rootElement.dataset.roxMounted;
};

// Expose mount helpers so dynamically-injected roots (e.g. the Elementor editor
// preview, which adds the widget after this script runs) can be mounted too.
window.roxAppointmentBooking = window.roxAppointmentBooking || {};
window.roxAppointmentBooking.mountRoot = mountRoot;
window.roxAppointmentBooking.mountAll = mountAll;
window.roxAppointmentBooking.unmountRoot = unmountRoot;
window.roxAppointmentBooking.resetRoot = resetRoot;

export default App;