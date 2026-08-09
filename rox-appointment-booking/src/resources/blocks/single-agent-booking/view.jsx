import React, { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { ConfigProvider } from "antd";
import BookingService from "../../components/BookingService/index.jsx";
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
const themeConfig = {
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

  createRoot(el).render(
    <StrictMode>
      <ConfigProvider theme={themeConfig}>
        <div
          className="rox-appointment-booking-frontend"
          data-instance={el.dataset.instance}
        >
          <BookingService singleAgentId={agentId} singleAgentConfig={config} />
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
