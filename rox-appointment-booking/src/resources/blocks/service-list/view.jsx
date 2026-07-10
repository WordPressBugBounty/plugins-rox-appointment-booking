import React, { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import { ConfigProvider } from "antd";
import ServiceListApp from "./app/ServiceListApp.jsx";
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
        <ConfigProvider theme={themeConfig}>
          <ServiceListApp config={config} />
        </ConfigProvider>
      </StrictMode>,
    );
  });
