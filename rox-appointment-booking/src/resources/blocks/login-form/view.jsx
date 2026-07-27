import React, { StrictMode } from "react";
import { createRoot } from "react-dom/client";
import LoginFormApp from "./app/LoginFormApp.jsx";
import "./app/login-form.scss";

const parseConfig = (el) => {
  try {
    return el.dataset.config ? JSON.parse(el.dataset.config) : {};
  } catch (e) {
    return {};
  }
};

/**
 * Mounts the login form on a single root element. The `rlfMounted` flag guards
 * against a double-mount (the Elementor editor can re-run its handler on the
 * same element).
 *
 * @param {HTMLElement} el Root element.
 * @return {void}
 */
const mountRoot = (el) => {
  if (!el || el.dataset.rlfMounted === "1") {
    return;
  }
  el.dataset.rlfMounted = "1";

  createRoot(el).render(
    <StrictMode>
      <LoginFormApp config={parseConfig(el)} />
    </StrictMode>,
  );
};

// Exposed so the Elementor handler can mount widgets injected after this bundle
// has already run (editor drag-in, control changes).
window.roxAppointmentLoginForm = window.roxAppointmentLoginForm || {};
window.roxAppointmentLoginForm.mountRoot = mountRoot;

document
  .querySelectorAll(".rox-appointment-booking-login-form-root")
  .forEach(mountRoot);
