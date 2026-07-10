<?php

/**
 * Booking Engine Settings Helper Functions
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('rox_appointment_booking_general_settings')) {
	/**
	 * Retrieve general settings for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $key
	 * @param mixed       $default
	 *
	 * @return mixed
	 */
	function rox_appointment_booking_general_settings($key = null, $default = null)
	{
		$settings = get_option('rox_appointment_booking_general_settings', []);
		return $key ? ($settings[$key] ?? $default) : $settings;
	}
}

if (!function_exists('rox_appointment_booking_payment_settings')) {
	/**
	 * Retrieve payment settings for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $key
	 * @param mixed       $default
	 *
	 * @return mixed
	 */
	function rox_appointment_booking_payment_settings($key = null, $default = null)
	{
		$settings = get_option('rox_appointment_booking_payments_settings', []);
		return $key ? ($settings[$key] ?? $default) : $settings;
	}
}

if (!function_exists('rox_appointment_booking_default_system_fields')) {
	/**
	 * Canonical defaults for the customer-information system (built-in) fields.
	 * Each field has `enabled` (shown on the booking form) and `required` flags.
	 * Email is always enabled + required (the system depends on it for customer
	 * dedup and account login).
	 *
	 * @return array<string, array{enabled: bool, required: bool}>
	 */
	function rox_appointment_booking_default_system_fields()
	{
		return [
			'first_name' => ['enabled' => true, 'required' => true],
			'last_name'  => ['enabled' => true, 'required' => true],
			'email'      => ['enabled' => true, 'required' => true],
			'phone'      => ['enabled' => true, 'required' => true],
			'notes'      => ['enabled' => true, 'required' => false],
		];
	}
}

if (!function_exists('rox_appointment_booking_system_fields')) {
	/**
	 * The effective system-field config: defaults merged with any admin overrides.
	 * The Pro plugin answers `rox_appointment_booking_system_fields` (a Pro
	 * feature); with Pro inactive the defaults apply unchanged.
	 *
	 * @return array<string, array{enabled: bool, required: bool}>
	 */
	function rox_appointment_booking_system_fields()
	{
		return apply_filters('rox_appointment_booking_system_fields', rox_appointment_booking_default_system_fields());
	}
}

