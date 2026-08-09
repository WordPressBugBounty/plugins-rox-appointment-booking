import apiFetch from "@wordpress/api-fetch";

/**
 * Thin `apiFetch` wrappers around the public auth REST endpoints the standalone
 * login form uses. Every url + the nonce come from the mount `data-config`
 * (built by `Modules\LoginForm\Services\LoginFormConfig`), so this module
 * never reads a global.
 *
 * The endpoints are public (`permissionCheck() => true`), but the nonce is sent
 * anyway so WordPress treats the call as coming from the current session.
 */

/**
 * POSTs to one of the config's REST urls.
 *
 * @param {string} url   Absolute REST url.
 * @param {object} data  Request body.
 * @param {string} nonce `wp_rest` nonce.
 * @return {Promise<object>} Parsed response body.
 */
const post = (url, data, nonce) =>
  apiFetch({
    url,
    method: "POST",
    data,
    headers: nonce ? { "X-WP-Nonce": nonce } : {},
  });

/**
 * Logs a customer in. The endpoint sets the WordPress auth cookie on success.
 *
 * @param {object} config      Mount config.
 * @param {object} credentials `{ email, password }`.
 * @return {Promise<object>} `{ success, code, message, data }`.
 */
export const login = (config, credentials) =>
  post(config.loginApi, credentials, config.nonce);

/**
 * Asks for a password reset link. `reset_page_url` is the current page, so the
 * emailed link comes back here instead of `wp-login.php` (the endpoint only
 * accepts same-site urls).
 *
 * @param {object} config Mount config.
 * @param {string} email  Account email address.
 * @return {Promise<object>} `{ success, code, message, data }`.
 */
export const requestReset = (config, email) =>
  post(
    config.resetRequestApi,
    { email, reset_page_url: window.location.origin + window.location.pathname },
    config.nonce,
  );

/**
 * Sets a new password from a reset link.
 *
 * @param {object} config      Mount config.
 * @param {object} resetParams `{ key, login }` read from the url.
 * @param {string} password    New password.
 * @return {Promise<object>} `{ success, code, message, data }` — `data.email` on success.
 */
export const setNewPassword = (config, resetParams, password) =>
  post(
    config.setPasswordApi,
    { key: resetParams.key, login: resetParams.login, password },
    config.nonce,
  );

/**
 * Normalises an error thrown by `apiFetch` into a displayable message. A non-2xx
 * response rejects with the parsed body, so its `message` is the server's own
 * text (e.g. "Invalid email or password").
 *
 * @param {*}      error    Thrown value.
 * @param {string} fallback Message to use when none can be read.
 * @return {string}
 */
export const errorMessage = (error, fallback) =>
  error?.message || error?.response?.message || fallback;
