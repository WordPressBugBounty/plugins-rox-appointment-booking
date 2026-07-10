import React, { StrictMode } from 'react';
import { createRoot } from 'react-dom/client';
import { ConfigProvider } from 'antd';
import { BrowserRouter } from 'react-router-dom';
import apiFetch from '@wordpress/api-fetch';
import Onboarding from './Onboarding/index.jsx';
import './scss/app.scss';
import './scss/onboarding.scss';

// On plain permalinks WordPress serves the REST API via `?rest_route=` instead
// of `/wp-json/`. The onboarding steps call `apiFetch({ path })`, so resolve
// every relative path against the PHP-provided `rest_url()` root (which is
// permalink-aware) and attach the `wp_rest` nonce. This makes onboarding
// requests work regardless of the site's permalink setting.
const roxRuntime = window.RoxAppointmentBooking || {};

if (roxRuntime.restUrl) {
  apiFetch.use((options, next) => {
    if (!options.url && typeof options.path === 'string') {
      let root = roxRuntime.restUrl;
      let path = options.path;
      if (root.indexOf('?') !== -1) {
        path = path.replace('?', '&');
      }
      if (!root.endsWith('/')) {
        root += '/';
      }
      path = path.replace(/^\//, '');
      const { path: _ignoredPath, ...rest } = options;
      return next({ ...rest, url: root + path });
    }
    return next(options);
  });
}

if (roxRuntime.restNonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(roxRuntime.restNonce));
}


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
     Message: {
      zIndexPopup: 999999
    },
  },
};

// Mount Onboarding component to the page
const rootElement = document.getElementById('rox-appointment-booking-onboarding');

if (rootElement) {
  const root = createRoot(rootElement);
  root.render(
    <StrictMode>
      <ConfigProvider theme={themeConfig}>
          <Onboarding />
        </ConfigProvider>
    </StrictMode>
  );
}
