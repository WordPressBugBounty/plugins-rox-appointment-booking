/**
 * Thin REST helper for the bespoke real-component pages.
 *
 * This is the ONE place that knows the REST plumbing (base URL, namespace,
 * nonce, response envelope). Page-local APIs (`pages/<entity>/<entity>Api.js`)
 * build on top of it so they never repeat fetch/nonce boilerplate.
 *
 * The plugin's REST responses use the envelope
 * `{ code, success, message, data, options }` (see
 * `rox_appointment_booking_rest_response()`), so `request()` unwraps `data`
 * and throws a clean `Error(message)` when the call did not succeed.
 */

import apiFetch from "@wordpress/api-fetch";
import { api } from "../config/env.js";
import { dispatchNotificationsRefresh } from "./notificationEvents.js";

/**
 * Append a params object to a REST path as a query string. Empty values
 * (null/undefined/"") are skipped; arrays are appended as repeated keys.
 *
 * @param {string} path
 * @param {object} params
 * @return {string}
 */
function withParams(path, params) {
  if (!params || Object.keys(params).length === 0) {
    return path;
  }
  const search = new URLSearchParams();
  Object.keys(params).forEach((key) => {
    const value = params[key];
    if (value === null || value === undefined || value === "") {
      return;
    }
    if (Array.isArray(value)) {
      value.forEach((v) => search.append(key, v));
    } else {
      search.set(key, value);
    }
  });
  const query = search.toString();
  return query ? `${path}?${query}` : path;
}

/**
 * Call a plugin REST endpoint and return its unwrapped `data` payload.
 *
 * @param {string} path Path relative to the plugin namespace, e.g.
 *   "customer" or "customer/12".
 * @param {object} [options]
 * @param {string} [options.method] HTTP method (default "GET").
 * @param {object} [options.data] Request body for POST/PUT.
 * @param {object} [options.params] Query-string params for GET.
 * @return {Promise<*>} The response `data` field.
 * @throws {Error} With the response message when the call did not succeed.
 */
export async function request(path, { method = "GET", data, params } = {}) {
  const url = api(withParams(path, params));
  const response = await apiFetch({ url, method, data });

  const ok =
    response?.success === true ||
    response?.code === 200 ||
    response?.code === 201;

  if (!ok) {
    throw new Error(response?.message || "Request failed");
  }

  // A successful mutation (appointment/order/payment save, status change,
  // reschedule, ...) may have created a server-side notification, so nudge the
  // notification bell to re-fetch. GET reads never create notifications.
  if (method !== "GET") {
    dispatchNotificationsRefresh();
  }

  return response.data;
}

/**
 * Fetch a paginated list and normalise the response into the shape
 * `DataTable2.fetchPage` expects. The pagination envelope (`options.total`,
 * `options.page`, `options.per_page`) is REST plumbing, so the mapping lives
 * here rather than in every page-local API.
 *
 * @param {string} path
 * @param {object} [options]
 * @param {object} [options.params] Query-string params (page, filters, ...).
 * @return {Promise<{ rows: object[], total: number, page: number, perPage: number }>}
 */
export async function requestPage(path, { params } = {}) {
  const url = api(withParams(path, params));
  const response = await apiFetch({ url });

  const ok = response?.success === true || response?.code === 200;
  if (!ok) {
    throw new Error(response?.message || "Request failed");
  }

  const meta = response.options || {};
  return {
    rows: Array.isArray(response.data) ? response.data : [],
    total: meta.total ?? (Array.isArray(response.data) ? response.data.length : 0),
    page: meta.page ?? params?.page ?? 1,
    perPage: meta.per_page ?? 10,
  };
}
