<?php

/**
 * Booking Engine Status Helper Functions
 *
 * @package RoxAppointmentBooking
 * @subpackage Functions
 * @since 1.0.0
 */

if (!defined('ABSPATH')) {
	exit;
}

if (!function_exists('rox_appointment_booking_order_statuses')) {
	/**
	 * Retrieve order statuses for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	function rox_appointment_booking_order_statuses()
	{
		return apply_filters('rox_appointment_booking_order_statuses', [
			["icon" => "notpaidcirclefilled", "label" => __("Pending payment", "rox-appointment-booking"), "value" => "pending_payment"],
			["icon" => "processingcirclefilled", "label" => __("Processing", "rox-appointment-booking"), "value" => "processing"],
			["icon" => "pending", "label" => __("On hold", "rox-appointment-booking"), "value" => "on_hold"],
			["icon" => "paidcirclefilled", "label" => __("Completed", "rox-appointment-booking"), "value" => "completed"],
			["icon" => "canceledcirclefilled", "label" => __("Cancelled", "rox-appointment-booking"), "value" => "cancelled"],
			["icon" => "refundedcirclefilled", "label" => __("Refunded", "rox-appointment-booking"), "value" => "refunded"],
			["icon" => "failedcirclefilled", "label" => __("Failed", "rox-appointment-booking"), "value" => "failed"],
		]);
	}
}

if (!function_exists('rox_appointment_booking_payment_statuses')) {
	/**
	 * Retrieve payment statuses for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	function rox_appointment_booking_payment_statuses()
	{
		return apply_filters('rox_appointment_booking_payment_statuses', [
			["icon" => "failedcirclefilled", "label" => __("Failed", "rox-appointment-booking"), "value" => "failed"],
			["icon" => "paidcirclefilled", "label" => __("Paid", "rox-appointment-booking"), "value" => "paid"],
			["icon" => "canceledcirclefilled", "label" => __("Cancelled", "rox-appointment-booking"), "value" => "cancelled"],
			["icon" => "processingcirclefilled", "label" => __("Processing", "rox-appointment-booking"), "value" => "processing"],
			["icon" => "notpaidcirclefilled", "label" => __("Unpaid", "rox-appointment-booking"), "value" => "unpaid"],
			["icon" => "refundedcirclefilled", "label" => __("Refunded", "rox-appointment-booking"), "value" => "refunded"],
		]);
	}
}

if (!function_exists('rox_appointment_booking_appointment_statuses')) {
	/**
	 * Retrieve appointment statuses for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	function rox_appointment_booking_appointment_statuses()
	{
		return apply_filters('rox_appointment_booking_appointment_statuses', [
			["icon" => "approved", "label" => __("Approved", "rox-appointment-booking"), "value" => "approved"],
			["icon" => "rescheduled", "label" => __("Rescheduled", "rox-appointment-booking"), "value" => "rescheduled"],
			["icon" => "pending", "label" => __("Pending", "rox-appointment-booking"), "value" => "pending"],
			["icon" => "rejected", "label" => __("Rejected", "rox-appointment-booking"), "value" => "rejected"],
			["icon" => "canceledcirclefilled", "label" => __("Cancelled", "rox-appointment-booking"), "value" => "cancelled"],
			["icon" => "completed", "label" => __("Completed", "rox-appointment-booking"), "value" => "completed"],
			["icon" => "emergency", "label" => __("Emergency", "rox-appointment-booking"), "value" => "emergency"],
		]);
	}
}

if (!function_exists('rox_appointment_booking_get_order_status_label')) {
	function rox_appointment_booking_get_order_status_label($value)
	{
		foreach (rox_appointment_booking_order_statuses() as $status) {
			if ($status['value'] === $value) {
				return $status['label'];
			}
		}
		foreach (rox_appointment_booking_appointment_statuses() as $status) {
			if ($status['value'] === $value) {
				return $status['label'];
			}
		}
		return $value;
	}
}

if (!function_exists('rox_appointment_booking_get_payment_status_label')) {
	/**
	 * Resolve a payment_status value (e.g. 'partially_paid') to its human label
	 * (e.g. 'Partially Paid') via rox_appointment_booking_payment_statuses().
	 *
	 * @since 1.0.0
	 *
	 * @param string $value
	 * @return string
	 */
	function rox_appointment_booking_get_payment_status_label($value)
	{
		foreach (rox_appointment_booking_payment_statuses() as $status) {
			if ($status['value'] === $value) {
				return $status['label'];
			}
		}
		return $value;
	}
}

if (!function_exists('rox_appointment_booking_payment_methods')) {
	/**
	 * Retrieve payment methods for the Booking Engine plugin.
	 *
	 * @since 1.0.0
	 *
	 * @return array
	 */
	function rox_appointment_booking_payment_methods()
	{
		return apply_filters('rox_appointment_booking_payment_methods', [
			["icon" => "card", "label" => "Stripe", "value" => "stripe"],
			["icon" => "paylater", "label" => __("Pay Later", "rox-appointment-booking"), "value" => "pay_later"],
			["icon" => "cash", "label" => __("Cash", "rox-appointment-booking"), "value" => "cash"],
			["icon" => "debit", "label" => __("Credit / Debit Card", "rox-appointment-booking"), "value" => "card"],
		]);
	}
}