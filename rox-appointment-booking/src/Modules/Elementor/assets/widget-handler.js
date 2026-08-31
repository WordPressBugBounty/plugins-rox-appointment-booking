/**
 * Elementor frontend/editor handler for the Rox Appointment Booking Panel widget.
 *
 * The shared frontend bundle (app.js) mounts the panel once on load via
 * querySelectorAll. That misses widgets the Elementor editor injects after the
 * bundle has run (drag-in, control changes). This handler hooks Elementor's
 * `frontend/element_ready` event and mounts the panel on the widget's root, so
 * the real first step renders live in the editor — just like the Gutenberg block.
 *
 * A widget in popup mode has no panel root inside it (the modal builds one on
 * click), so nothing is mounted and the pass costs nothing.
 *
 * Runs on both the editor preview and the published page; the bundle's
 * `roxMounted` guard prevents double-mounting.
 */
(function () {
  "use strict";

  var WIDGET_HANDLE = "rox-appointment-booking-panel";

  // The editor re-renders this widget over AJAX whenever a control changes, so
  // the web font PHP enqueued on page load never arrives for a newly picked
  // one. Add it from the mount node instead, deduped by href so the published
  // page — where PHP did print the link — gains no second copy.
  function ensureFont(root) {
    var href = root.dataset ? root.dataset.fontUrl : "";
    var doc = root.ownerDocument;

    if (!href || !doc || doc.querySelector('link[href="' + href + '"]')) {
      return;
    }

    var link = doc.createElement("link");
    link.rel = "stylesheet";
    link.href = href;
    doc.head.appendChild(link);
  }

  function mountElement($element) {
    var el = $element && $element[0] ? $element[0] : $element;
    if (!el) {
      return;
    }

    var root = el.querySelector(".rox-appointment-booking-frontend-root");
    if (!root) {
      return;
    }

    ensureFont(root);

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
