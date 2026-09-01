/**
 * On-demand loader for the Jodit rich-text editor.
 *
 * Jodit ships from `public/vendor/jodit/` as a plain script + stylesheet rather
 * than through the webpack bundle, for two reasons:
 *
 * 1. The build's CSSReplacementPlugin rewrites every `:root` selector in an
 *    emitted stylesheet to `:host`. Jodit keeps all of its design tokens in
 *    `:root` blocks, and the admin app is not in a shadow root, so a bundled
 *    copy would lose every custom property and render unstyled.
 * 2. `splitChunks` funnels all of node_modules into one `vendors` chunk shared
 *    by the public booking panel, so importing Jodit would ship ~700 KB to the
 *    frontend as well.
 *
 * Loading is deferred until an editor actually mounts, so the settings screen
 * only pays for it when the e-mail templates are opened.
 */

import { assetUrl } from "../config/env.js";

const SCRIPT_ID = "rox-appointment-booking-jodit-js";
const STYLE_ID = "rox-appointment-booking-jodit-css";

let pending = null;

/**
 * Add the stylesheet once. Resolves when it has loaded so the first editor is
 * never measured against unstyled markup.
 *
 * @return {Promise<void>}
 */
function loadStyle() {
  return new Promise((resolve) => {
    if (document.getElementById(STYLE_ID)) {
      resolve();
      return;
    }

    const link = document.createElement("link");
    link.id = STYLE_ID;
    link.rel = "stylesheet";
    link.href = assetUrl("vendor/jodit/jodit.min.css");
    // A missing stylesheet should not block the editor from appearing.
    link.onload = () => resolve();
    link.onerror = () => resolve();
    document.head.appendChild(link);
  });
}

/**
 * Add the script once.
 *
 * @return {Promise<void>}
 */
function loadScript() {
  return new Promise((resolve, reject) => {
    const existing = document.getElementById(SCRIPT_ID);

    if (existing) {
      if (window.Jodit) {
        resolve();
      } else {
        existing.addEventListener("load", () => resolve());
        existing.addEventListener("error", () =>
          reject(new Error("Jodit failed to load"))
        );
      }
      return;
    }

    const script = document.createElement("script");
    script.id = SCRIPT_ID;
    script.src = assetUrl("vendor/jodit/jodit.min.js");
    script.async = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Jodit failed to load"));
    document.head.appendChild(script);
  });
}

/**
 * Load Jodit and resolve with its global constructor. Repeat calls share one
 * in-flight request.
 *
 * @return {Promise<object>} The `Jodit` global.
 */
export function loadJodit() {
  if (window.Jodit) {
    return Promise.resolve(window.Jodit);
  }

  if (!pending) {
    pending = Promise.all([loadStyle(), loadScript()])
      .then(() => {
        if (!window.Jodit) {
          throw new Error("Jodit loaded but did not register a global");
        }
        return window.Jodit;
      })
      .catch((error) => {
        // Clear the cache so a later mount can retry after a transient failure.
        pending = null;
        throw error;
      });
  }

  return pending;
}
