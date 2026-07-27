<?php

/**
 * Class LoginFormConfig
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\CustomerLogin\Services
 * @since 1.0.0
 *
 * Shared config builder for the standalone customer login form. Produces the
 * minimal `data-config` payload the bespoke React app reads at mount time (the
 * three public auth REST urls + nonce + redirect behaviour). The shortcode, the
 * Gutenberg block render callback and the Elementor widget render() all call
 * `build()` so the config/API logic has a single home in this module. The
 * Blocks + Elementor surfaces only *consume* it.
 */

namespace RoxAppointmentBooking\Modules\CustomerLogin\Services;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

class LoginFormConfig
{
    /**
     * Not auto-instantiated by the module loader; used statically.
     *
     * @var bool
     */
    public static $loadable = false;

    /**
     * Build the login form mount config.
     *
     * @param string $redirect_url Optional redirect target after a successful
     *                             login. Empty string ⇒ the WordPress admin.
     * @param string $login_label  Optional Login button label. Empty string ⇒ the
     *                             app's default ("Login").
     * @param bool   $show_google  Whether the surface wants the "Sign in with
     *                             Google" button. Only ever hides it — it can
     *                             never switch on a Google login the Pro filter
     *                             has not enabled.
     * @return array
     */
    public static function build(
        string $redirect_url = '',
        string $login_label = '',
        bool $show_google = true
    ): array {
        $api_base  = esc_url_raw(rest_url('rox-appointment-booking/v1/'));
        $logged_in = is_user_logged_in();

        // Sanitise first, then fall back: a value `esc_url_raw()` rejects (e.g. a
        // `javascript:` url) must land on the default too, not leave the field
        // empty for the app to interpret.
        $redirect_url = esc_url_raw(trim($redirect_url));
        if ($redirect_url === '') {
            $redirect_url = esc_url_raw(admin_url());
        }

        $config = [
            'loginApi'        => $api_base . 'public/customer/login',
            'resetRequestApi' => $api_base . 'public/customer/reset-password-request',
            'setPasswordApi'  => $api_base . 'public/customer/reset-password',
            'nonce'           => wp_create_nonce('wp_rest'),
            // Already resolved above: the surface's url, or /wp-admin by default.
            'redirectUrl'     => $redirect_url,
            'loginLabel'      => sanitize_text_field($login_label),
            // When someone is already signed in there is nothing to log into, so
            // the form renders a "logged in as …" banner instead. The logout url
            // is emitted bare; the app appends `redirect_to` so logging out
            // returns to this page (the `log-out` nonce does not cover it).
            'isLoggedIn'      => $logged_in,
            'userEmail'       => $logged_in ? wp_get_current_user()->user_email : '',
            'logoutUrl'       => $logged_in ? html_entity_decode(wp_logout_url()) : '',
        ];

        // "Sign in with Google" — the same filter the booking panel reads
        // (`BookingPanelStructure`). Only a Pro shipping the Google-login backend
        // answers it; no Pro, an older Pro, or the integration switched off all
        // leave `enabled` false, in which case the `google` key is omitted
        // entirely and the button never renders.
        $google = apply_filters('rox_appointment_booking_google_login_config', [
            'enabled'    => false,
            'clientId'   => '',
            'buttonText' => 'Continue with Google',
        ]);

        if ($show_google && !empty($google['enabled']) && !empty($google['clientId'])) {
            $config['google'] = [
                'enabled'    => true,
                'clientId'   => (string) $google['clientId'],
                'buttonText' => (string) $google['buttonText'],
                'loginApi'   => $api_base . 'public/customer/google-login',
            ];
        }

        return $config;
    }
}
