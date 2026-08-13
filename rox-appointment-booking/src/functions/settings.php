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

if (!function_exists('rox_appointment_booking_email_settings')) {
	/**
	 * Retrieve e-mail settings for the Booking Engine plugin.
	 *
	 * Falls back to the legacy `rox_appointment_booking_notification_settings`
	 * option when the current one has never been written — the same shim
	 * GetEmailSettings applies, so sites that predate the rename keep their
	 * sender configured.
	 *
	 * @since 1.0.0
	 *
	 * @param string|null $key
	 * @param mixed       $default
	 *
	 * @return mixed
	 */
	function rox_appointment_booking_email_settings($key = null, $default = null)
	{
		$settings = get_option('rox_appointment_booking_email_settings', []);

		if (empty($settings)) {
			$settings = get_option('rox_appointment_booking_notification_settings', []);
		}

		if (!is_array($settings)) {
			$settings = [];
		}

		return $key ? ($settings[$key] ?? $default) : $settings;
	}
}

if (!function_exists('rox_appointment_booking_customer_can_reschedule')) {
	/**
	 * Whether customers may re-schedule their own appointments — the
	 * "Allow Customer To Re-Schedule Their Appointment" switch under
	 * Settings > Booking. Off by default.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function rox_appointment_booking_customer_can_reschedule()
	{
		return filter_var(
			rox_appointment_booking_general_settings('customer_reschedule_enable', false),
			FILTER_VALIDATE_BOOLEAN
		);
	}
}

if (!function_exists('rox_appointment_booking_customer_can_cancel')) {
	/**
	 * Whether customers may cancel their own appointments — the
	 * "Allow Customer To Cancel Their Appointment" switch under
	 * Settings > Booking. Off by default.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function rox_appointment_booking_customer_can_cancel()
	{
		return filter_var(
			rox_appointment_booking_general_settings('customer_cancel_enable', false),
			FILTER_VALIDATE_BOOLEAN
		);
	}
}

if (!function_exists('rox_appointment_booking_agent_can_reschedule')) {
	/**
	 * Whether agents may re-schedule their appointments — the
	 * "Allow Agent To Re-Schedule Their Appointment" switch under
	 * Settings > Booking. Off by default. Administrators are not governed by
	 * this switch.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function rox_appointment_booking_agent_can_reschedule()
	{
		return filter_var(
			rox_appointment_booking_general_settings('agent_reschedule_enable', false),
			FILTER_VALIDATE_BOOLEAN
		);
	}
}

if (!function_exists('rox_appointment_booking_agent_can_cancel')) {
	/**
	 * Whether agents may cancel their appointments — the
	 * "Allow Agent To Cancel Their Appointment" switch under Settings > Booking.
	 * Off by default. Administrators are not governed by this switch.
	 *
	 * @since 1.0.0
	 *
	 * @return bool
	 */
	function rox_appointment_booking_agent_can_cancel()
	{
		return filter_var(
			rox_appointment_booking_general_settings('agent_cancel_enable', false),
			FILTER_VALIDATE_BOOLEAN
		);
	}
}

if (!function_exists('rox_appointment_booking_default_system_fields')) {
	/**
	 * Canonical defaults for the customer-information system (built-in) fields.
	 * Each field has `enabled` (shown on the booking form), `required` and
	 * `width` ('full' | 'half' — the layout width on the booking form).
	 * Email is always enabled + required (the system depends on it for customer
	 * dedup and account login).
	 *
	 * @return array<string, array{enabled: bool, required: bool, width: string}>
	 */
	function rox_appointment_booking_default_system_fields()
	{
		return [
			'first_name' => ['enabled' => true, 'required' => true, 'width' => 'full'],
			'last_name'  => ['enabled' => true, 'required' => true, 'width' => 'full'],
			'email'      => ['enabled' => true, 'required' => true, 'width' => 'full'],
			'phone'      => ['enabled' => true, 'required' => true, 'width' => 'full'],
			'notes'      => ['enabled' => true, 'required' => false, 'width' => 'full'],
		];
	}
}

if (!function_exists('rox_appointment_booking_system_fields')) {
	/**
	 * The effective system-field config: defaults merged with any admin overrides.
	 * The Pro plugin answers `rox_appointment_booking_system_fields` (a Pro
	 * feature); with Pro inactive the defaults apply unchanged.
	 *
	 * @return array<string, array{enabled: bool, required: bool, width: string}>
	 */
	function rox_appointment_booking_system_fields()
	{
		return apply_filters('rox_appointment_booking_system_fields', rox_appointment_booking_default_system_fields());
	}
}

