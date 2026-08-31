/**
 * Elementor frontend handler for the booking panel widget's popup mode.
 *
 * The trigger's view bundle wires every button it can find once, on load. That
 * misses widgets the Elementor editor injects afterwards (drag-in, control
 * changes), so this hooks Elementor's `frontend/element_ready` event and wires
 * the widget's own trigger as it appears. A widget in general mode has no
 * trigger inside it, so nothing is wired and the pass costs nothing.
 *
 * Nothing is wired inside the editor preview itself: there, a click is how an
 * editor selects the widget, and opening a full-screen booking modal every time
 * would make the widget impossible to work with. The view bundle applies the
 * same rule, so the two agree.
 */
(function () {
  "use strict";

  var WIDGET_HANDLE = "rox-appointment-booking-panel";

  function isEditPreview() {
    try {
      return (
        window.location.search.indexOf("elementor-preview=") > -1 ||
        (window.elementorFrontend &&
          typeof window.elementorFrontend.isEditMode === "function" &&
          window.elementorFrontend.isEditMode())
      );
    } catch (e) {
      return false;
    }
  }

  function mountElement($element, attempt) {
    if (isEditPreview()) {
      return;
    }

    var el = $element && $element[0] ? $element[0] : $element;
    if (!el) {
      return;
    }

    // The view bundle is a deferred webpack chunk; it may not have exposed the
    // mount helper yet. Retry shortly if so, then give up rather than spin.
    var api = window.roxAppointmentBookingButton;
    if (api && typeof api.mountButton === "function") {
      api.mountButton(el);
      return;
    }

    var next = (attempt || 0) + 1;
    if (next > 50) {
      return;
    }

    setTimeout(function () {
      mountElement(el, next);
    }, 100);
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
      function ($element) {
        mountElement($element, 0);
      }
    );
  }

  register();
})();
