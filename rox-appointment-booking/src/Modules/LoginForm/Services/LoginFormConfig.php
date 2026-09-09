<?php

/**
 * Class LoginFormConfig
 *
 * @package RoxAppointmentBooking
 * @subpackage Modules\LoginForm\Services
 * @since 1.0.0
 *
 * Shared config builder for the standalone login form. Produces the
 * minimal `data-config` payload the bespoke React app reads at mount time (the
 * three public auth REST urls + nonce + redirect behaviour). The shortcode, the
 * Gutenberg block render callback and the Elementor widget render() all call
 * `build()` so the config/API logic has a single home in this module. The
 * Blocks + Elementor surfaces only *consume* it.
 */

namespace RoxAppointmentBooking\Modules\LoginForm\Services;

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
     *                             login. Empty string ⇒ let the login response
     *                             decide (booking roles only).
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

        // A value `esc_url_raw()` rejects (e.g. a `javascript:` url) collapses to
        // an empty string, which is also what "the surface set no redirect"
        // looks like — both mean "no url from this surface".
        //
        // There is deliberately no default url here. This used to send every
        // successful login to the plugin dashboard, which took editors,
        // subscribers, shop managers and administrators who happen to also be a
        // booking customer somewhere they have no business being. The login
        // response now carries a per-role `redirect_url` instead
        // (rox_appointment_booking_panel_redirect_url()), so only booking agents
        // and customers are sent to a dashboard; anyone else stays on the page
        // they logged in from. A surface that sets its own redirect still wins
        // for everybody, since that is the site owner's explicit choice.
        $redirect_url = esc_url_raw(trim($redirect_url));

        $config = [
            'loginApi'        => $api_base . 'public/login',
            'resetRequestApi' => $api_base . 'public/customer/reset-password-request',
            'setPasswordApi'  => $api_base . 'public/customer/reset-password',
            'nonce'           => wp_create_nonce('wp_rest'),
            // The surface's own url, or empty to let the login response decide.
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
            'buttonText' => esc_html__('Continue with Google', 'rox-appointment-booking'),
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
