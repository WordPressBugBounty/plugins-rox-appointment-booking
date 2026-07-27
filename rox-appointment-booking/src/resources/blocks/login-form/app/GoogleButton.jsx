import React, { useEffect, useRef, useState } from "react";
import apiFetch from "@wordpress/api-fetch";

/**
 * "Sign in with Google" button for the standalone login form.
 *
 * Copied from `components/BookingService/GoogleLoginButton.jsx` rather than
 * imported — this bundle is self-contained and imports nothing from
 * `BookingService/`. Only rendered when `config.google.enabled`, i.e. when a Pro
 * shipping the Google-login backend answered the config filter.
 *
 * Like the password login, the Pro endpoint sets the WordPress auth cookie, so
 * the parent only has to navigate on success.
 */

const GSI_SRC = "https://accounts.google.com/gsi/client";

// Load the Google Identity Services script once, sharing the same promise across
// every button instance / re-render so it is never injected twice.
let gsiScriptPromise = null;
function loadGsiScript() {
  if (window.google?.accounts?.id) {
    return Promise.resolve();
  }
  if (gsiScriptPromise) {
    return gsiScriptPromise;
  }
  gsiScriptPromise = new Promise((resolve, reject) => {
    const existing = document.querySelector(`script[src="${GSI_SRC}"]`);
    if (existing) {
      existing.addEventListener("load", () => resolve());
      existing.addEventListener("error", () =>
        reject(new Error("Failed to load Google sign-in.")),
      );
      return;
    }
    const script = document.createElement("script");
    script.src = GSI_SRC;
    script.async = true;
    script.defer = true;
    script.onload = () => resolve();
    script.onerror = () => reject(new Error("Failed to load Google sign-in."));
    document.head.appendChild(script);
  });
  return gsiScriptPromise;
}

// The official GIS button only accepts a fixed set of labels; map the admin's
// configured button text to the nearest preset so the rendered (trusted) Google
// button still reflects intent. The default "Continue with Google" → the exact
// "continue_with" preset.
function gsiTextOption(buttonText) {
  const text = (buttonText || "").toLowerCase();
  if (text.includes("sign up") || text.includes("signup")) {
    return "signup_with";
  }
  if (
    text.includes("sign in") ||
    text.includes("signin") ||
    text.includes("log in") ||
    text.includes("login")
  ) {
    return "signin_with";
  }
  return "continue_with";
}

/**
 * @param {object}   props
 * @param {object}   props.google    `config.google` block: `{ clientId, buttonText, loginApi }`.
 * @param {Function} props.onSuccess Called once the endpoint has signed the visitor in.
 * @return {React.ReactElement|null}
 */
const GoogleButton = ({ google = {}, onSuccess }) => {
  const { clientId, buttonText, loginApi } = google;
  const buttonRef = useRef(null);
  const onSuccessRef = useRef(onSuccess);
  const [error, setError] = useState("");
  const [loading, setLoading] = useState(false);

  // Keep the latest onSuccess without re-initializing the Google button on every
  // parent re-render.
  useEffect(() => {
    onSuccessRef.current = onSuccess;
  }, [onSuccess]);

  useEffect(() => {
    if (!clientId || !loginApi) {
      return undefined;
    }

    let cancelled = false;

    const handleCredential = async (response) => {
      if (!response?.credential) {
        return;
      }
      setError("");
      setLoading(true);
      try {
        const result = await apiFetch({
          url: loginApi,
          method: "POST",
          data: { credential: response.credential },
        });
        if (result?.success && result?.data) {
          onSuccessRef.current(result.data);
        } else {
          setError(result?.message || "Google sign-in failed. Please try again.");
        }
      } catch (err) {
        setError(err?.message || "Google sign-in failed. Please try again.");
      } finally {
        if (!cancelled) {
          setLoading(false);
        }
      }
    };

    loadGsiScript()
      .then(() => {
        if (cancelled || !window.google?.accounts?.id || !buttonRef.current) {
          return;
        }
        window.google.accounts.id.initialize({
          client_id: clientId,
          callback: handleCredential,
        });
        window.google.accounts.id.renderButton(buttonRef.current, {
          theme: "outline",
          size: "large",
          text: gsiTextOption(buttonText),
          shape: "rectangular",
          // GIS caps the button width at 400px; clamp so a wide container doesn't
          // trigger a console warning and an unstyled fallback width.
          width: Math.min(buttonRef.current.offsetWidth || 320, 400),
        });
      })
      .catch(() => {
        if (!cancelled) {
          setError("Could not load Google sign-in.");
        }
      });

    return () => {
      cancelled = true;
    };
  }, [clientId, loginApi, buttonText]);

  if (!clientId || !loginApi) {
    return null;
  }

  return (
    <div className="google-login">
      <div ref={buttonRef} className="google-login__button" />
      {loading && <div className="google-login__status">Signing you in…</div>}
      {error && <div className="google-login__error">{error}</div>}
    </div>
  );
};

export default GoogleButton;
