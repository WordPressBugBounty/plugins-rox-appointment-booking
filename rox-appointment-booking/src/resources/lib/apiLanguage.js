/**
 * Sends the page's language along with every REST call a public bundle makes.
 *
 * The booking panel and customer panel call `apiFetch` from a dozen different
 * components, so rather than touching each call site this registers ONE
 * middleware that appends `lang` to all of them. `AbstractREST::secureHandleRequest()`
 * is the matching single choke point on the PHP side: it whitelists the value
 * against the site's active languages and switches WPML for the duration of the
 * request.
 *
 * Deliberately not used by the wp-admin bundle. Admin screens must show the
 * source language (otherwise editing a record while reading a translation would
 * overwrite the original), and WPML resolves the admin language server-side
 * anyway.
 */

import apiFetch from "@wordpress/api-fetch";

let registered = false;

/**
 * Append `lang` to a URL, leaving an existing value alone.
 *
 * @param {string} url
 * @param {string} language
 * @return {string}
 */
function withLang(url, language) {
  // Only same-origin plugin calls are ours to annotate; anything absolute and
  // foreign is left untouched.
  const separator = url.includes("?") ? "&" : "?";
  return /[?&]lang=/.test(url) ? url : `${url}${separator}lang=${encodeURIComponent(language)}`;
}

/**
 * Register the middleware once per page load.
 *
 * @param {string} language Language code injected by PHP. Falsy values are a
 *   no-op, so a single-language site adds nothing to its requests.
 * @return {void}
 */
export function registerLanguageMiddleware(language) {
  if (registered || !language) {
    return;
  }
  registered = true;

  apiFetch.use((options, next) => {
    // apiFetch takes either `url` (absolute) or `path` (relative to the REST
    // root); both are in play across these bundles, so handle each.
    if (typeof options.url === "string") {
      return next({ ...options, url: withLang(options.url, language) });
    }
    if (typeof options.path === "string") {
      return next({ ...options, path: withLang(options.path, language) });
    }
    return next(options);
  });
}
