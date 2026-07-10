import React, { useCallback, useEffect, useState } from "react";
import { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import "./scss/app.scss";

import { ConfigProvider, message } from "antd";
import { HashRouter } from "react-router-dom";
import AppContent from "./components/layout/AppContent.jsx";
import { NotificationProvider } from "./contexts/NotificationContext.jsx";
import { getSidebarConfig } from "./config/sidebar.js";
import { exposeHookApi } from "./config/hooks.js";
import { exposeSlotApi } from "./app/slots.jsx";

// Publish the namespaced hook + slot APIs on `window` as early as possible so
// add-ons can register config filters and slot fills before the admin shell
// renders. The underlying hooks live on the shared `wp.hooks` global, so any
// add-on shares one instance regardless of load order.
exposeHookApi();
exposeSlotApi();

const themeConfig = {
  token: {
    borderRadius: 5,
    fontFamily: '"Heebo", sans-serif',
    colorPrimary: "#3560fb",
    colorBorder: "rgb(219,221,225)"
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
      borderRadiusSM: 5,
      controlHeight: 40,
      colorTextPlaceholder: "#97999E",
      colorText: "rgb(21,23,32)",
    },
    InputNumber: {
      colorBorder: "rgb(219,221,225)",
    },
    Select: {
      colorBorder: "rgb(219,221,225)",
      colorTextPlaceholder: "#494a4c",
      colorText: "rgb(21,23,32)",
      optionActiveBg: "#F3F4F9"
    },
    DatePicker: {
      colorBorder: "rgb(219,221,225)",
      fontWeightStrong: 400,
      colorText: "rgb(0,0,0)"
    },
    Switch: {
      colorPrimary: "rgb(53,96,251)",
      colorPrimaryHover: "rgb(53,96,251)",
      colorPrimaryBorder: "rgb(34, 113, 177)",
    },
    Drawer: {
      zIndexPopup: 99999,
      colorBgElevated: "rgb(243,244,249)"
    },
    Table: {
      borderColor: "rgb(245,247,250)",
      colorText: "rgb(41,45,50)",
      rowHoverBg: "rgba(255, 255, 255, 0.70) !important",
    },
    Card: {
      bodyPadding: 0,
    },
    Tabs: {
      inkBarColor: "rgb(7,131,190)",
      itemSelectedColor: "rgb(7,131,190)",
      titleFontSize: 13,
      colorText: "rgb(102,112,133)",
      itemHoverColor: "rgb(29,41,57)",
    },
    Layout: {
      siderBg: "#ffffff",
      headerBg: "#ffffff",
    },
    Menu: {
      itemBg: "transparent",
      itemColor: "#344054",
      itemHoverBg: "#F3F4F6",
      itemHoverColor: "#374151",
      itemSelectedBg: "rgb(235,239,255)",
      itemSelectedColor: "#3057E4",
      colorText: "rgb(52,64,84)",
      iconSize: 16,
      fontSize: 16,
      itemBorderRadius: 6,
    },
     Message: {
      zIndexPopup: 999999
    },
    Radio: {
      colorPrimary: "rgb(53,96,251)",
      fontSize: 16
    },
    Skeleton: {
      gradientFromColor: "rgb(226,229,239)",
      gradientToColor: "rgb(215,215,217)",
      paragraphLiHeight: 19
    },
    Tooltip: {
      lineHeight: 1,
      paddingXS: 6,
      controlHeight: 30,
      colorBgSpotlight: "rgba(0, 0, 0, 0.9)",
    },
    Checkbox: {
      controlInteractiveSize: 20,
      lineWidth: 2
    }
  },
};

const App = () => {
  // Config is now derived synchronously from static JS (config/*.js) instead of
  // being fetched from the old /structure/app-config and /structure/main-json
  // endpoints. The getters read the values PHP injects on `window` and run the
  // Pro extension filters, so there is no loading/error state at boot.
  const [appConfig, setAppConfig] = useState(() => getSidebarConfig());

  // Recompute the static config in place. The window flags it depends on don't
  // change without a reload, but recomputing keeps the existing
  // "refresh-runtime-config" event contract intact and re-applies Pro filters.
  const refreshRuntimeConfigs = useCallback(() => {
    setAppConfig(getSidebarConfig());
  }, []);

  // Configure message to render in the root container with high z-index
  useEffect(() => {
    message.config({
      top: 24,
      duration: 3,
      maxCount: 3,
      getContainer: () => document.getElementById("rox-appointment-booking-app-root"),
    });
  }, []);

  // Allow children to trigger sidebar/routes refresh without full page reload
  useEffect(() => {
    window.addEventListener(
      "rox-appointment-booking:refresh-runtime-config",
      refreshRuntimeConfigs
    );

    return () => {
      window.removeEventListener(
        "rox-appointment-booking:refresh-runtime-config",
        refreshRuntimeConfigs
      );
    };
  }, [refreshRuntimeConfigs]);

  if (!appConfig) return null;

  // Extract notification URLs from config
  const notificationAction = appConfig?.topbar?.actions?.find(
    (action) => action.type === "notification"
  );
  const notificationApiUrl = notificationAction?.props?.apiUrl || null;

  return (
    <NotificationProvider notificationApiUrl={notificationApiUrl}>
      <AppContent appConfig={appConfig} />
    </NotificationProvider>
  );
};

// Root element for the React app
const rootElement = document.querySelector("#rox-appointment-booking-app-root");
const root = createRoot(rootElement);

// Function to render the app
const render = () => {
  root.render(
    <StrictMode>
      <ConfigProvider theme={themeConfig}>
        <HashRouter>
          <App />
        </HashRouter>
      </ConfigProvider>
    </StrictMode>
  );
};

// Initial render — deferred to DOMContentLoaded so extension bundles (e.g. the
// Pro plugin) that register config filters (`addFilter` on the shared wp.hooks)
// during page load are in place before the routes/sidebar config is first
// computed. When the document is already parsed, render immediately. Running
// without any extension simply renders with no extra filters.
if (document.readyState === "loading") {
  document.addEventListener("DOMContentLoaded", render);
} else {
  render();
}
