/**
 * Elementor frontend/editor handler for the Rox Appointment Login Form widget.
 *
 * The login-form view bundle mounts every root once on load via
 * querySelectorAll. That misses widgets the Elementor editor injects after the
 * bundle has run (drag-in, control changes). This handler hooks Elementor's
 * `frontend/element_ready` event and mounts the form on the widget's root, so
 * the real form renders live in the editor.
 *
 * Kept separate from `widget-handler.js` (the booking panel's) so it can depend
 * on the login-form bundle alone — a page with only the login form never loads
 * the booking panel's frontend bundle.
 *
 * Runs on both the editor preview and the published page; the bundle's
 * `rlfMounted` guard prevents double-mounting.
 */
(function () {
  "use strict";

  var WIDGET_HANDLE = "rox-appointment-login-form";

  function mountElement($element) {
    var el = $element && $element[0] ? $element[0] : $element;
    if (!el) {
      return;
    }

    var root = el.querySelector(".rox-appointment-booking-login-form-root");
    if (!root) {
      return;
    }

    // The view bundle is a deferred webpack chunk; it may not have exposed the
    // mount helper yet. Retry shortly if so.
    if (
      window.roxAppointmentLoginForm &&
      typeof window.roxAppointmentLoginForm.mountRoot === "function"
    ) {
      window.roxAppointmentLoginForm.mountRoot(root);
    } else {
      setTimeout(function () {
        mountElement(el);
      }, 100);
    }
  }

  function register() {
    if (
      typeof window.elementorFrontend === "undefined" ||
      !window.elementorFrontend.hooks
    ) {
      setTimeout(register, 100);
      return;
    }

    window.elementorFrontend.hooks.addAction(
      "frontend/element_ready/" + WIDGET_HANDLE + ".default",
      mountElement
    );
  }

  register();
})();
