import React from "react";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./scss/app.scss";
import BookingService from "./../components/BookingService/index.jsx";


import { ConfigProvider } from "antd";

const themeConfig = {
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
      colorText: "rgb(0,0,0)"
    },
    Skeleton: {
      gradientFromColor: "rgb(226,229,239)",
      gradientToColor: "rgb(142,149,172)",
      paragraphLiHeight: 19
    }
  },
};

const App = ({ instanceId, type, hideNavigation, hideInfo }) => {
  // Access frontend config from window object
  const config = window?.rox_appointment_booking?.config?.frontend || {};

  return (
    <ConfigProvider theme={themeConfig}>
      <div className="rox-appointment-booking-frontend" data-instance={instanceId}>
        {/* Frontend booking components will go here */}
        <BookingService
          instanceId={instanceId}
          type={type}
          hideNavigation={hideNavigation}
          hideInfo={hideInfo}
        />
      </div>
    </ConfigProvider>
  );
};

// Mount the React app on a single root element (guarded against double-mount).
const mountRoot = (rootElement) => {
  if (!rootElement || rootElement.dataset.roxMounted === "true") {
    return;
  }
  rootElement.dataset.roxMounted = "true";

  const root = createRoot(rootElement);
  const instanceId = rootElement.dataset.instance;
  const type = rootElement.dataset.type;
  const hideNavigation = rootElement.dataset.hideNavigation === "true";
  const hideInfo = rootElement.dataset.hideInfo === "true";

  root.render(
    <StrictMode>
      <App
        instanceId={instanceId}
        type={type}
        hideNavigation={hideNavigation}
        hideInfo={hideInfo}
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

// Expose mount helpers so dynamically-injected roots (e.g. the Elementor editor
// preview, which adds the widget after this script runs) can be mounted too.
window.roxAppointmentBooking = window.roxAppointmentBooking || {};
window.roxAppointmentBooking.mountRoot = mountRoot;
window.roxAppointmentBooking.mountAll = mountAll;

export default App;