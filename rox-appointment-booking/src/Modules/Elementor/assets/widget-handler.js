/**
 * Elementor frontend/editor handler for the Rox Appointment Booking Panel widget.
 *
 * The shared frontend bundle (app.js) mounts the panel once on load via
 * querySelectorAll. That misses widgets the Elementor editor injects after the
 * bundle has run (drag-in, control changes). This handler hooks Elementor's
 * `frontend/element_ready` event and mounts the panel on the widget's root, so
 * the real first step renders live in the editor — just like the Gutenberg block.
 *
 * Runs on both the editor preview and the published page; the bundle's
 * `roxMounted` guard prevents double-mounting.
 */
(function () {
  "use strict";

  var WIDGET_HANDLE = "rox-appointment-booking-panel";

  function mountElement($element) {
    var el = $element && $element[0] ? $element[0] : $element;
    if (!el) {
      return;
    }

    var root = el.querySelector(".rox-appointment-booking-frontend-root");
    if (!root) {
      return;
    }

    // The frontend bundle is a deferred webpack chunk; it may not have exposed
    // the mount helper yet. Retry shortly if so.
    if (
      window.roxAppointmentBooking &&
      typeof window.roxAppointmentBooking.mountRoot === "function"
    ) {
      window.roxAppointmentBooking.mountRoot(root);
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
