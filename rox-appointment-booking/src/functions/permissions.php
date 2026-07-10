<?php

/**
 * Permission Helper Functions for Booking Engine
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (! defined('ABSPATH')) exit; // Exit if accessed directly

if (!function_exists('rox_appointment_booking_get_allowed_roles_map')) {
	/**
	 * Returns the role map by resource and action.
	 *
	 * @return array<string, array<string, string[]>>
	 */
	function rox_appointment_booking_get_allowed_roles_map() {
		return [
			'agent' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'booking' => [
				'show' => ['administrator', 'author', 'subscriber', 'contributor', 'editor', 'rox_appointment_booking_customer'],
				'create' => ['administrator', 'author', 'subscriber', 'contributor', 'editor'],
				'edit' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'delete' => ['administrator'],
			],
			'customer' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'order' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'calender' => [
				'show' => ['administrator', 'author', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'payment' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'service' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'extra_service' => [
				'show' => ['administrator', 'subscriber', 'contributor', 'editor'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'settings' => [
				'show' => ['administrator'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'categories' => [
				'show' => ['administrator'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
			'location' => [
				'show' => ['administrator'],
				'create' => ['administrator'],
				'edit' => ['administrator'],
				'delete' => ['administrator'],
			],
		];
	}
}

if (!function_exists('rox_appointment_booking_get_allowed_roles')) {
	/**
	 * Gets allowed roles for a specific resource/action.
	 *
	 * @param string $resource
	 * @param string $action
	 * @return string[]
	 */
	function rox_appointment_booking_get_allowed_roles($resource, $action) {
		$roles_map = rox_appointment_booking_get_allowed_roles_map();
		if (!isset($roles_map[$resource])) {
			return [];
		}
		return isset($roles_map[$resource][$action]) ? $roles_map[$resource][$action] : [];
	}
}

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

if (!function_exists('rox_appointment_booking_has_permission')) {
	/**
	 * Checks whether the current user has permission for a resource/action.
	 *
	 * @param string $resource
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_permission($resource, $action) {
		$user = wp_get_current_user();
		if (!$user->exists()) {
			return false;
		}
		$allowed_roles = rox_appointment_booking_get_allowed_roles($resource, $action);
		return !empty(array_intersect($allowed_roles, $user->roles));
	}
}

if (!function_exists('rox_appointment_booking_has_location_permission')) {
	/**
	 * Checks location permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_location_permission($action) {
		return rox_appointment_booking_has_permission('location', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_categories_permission')) {
	/**
	 * Checks categories permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_categories_permission($action) {
		return rox_appointment_booking_has_permission('categories', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_agent_permission')) {
	/**
	 * Checks agent permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_agent_permission($action) {
		return rox_appointment_booking_has_permission('agent', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_booking_permission')) {
	/**
	 * Checks booking permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_booking_permission($action) {
		return rox_appointment_booking_has_permission('booking', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_customer_permission')) {
	/**
	 * Checks customer permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_customer_permission($action) {
		return rox_appointment_booking_has_permission('customer', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_order_permission')) {
	/**
	 * Checks order permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_order_permission($action) {
		return rox_appointment_booking_has_permission('order', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_calender_permission')) {
	/**
	 * Checks calender permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_calender_permission($action) {
		return rox_appointment_booking_has_permission('calender', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_payment_permission')) {
	/**
	 * Checks payment permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_payment_permission($action) {
		return rox_appointment_booking_has_permission('payment', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_service_permission')) {
	/**
	 * Checks service permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_service_permission($action) {
		return rox_appointment_booking_has_permission('service', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_extra_service_permission')) {
	/**
	 * Checks extra service permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_extra_service_permission($action) {
		return rox_appointment_booking_has_permission('extra_service', $action);
	}
}

if (!function_exists('rox_appointment_booking_has_settings_permission')) {
	/**
	 * Checks settings permission for the current user.
	 *
	 * @param string $action
	 * @return bool
	 */
	function rox_appointment_booking_has_settings_permission($action) {
		return rox_appointment_booking_has_permission('settings', $action);
	}
}