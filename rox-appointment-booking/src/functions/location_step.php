<?php

/**
 * Location-step helper functions  Booking Engine
 *
 * The booking panel only starts from a Location step under a narrow set of
 * conditions (Pro active, the location module switched on, and at least one
 * location saved). Several places need that same answer — the panel structure
 * endpoint, the booking-panel block editor and the Elementor widget, which all
 * decide whether to offer a "restrict to these locations" control — so the
 * checks live here instead of being repeated per surface.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('rox_appointment_booking_location_module_enabled')) {

    /**
     * Whether the Location step is switched on: the location module is enabled
     * in settings AND Pro is active. Says nothing about how many locations
     * exist — see rox_appointment_booking_location_count().
     *
     * @return bool
     */
    function rox_appointment_booking_location_module_enabled(): bool
    {
        $location_settings = get_option('rox_appointment_booking_location_settings', []);

        $enabled = filter_var(
            $location_settings['location_module_enable'] ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        // The location step is a Pro feature: with the module enabled but Pro
        // inactive the panel starts from the category step instead.
        return $enabled && (bool) rox_appointment_booking_is_pro_user();
    }
}

if (!function_exists('rox_appointment_booking_location_count')) {

    /**
     * Number of locations available to the booking panel. Always 0 without Pro,
     * which owns the location table and its model.
     *
     * @return int
     */
    function rox_appointment_booking_location_count(): int
    {
        $model = '\RoxAppointmentBookingPro\Modules\Location\Data\LocationModel';

        if (!rox_appointment_booking_is_pro_user() || !class_exists($model)) {
            return 0;
        }

        return (int) $model::count();
    }
}

if (!function_exists('rox_appointment_booking_location_choice_available')) {

    /**
     * Whether a surface should offer a location picker at all. The visitor only
     * ever sees a Location step with the module enabled AND more than one
     * location to choose between — with a single location the panel auto-selects
     * it and skips the step, so restricting the list would have nothing to do.
     *
     * @return bool
     */
    function rox_appointment_booking_location_choice_available(): bool
    {
        return rox_appointment_booking_location_module_enabled()
            && rox_appointment_booking_location_count() > 1;
    }
}
