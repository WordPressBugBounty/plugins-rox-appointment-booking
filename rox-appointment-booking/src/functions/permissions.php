<?php

/**
 * Permission Helper Functions for Booking Engine
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('rox_appointment_booking_get_current_user_role')) {
	/**
	 * Gets the current user's role(s).
	 *
	 * @return string[]
	 */
	function rox_appointment_booking_get_current_user_role() {
		$user = wp_get_current_user();
		if (!$user->exists()) {
			return [];
		}
		return $user->roles;
	}
}

if (!function_exists('rox_appointment_booking_is_customer')) {
	/**
	 * Whether the current user should get the separate Customer Panel UI instead
	 * of the admin dashboard: anyone who cannot reach the panel at all.
	 *
	 * Asks the panel gate rather than listing roles. The old test was "neither an
	 * administrator nor a booking agent", which answered TRUE for any other panel
	 * role and would have handed a manager the customer panel. The answer is
	 * unchanged for administrators, agents and customers.
	 *
	 * @return bool
	 */
	function rox_appointment_booking_is_customer() {
		return !\RoxAppointmentBooking\Supports\Security::canAccessPanel();
	}
}
