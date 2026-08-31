<?php

namespace RoxAppointmentBooking\Supports;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * Registration helper for the trigger-button view bundle.
 *
 * Every surface that renders a trigger through {@see BookingButtonMarkup} needs
 * the same bundle behind it — the booking panel block in popup mode and the
 * Elementor button widget — so the registration lives here rather than in
 * whichever of them happens to run first.
 *
 * The bundle depends on the shared panel bundle: the modal mounts the panel
 * through `window.roxAppointmentBooking.mountRoot()`, so that has to exist by
 * the time a click arrives. When the panel bundle is missing from the build
 * output the trigger would open an empty modal, so nothing is registered at all
 * and it falls back to inert markup.
 *
 * Idempotent: whichever surface runs first wins and later calls are no-ops.
 *
 * @package RoxAppointmentBooking
 * @subpackage Supports
 * @since 1.0.0
 */
class BookingButtonAssets
{
    /**
     * View script/style handle.
     */
    public const VIEW_HANDLE = 'rox-appointment-booking-booking-button-view';

    /**
     * Registers the bundle on top of the shared panel bundle.
     *
     * @return bool True when the handle is registered (by this call or an
     *              earlier one), false when either build output is missing.
     */
    public static function register(): bool
    {
        if (wp_script_is(self::VIEW_HANDLE, 'registered')) {
            return true;
        }

        if (!FrontendPanelAssets::register()) {
            return false;
        }

        $build_path = ROX_APPOINTMENT_BOOKING_PATH . 'public/build/';
        $build_url  = ROX_APPOINTMENT_BOOKING_PUBLIC_URL . 'build/';

        $view_asset_file = $build_path . 'blocks/booking-panel/view.asset.php';
        if (!file_exists($view_asset_file)) {
            return false;
        }

        $view_asset = require $view_asset_file;

        wp_register_script(
            self::VIEW_HANDLE,
            $build_url . 'blocks/booking-panel/view.js',
            array_merge(
                [FrontendPanelAssets::VIEW_HANDLE],
                $view_asset['dependencies'] ?? []
            ),
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
            $build_url . 'blocks/booking-panel/view.css',
            [FrontendPanelAssets::VIEW_HANDLE],
            $view_asset['version'] ?? ROX_APPOINTMENT_BOOKING_VERSION
        );

        return true;
    }
}
