import React from "react";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./scss/app.scss";
import BookingService from "./../components/BookingService/index.jsx";


import { ConfigProvider } from "antd";
// Side effect: registers the WordPress-derived Day.js locale. Imported before
// any date is rendered so antd's pickers and every dayjs().format() pick it up.
import { getAntdLocale, siteLocale } from "../lib/locale.js";
import { parseIdList } from "../lib/idList.js";

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

const App = ({
  instanceId,
  type,
  hideNavigation,
  hideInfo,
  showBackground,
  backgroundColor,
  allowedLocationIds,
  allowedCategoryIds,
}) => {
  // Access frontend config from window object
  const config = window?.rox_appointment_booking?.config?.frontend || {};

  return (
    <ConfigProvider
      theme={themeConfig}
      locale={getAntdLocale()}
      direction={siteLocale.direction}
    >
      <div className="rox-appointment-booking-frontend" data-instance={instanceId}>
        {/* Frontend booking components will go here */}
        <BookingService
          instanceId={instanceId}
          type={type}
          hideNavigation={hideNavigation}
          hideInfo={hideInfo}
          showBackground={showBackground}
          backgroundColor={backgroundColor}
          allowedLocationIds={allowedLocationIds}
          allowedCategoryIds={allowedCategoryIds}
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
  // Absent attribute (the plain shortcode) keeps the frame — only an explicit
  // "false" from a surface that offers the toggle removes it.
  const showBackground = rootElement.dataset.showBackground !== "false";
  const backgroundColor = rootElement.dataset.backgroundColor || "";
  // Optional "only offer these" lists set by the block / Elementor widget.
  // Absent (the plain shortcode) or empty means every location / category.
  const allowedLocationIds = parseIdList(rootElement.dataset.locations);
  const allowedCategoryIds = parseIdList(rootElement.dataset.categories);

  root.render(
    <StrictMode>
      <App
        instanceId={instanceId}
        type={type}
        hideNavigation={hideNavigation}
        hideInfo={hideInfo}
        showBackground={showBackground}
        backgroundColor={backgroundColor}
        allowedLocationIds={allowedLocationIds}
        allowedCategoryIds={allowedCategoryIds}
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