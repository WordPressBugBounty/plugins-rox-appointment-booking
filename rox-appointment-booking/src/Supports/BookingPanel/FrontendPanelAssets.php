<?php

namespace RoxAppointmentBooking\Supports\BookingPanel;

use RoxAppointmentBooking\Supports\Assets;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Registration helper for the shared frontend booking-panel bundle.
 *
 * The `[rox_appointment_booking]` shortcode, the "Booking Panel" block and the
 * Elementor panel widget each register `public/build/frontend/app.js` under the
 * same `rox-appointment-booking-frontend` handle so a page carrying more than
 * one of them still loads the bundle once. The Booking Button surfaces need the
 * very same bundle — the modal mounts the panel through
 * `window.roxAppointmentBooking.mountRoot()` — so rather than adding a fourth
 * hand-rolled copy of that registration, they call this helper.
 *
 * Idempotent: whichever surface runs first wins and later calls are no-ops.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */
class FrontendPanelAssets
{
    /**
     * Shared frontend (view) script/style handle.
     */
    public const VIEW_HANDLE = 'rox-appointment-booking-frontend';

    /**
     * Registers the panel bundle and prints the config the panel reads from
     * `window.rox_appointment_booking.config.app`.
     *
     * @return bool True when the handle is registered (by this call or an
     *              earlier one), false when the build output is missing.
     */
    public static function register(): bool
    {
        if (wp_script_is(self::VIEW_HANDLE, 'registered')) {
            return true;
        }

        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

        $view_asset_file = $build_path . 'frontend/app.asset.php';
        if (!file_exists($view_asset_file)) {
            return false;
        }

        $view_asset = require $view_asset_file;

        $shared = Assets::enqueueSharedChunks(
            $build_url,
            $build_path,
            $view_asset['dependencies'] ?? [],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        wp_register_script(
            self::VIEW_HANDLE,
            $build_url . 'frontend/app.js',
            [$shared['script']],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION,
            true
        );

        wp_set_script_translations(
            self::VIEW_HANDLE,
            'rox-appointment-booking',
            ROX_APPOINTMENT_BOOKING_PATH . 'languages'
        );

        wp_register_style(
            self::VIEW_HANDLE,
            $build_url . 'frontend/app.css',
            array_filter([$shared['style']]),
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        wp_add_inline_script(
            self::VIEW_HANDLE,
            'window.rox_appointment_booking = window.rox_appointment_booking || {}; ' .
                'window.rox_appointment_booking.config = window.rox_appointment_booking.config || {}; ' .
                'window.rox_appointment_booking.config.app = ' . wp_json_encode(self::config()) . ';',
            'before'
        );

        return true;
    }

    /**
     * Frontend config the booking panel expects. Mirrors
     * FrontendApp::frontendAppVars() / BookingPanelBlock::frontendConfig() /
     * Elementor Provider::frontendConfig().
     *
     * @return array
     */
    public static function config(): array
    {
        return [
            'version'           => ROX_APPOINTMENT_BOOKING_VERSION,
            'appTitle'          => ROX_APPOINTMENT_BOOKING_NAME,
            'defaultLocale'     => determine_locale(),
            'timezone'          => get_option('timezone_string') ?: 'UTC',
            'dateFormat'        => get_option('date_format') ?: 'Y-m-d',
            'timeFormat'        => get_option('time_format') ?: 'H:i:s',
            'appRootDomId'      => 'rox-appointment-booking-frontend-root',
            'publicUrl'         => esc_url_raw(ROX_APPOINTMENT_BOOKING_PUBLIC_URL),
            'nonce'             => wp_create_nonce('rox_appointment_booking_frontend_nonce'),
            'apiBaseUrl'        => esc_url_raw(rest_url('rox-appointment-booking/v1/')),
            'restBaseUrl'       => esc_url_raw(rest_url()),
            'siteUrl'           => esc_url_raw(site_url()),
            'is_user_logged_in' => is_user_logged_in(),
            'logout_url'        => html_entity_decode(wp_logout_url()),
            'dashboardUrl'      => rox_appointment_booking_dashboard_url(),
        ];
    }
}
