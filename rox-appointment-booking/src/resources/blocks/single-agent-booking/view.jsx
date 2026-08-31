import React, { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { ConfigProvider } from "antd";
import BookingService from "../../components/BookingService/index.jsx";
// Side effect: registers the WordPress-derived Day.js locale. Imported before
// any date is rendered so antd's pickers and every dayjs().format() pick it up.
import { getAntdLocale, siteLocale } from "../../lib/locale.js";
import { ensureWebFont } from "../../lib/webFont.js";
import { readAccent, withAccent } from "../../lib/accentTheme.js";
// Same base stylesheet the frontend App loads — sets box-sizing:border-box +
// the Heebo font on `.rox-appointment-booking-frontend` so the reused panel
// (slots, inputs) renders identically to the main booking panel.
import "../../frontend/scss/app.scss";

/**
 * Frontend entry for the Single Agent Booking Panel.
 *
 * Mounts the SHARED booking panel (`components/BookingService`) in single-agent
 * mode on every `.rox-appointment-booking-single-agent-booking-root` node
 * printed by the shortcode / block / Elementor widget. It is the exact same
 * component the normal booking panel uses — single-agent mode (agent locked +
 * agent info card in place of the step sidebar) is driven entirely by the
 * `singleAgentId` / `singleAgentConfig` props derived from the surface's
 * `data-config`. The root class is unique to this feature so it never collides
 * with the booking-panel, service-list or login-form roots.
 */

// Matches the frontend App's antd theme so the reused panel renders identically.
const baseThemeConfig = {
  token: {
    borderRadius: 6,
    fontFamily: '"Heebo", sans-serif',
    colorPrimary: "#3560fb",
  },
  components: {
    Button: {
      colorPrimary: "rgb(53,96,251)",
      colorPrimaryHover: "rgb(81,117,248)",
      colorPrimaryActive: "rgb(53,96,251)",
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
      // Kept in step with the booking panel's own ConfigProvider (see
      // frontend/App.jsx) — this surface renders the same customer form, so the
      // calendar has to come out the same size. 30 * 7 + 14 * 2 = a 238px panel.
      cellWidth: 30,
      cellHeight: 26,
      pickerDatePanelPaddingHorizontal: 14,
    },
    Skeleton: {
      gradientFromColor: "rgb(226,229,239)",
      gradientToColor: "rgb(142,149,172)",
      paragraphLiHeight: 19,
    },
  },
};

const parseConfig = (el) => {
  try {
    return el.dataset.config ? JSON.parse(el.dataset.config) : {};
  } catch (e) {
    return {};
  }
};

// Mount the panel on a single root element (guarded against double-mount).
const mountRoot = (el) => {
  if (!el || el.dataset.roxMounted === "true") {
    return;
  }
  el.dataset.roxMounted = "true";

  const config = parseConfig(el);
  const agentId = config.agentId ? Number(config.agentId) : null;

  // A resolved CSS stack, already validated server-side against the shared
  // font list. Empty keeps the panel on the stylesheet's own font.
  const fontFamily = config.fontFamily || "";

  // The Elementor editor re-renders the widget over AJAX, where the enqueue
  // PHP does on page load never arrives — so load the face from the config too.
  // Deduped on the href, so the link PHP printed gains no second copy.
  ensureWebFont(el.ownerDocument, config.fontUrl);

  // antd renders selects, date pickers and toasts in a portal on <body>, where
  // the CSS variable set on the wrapper below cannot reach them — the theme
  // token is what carries the chosen font into those.
  // The accent the surface chose, read off the cascade: the Elementor control
  // writes it as a CSS variable and the block as an inline style.
  const accent = readAccent(el);

  const themed = withAccent(baseThemeConfig, accent);

  const themeConfig = fontFamily
    ? { ...themed, token: { ...themed.token, fontFamily } }
    : themed;

  createRoot(el).render(
    <StrictMode>
      <ConfigProvider
        theme={themeConfig}
        locale={getAntdLocale()}
        direction={siteLocale.direction}
      >
        <div
          className="rox-appointment-booking-frontend"
          data-instance={el.dataset.instance}
          // Every font-family in the panel stylesheet reads this variable and
          // falls back to Heebo, so an unset font leaves the panel as it was.
          style={fontFamily ? { "--rox-font-family": fontFamily } : undefined}
        >
          <BookingService
            // Decides which store this panel gets, so two single-agent panels
            // on a page keep their selections apart. Prefixed because every
            // surface numbers its instances from 1 independently.
            instanceId={`agent-${el.dataset.instance || "1"}`}
            singleAgentId={agentId}
            singleAgentConfig={config}
            // Frame settings live in the same data-config blob, but the panel
            // takes them as plain props so every surface feeds it the same way.
            showBackground={config.showBackground !== false}
            backgroundColor={config.backgroundColor || ""}
          />
        </div>
      </ConfigProvider>
    </StrictMode>,
  );
};

const mountAll = () => {
  document
    .querySelectorAll(".rox-appointment-booking-single-agent-booking-root")
    .forEach(mountRoot);
};

mountAll();

// Expose mount helpers so dynamically-injected roots (e.g. the Elementor editor
// preview, added after this script runs) can be mounted too.
window.roxAppointmentBookingSingleAgent =
  window.roxAppointmentBookingSingleAgent || {};
window.roxAppointmentBookingSingleAgent.mountRoot = mountRoot;
window.roxAppointmentBookingSingleAgent.mountAll = mountAll;
