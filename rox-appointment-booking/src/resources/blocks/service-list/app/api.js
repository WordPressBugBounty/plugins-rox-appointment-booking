import apiFetch from "@wordpress/api-fetch";

/**
 * Block-owned REST helpers. These talk to the same public endpoints the
 * shortcode uses, but the transport here is independent of the shortcode's
 * components/state.
 */

const appConfig = () => window?.rox_appointment_booking?.config?.app || {};

export const apiBase = () =>
  appConfig().apiBaseUrl || "/wp-json/rox-appointment-booking/v1/";

const unwrap = (res) => (res && res.success ? res.data : null);

export async function fetchServices() {
  const res = await apiFetch({ url: `${apiBase()}public/service?per_page=100` });
  return unwrap(res) || [];
}

export async function fetchAgents(serviceId) {
  const res = await apiFetch({
    url: `${apiBase()}public/agent?service_id=${encodeURIComponent(serviceId)}`,
  });
  return unwrap(res) || [];
}

/**
 * The booking-panel-structure response feeds the reused schedule calendar
 * (it reads `content.content.appointmentSchedulesApi` from the shared store).
 */
export async function fetchStructure() {
  const res = await apiFetch({ url: `${apiBase()}booking-panel-structure` });
  return res?.data || null;
}

export async function submitBooking(payload) {
  return apiFetch({
    url: `${apiBase()}public/booking`,
    method: "POST",
    data: payload,
  });
}
