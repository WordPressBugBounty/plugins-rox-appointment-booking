import apiFetch from "@wordpress/api-fetch";

// Thin fetch helper for the Customer Panel's own REST endpoints
// (/customer-panel/…). Uses the nonce + apiBaseUrl injected by
// CustomerPanelApp::panelVars() so authenticated endpoints accept our requests
// (their permissionCheck verifies X-WP-Nonce). Phase D wires each view to this.
const cfg =
  (window.rox_appointment_booking &&
    window.rox_appointment_booking.config &&
    window.rox_appointment_booking.config.customerPanel) ||
  {};

// Register the REST nonce once for every apiFetch call from this bundle.
if (cfg.nonce) {
  apiFetch.use(apiFetch.createNonceMiddleware(cfg.nonce));
}

// Plugin REST base, e.g. https://site/wp-json/rox-appointment-booking/v1
const BASE = (cfg.apiBaseUrl || "/wp-json/rox-appointment-booking/v1/").replace(
  /\/$/,
  ""
);

// `path` is relative to the plugin REST namespace, e.g. "customer-panel/bookings".
function url(path, params) {
  const qs = params ? `?${new URLSearchParams(params).toString()}` : "";
  return `${BASE}/${path}${qs}`;
}

export function apiGet(path, params) {
  return apiFetch({ url: url(path, params) });
}

export function apiPost(path, data) {
  return apiFetch({ url: url(path), method: "POST", data });
}

// Multipart POST (file uploads). `body` must be a FormData; apiFetch leaves the
// Content-Type unset so the browser adds the multipart boundary itself.
export function apiUpload(path, body) {
  return apiFetch({ url: url(path), method: "POST", body });
}
