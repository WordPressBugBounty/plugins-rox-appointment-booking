import React, { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { ConfigProvider } from "antd";
import ServiceListApp from "./app/ServiceListApp.jsx";
// Side effect: registers the WordPress-derived Day.js locale. Imported before
// any date is rendered so antd's pickers and every dayjs().format() pick it up.
import { getAntdLocale, siteLocale } from "../../lib/locale.js";
import "./app/service-list.scss";

const themeConfig = {
  token: {
    borderRadius: 5,
    fontFamily: '"Heebo", sans-serif',
    colorPrimary: "#3560fb",
  },
};

const parseConfig = (el) => {
  try {
    return el.dataset.config ? JSON.parse(el.dataset.config) : {};
  } catch (e) {
    return {};
  }
};

document
  .querySelectorAll(".rox-appointment-booking-service-list-root")
  .forEach((el) => {
    const config = parseConfig(el);

    createRoot(el).render(
      <StrictMode>
        <ConfigProvider
          theme={themeConfig}
          locale={getAntdLocale()}
          direction={siteLocale.direction}
        >
          <ServiceListApp config={config} />
        </ConfigProvider>
      </StrictMode>,
    );
  });
