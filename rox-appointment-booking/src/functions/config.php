<?php

/**
 * Configuration helper functions  Booking Engine
 *
 * This file contains functions for accessing and managing configuration settings
 * for theBooking Engine plugin.
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

// error logging only happens when debug mode is enabled
if (!function_exists('rox_appointment_booking_is_debug')) {

    function rox_appointment_booking_is_debug(): bool
    {
        return defined('WP_DEBUG') && WP_DEBUG && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG;
    }
}

/**
 * User role helper function for Booking Engine
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */
if (!function_exists('rox_appointment_booking_has_role')) {

    function rox_appointment_booking_has_role(string $role): bool
    {
        $user = wp_get_current_user();
        return in_array($role, (array) $user->roles, true);
    }
}

/**
 * Check if the current user is a pro user of the Booking Engine plugin.
 * This function checks if the pro version of the plugin is active and returns 'pro' or 'free' accordingly.
 * 
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 * @return string 'pro' if the pro version is active, 'free' otherwise.
 */
if (!function_exists('rox_appointment_booking_is_pro_user')) {

    function rox_appointment_booking_is_pro_user(): string
    {
        if (!function_exists('is_plugin_active')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $is_pro_active = is_plugin_active('rox-appointment-booking-pro/rox-appointment-booking-pro.php');
        return $is_pro_active || false;
    }
}
