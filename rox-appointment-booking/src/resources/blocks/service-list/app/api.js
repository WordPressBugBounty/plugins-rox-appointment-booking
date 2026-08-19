import apiFetch from "@wordpress/api-fetch";

/**
 * Block-owned REST helpers. These talk to the same public endpoints the
 * shortcode uses, but the transport here is independent of the shortcode's
 * components/state.
 */

// Namespace-relative `path`, never a hand-built URL: `edit.jsx` calls these from
// the block editor, where the block's config inline script is NOT printed (it
// hangs off the view handle only). A site-root fallback like
// "/wp-json/rox-appointment-booking/v1/" therefore 404s on every install where
// WordPress does not sit at the domain root (…/booking/) or where permalinks are
// plain. apiFetch resolves `path` against the REST root WordPress printed.
const NAMESPACE = "/rox-appointment-booking/v1/";

const req = (path, options = {}) =>
  apiFetch({ path: `${NAMESPACE}${path}`, ...options });

const unwrap = (res) => (res && res.success ? res.data : null);

export async function fetchServices() {
  const res = await req("public/service?per_page=100");
  return unwrap(res) || [];
}

export async function fetchAgents(serviceId) {
  const res = await req(
    `public/agent?service_id=${encodeURIComponent(serviceId)}`,
  );
  return unwrap(res) || [];
}

/**
 * The booking-panel-structure response feeds the reused schedule calendar
 * (it reads `content.content.appointmentSchedulesApi` from the shared store).
 */
export async function fetchStructure() {
  const res = await req("booking-panel-structure");
  return res?.data || null;
}

export async function submitBooking(payload) {
  const res = await req("public/booking", {
    method: "POST",
    data: payload,
  });

  // The response may have logged the customer in (auto login setting). The page
  // still holds the logged-out nonce, which WordPress rejects once the auth
  // cookie is present, so swap in the fresh one before any further call.
  const autoLogin = res?.data?.auto_login;
  if (autoLogin?.logged_in && autoLogin?.nonce) {
    if (apiFetch.nonceMiddleware) {
      apiFetch.nonceMiddleware.nonce = autoLogin.nonce;
    }
    if (window.wpApiSettings) {
      window.wpApiSettings.nonce = autoLogin.nonce;
    }
  }

  return res;
}
