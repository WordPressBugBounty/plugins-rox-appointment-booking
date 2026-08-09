import React, { useState } from "react";
import { login, requestReset, setNewPassword, errorMessage } from "./api";
// Explicit extension: webpack's `resolve.extensions` omits `.jsx`.
import GoogleButton from "./GoogleButton.jsx";
import { __ } from '@wordpress/i18n';

/**
 * Reads the reset-link params the reset email appends to this page's url. Same
 * two names `BookingService/index.jsx` reads, so one email works for either
 * surface.
 *
 * @return {?{key: string, login: string}}
 */
const readResetParams = () => {
  const params = new URLSearchParams(window.location.search);
  const key = params.get("rox_reset_key");
  const loginName = params.get("rox_reset_login");
  return key && loginName ? { key, login: loginName } : null;
};

/**
 * Strips the (now consumed) reset params from the url so a refresh does not
 * re-enter the reset flow.
 */
const stripResetParams = () => {
  const url = new URL(window.location.href);
  url.searchParams.delete("rox_reset_key");
  url.searchParams.delete("rox_reset_login");
  window.history.replaceState({}, document.title, url.toString());
};

/**
 * The "Cancel ×" control that closes a reset-container back to the login state.
 * Copied from `CustomerInfo.jsx`, where the same markup appears in both reset
 * views.
 *
 * @param {{onClick: Function}} props
 */
const CancelButton = ({ onClick }) => (
  <div className="cancel-button" onClick={onClick}>
    Cancel
    <span className="close-icon">
      <svg
        xmlns="http://www.w3.org/2000/svg"
        width="10"
        height="10"
        viewBox="0 0 10 10"
        fill="none"
      >
        <path
          d="M9 1L1 9"
          stroke="#EF7471"
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
        <path
          d="M1 1L9 9"
          stroke="#EF7471"
          strokeWidth="1.5"
          strokeLinecap="round"
          strokeLinejoin="round"
        />
      </svg>
    </span>
  </div>
);

/**
 * Bespoke standalone login form (customers and agents).
 *
 * Three states — `login` (Step 1.3, `POST /public/login`), `forgot`
 * (Step 1.4, `POST /public/customer/reset-password-request`) and `reset`
 * (Step 1.5, `POST /public/customer/reset-password`, entered on mount when the
 * url carries the reset-link params). The markup is copied from the booking
 * panel's customer step (`components/BookingService/CustomerInfo.jsx`) so the
 * two look identical.
 *
 * On successful login the endpoint has already set the WordPress auth cookie, so
 * the form only has to navigate: to `config.redirectUrl` when set, otherwise a
 * reload of the current page (which drops the visitor into their logged-in state).
 *
 * @param {{config: object}} props
 */
export default function LoginFormApp({ config = {} }) {
  // Read once on mount — after the params are stripped a refresh resolves to null.
  const [resetParams] = useState(readResetParams);
  const [stage, setStage] = useState(() => (readResetParams() ? "reset" : "login"));
  const [credentials, setCredentials] = useState({ email: "", password: "" });
  const [loginError, setLoginError] = useState("");
  const [isLoggingIn, setIsLoggingIn] = useState(false);
  const [resetEmail, setResetEmail] = useState("");
  const [resetMessage, setResetMessage] = useState(null);
  const [isSendingReset, setIsSendingReset] = useState(false);
  const [newPassword, setNewPasswordValue] = useState("");
  const [confirmNewPassword, setConfirmNewPassword] = useState("");
  const [isSettingPassword, setIsSettingPassword] = useState(false);

  const handleChange = (e) => {
    const { name, value } = e.target;
    setCredentials((prevState) => ({ ...prevState, [name]: value }));
  };

  /**
   * Sends the visitor on after a successful login. The login response may carry
   * its own `redirect_url` (e.g. agents land on the admin dashboard) which wins;
   * otherwise the surface's `redirectUrl` setting applies (filled server-side,
   * defaults to the WordPress admin). The reload is only a fallback for a config
   * that carries no url at all.
   *
   * @param {object} [data] The login response `data` (password or Google flow).
   */
  const redirectAfterLogin = (data) => {
    const target = (data && data.redirect_url) || config.redirectUrl;
    if (target) {
      window.location.assign(target);
      return;
    }
    window.location.reload();
  };

  /**
   * A real WordPress session can only be ended server-side, so leave through the
   * WP logout url. `redirect_to` is appended here rather than baked into the url
   * because the `log-out` nonce does not cover it — the same approach the
   * booking panel uses.
   */
  const handleLogout = () => {
    if (!config.logoutUrl) {
      return;
    }
    window.location.href = `${config.logoutUrl}&redirect_to=${encodeURIComponent(
      window.location.href,
    )}`;
  };

  const handleLogin = async () => {
    if (isLoggingIn) {
      return;
    }

    setLoginError("");
    setIsLoggingIn(true);

    try {
      const response = await login(config, credentials);

      if (response.success) {
        redirectAfterLogin(response.data);
        // Keep the button disabled while the browser navigates away.
        return;
      }

      setLoginError(response.message || "Login failed. Please try again.");
    } catch (error) {
      setLoginError(
        errorMessage(error, "An error occurred during login. Please try again."),
      );
    }

    setIsLoggingIn(false);
  };

  const handleForgotPassword = () => {
    // Prefill the reset email with whatever was typed in the login form.
    setResetEmail(credentials.email || "");
    setResetMessage(null);
    setStage("forgot");
  };

  /**
   * Requests a password reset link. On success the backend emails the link to
   * the account owner; an unknown email comes back as an error we surface inline.
   */
  const handleResetRequest = async () => {
    if (isSendingReset) {
      return;
    }
    setResetMessage(null);

    if (!resetEmail || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(resetEmail)) {
      setResetMessage({ type: "error", text: "Please enter a valid email address." });
      return;
    }

    setIsSendingReset(true);
    try {
      const response = await requestReset(config, resetEmail);

      setResetMessage({
        type: response.success ? "success" : "error",
        text:
          response.message ||
          (response.success
            ? "We have sent a password reset link to your email address."
            : "No account found with this email address."),
      });
    } catch (error) {
      setResetMessage({
        type: "error",
        text: errorMessage(error, "No account found with this email address."),
      });
    }

    setIsSendingReset(false);
  };

  /**
   * Leaves the reset stage for the login form, consuming the url params on the
   * way out. Used by both a successful reset and Cancel (×).
   */
  const exitResetStage = () => {
    stripResetParams();
    setNewPasswordValue("");
    setConfirmNewPassword("");
    setStage("login");
  };

  /**
   * Sets the new password from the reset link. On success the login form takes
   * over, prefilled with the account email the endpoint returns.
   */
  const handleSetNewPassword = async () => {
    if (isSettingPassword) {
      return;
    }
    setResetMessage(null);

    if (!newPassword || newPassword.length < 6) {
      setResetMessage({ type: "error", text: "Password must be at least 6 characters." });
      return;
    }
    if (newPassword !== confirmNewPassword) {
      setResetMessage({ type: "error", text: "Passwords do not match." });
      return;
    }

    setIsSettingPassword(true);
    try {
      const response = await setNewPassword(config, resetParams, newPassword);

      if (response.success) {
        setCredentials({ email: response.data?.email || "", password: "" });
        setLoginError("");
        exitResetStage();
        setResetMessage({
          type: "success",
          text: "Your password has been updated. Please log in.",
        });
      } else {
        setResetMessage({
          type: "error",
          text:
            response.message ||
            "This password reset link is invalid. Please request a new one.",
        });
      }
    } catch (error) {
      setResetMessage({
        type: "error",
        text: errorMessage(
          error,
          "This password reset link is invalid. Please request a new one.",
        ),
      });
    }

    setIsSettingPassword(false);
  };

  const messageBox = (message) =>
    message && (
      <div
        className={`rlf-message rlf-message-${
          message.type === "success" ? "success" : "error"
        }`}
      >
        {message.text}
      </div>
    );

  // Nothing to log into when a session is already active: show who is signed in
  // and a way out instead of the form. The `reset` stage is exempt: someone who
  // followed a reset link came here to change their password, even if they
  // happen to still be signed in.
  if (config.isLoggedIn && stage !== "reset") {
    return (
      <div className="form-wrapper">
        <div className="logged-in-banner">
          You're currently logged in as {config.userEmail}{" "}
          <span className="logout-link" onClick={handleLogout}>
            (Logout)
          </span>
        </div>
      </div>
    );
  }

  if (stage === "reset") {
    return (
      <div className="form-wrapper">
        <div className="reset-container">
          <CancelButton
            onClick={() => {
              setResetMessage(null);
              exitResetStage();
            }}
          />
          <div className="title">Change Your Password</div>
          <p className="description">
            Please enter a new password for your account.
          </p>
          {messageBox(resetMessage)}
          <div className="account-form-wrapper">
            <input
              type="password"
              placeholder="New Password"
              className="input-field"
              value={newPassword}
              onChange={(e) => setNewPasswordValue(e.target.value)}
              required
            />
            <input
              type="password"
              placeholder="Confirm New Password"
              className="input-field"
              value={confirmNewPassword}
              onChange={(e) => setConfirmNewPassword(e.target.value)}
              required
            />
          </div>
          <div className="footer-btn-container">
            <div
              className="primary-submit-btn"
              onClick={handleSetNewPassword}
              style={{
                opacity: isSettingPassword ? 0.6 : 1,
                cursor: isSettingPassword ? "not-allowed" : "pointer",
              }}
            >
              {isSettingPassword ? "Saving..." : "Reset Password"}
            </div>
          </div>
        </div>
      </div>
    );
  }

  if (stage === "forgot") {
    return (
      <div className="form-wrapper">
        <div className="reset-container">
          <CancelButton
            onClick={() => {
              setResetMessage(null);
              setStage("login");
            }}
          />
          <div className="title">Reset Password Request</div>
          <p className="description">
            Enter your account email address and we'll send you a link to reset
            your password.
          </p>
          {messageBox(resetMessage)}
          <div className="account-form-wrapper">
            <input
              type="email"
              placeholder="Email Address"
              className="input-field"
              value={resetEmail}
              onChange={(e) => setResetEmail(e.target.value)}
              required
            />
          </div>
          <div className="footer-btn-container">
            <div
              className="primary-submit-btn"
              onClick={handleResetRequest}
              style={{
                opacity: isSendingReset ? 0.6 : 1,
                cursor: isSendingReset ? "not-allowed" : "pointer",
              }}
            >
              {isSendingReset ? "Sending..." : "Submit Request"}
            </div>
          </div>
        </div>
      </div>
    );
  }

  return (
    <div className="form-wrapper">
      <div className="form-fields">
        {resetMessage?.type === "success" && messageBox(resetMessage)}
        {loginError && <div className="rlf-message rlf-message-error">{loginError}</div>}
        <div className="form-group">
          <label>Email</label>
          <input
            type="email"
            name="email"
            value={credentials.email}
            onChange={handleChange}
            placeholder={__("Enter email", "rox-appointment-booking")}
            required
          />
        </div>
        <div className="form-group">
          <label>Password</label>
          <input
            type="password"
            name="password"
            value={credentials.password}
            onChange={handleChange}
            placeholder={__("Enter password", "rox-appointment-booking")}
            required
          />
        </div>
      </div>
      <div className="footer-btn-container">
        <div
          className="primary-submit-btn"
          onClick={handleLogin}
          style={{
            opacity: isLoggingIn ? 0.6 : 1,
            cursor: isLoggingIn ? "not-allowed" : "pointer",
          }}
        >
          {isLoggingIn ? "Logging in..." : config.loginLabel || "Login"}
        </div>
        <div className="forgot-password-container">
          <div className="forgot-password-link" onClick={handleForgotPassword}>
            Forgot password?
          </div>
        </div>
      </div>
      {config.google?.enabled && (
        <>
          <div className="google-login-divider">
            <span>OR</span>
          </div>
          <GoogleButton google={config.google} onSuccess={redirectAfterLogin} />
        </>
      )}
    </div>
  );
}
