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
			["icon" => "notpaidcirclefilled", "label" => "Pending payment", "value" => "pending_payment"],
			["icon" => "processingcirclefilled", "label" => "Processing", "value" => "processing"],
			["icon" => "pending", "label" => "On hold", "value" => "on_hold"],
			["icon" => "paidcirclefilled", "label" => "Completed", "value" => "completed"],
			["icon" => "canceledcirclefilled", "label" => "Cancelled", "value" => "cancelled"],
			["icon" => "refundedcirclefilled", "label" => "Refunded", "value" => "refunded"],
			["icon" => "failedcirclefilled", "label" => "Failed", "value" => "failed"],
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
			["icon" => "failedcirclefilled", "label" => "Failed", "value" => "failed"],
			["icon" => "paidcirclefilled", "label" => "Paid", "value" => "paid"],
			["icon" => "canceledcirclefilled", "label" => "Cancelled", "value" => "cancelled"],
			["icon" => "processingcirclefilled", "label" => "Processing", "value" => "processing"],
			["icon" => "notpaidcirclefilled", "label" => "Unpaid", "value" => "unpaid"],
			["icon" => "refundedcirclefilled", "label" => "Refunded", "value" => "refunded"],
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
			["icon" => "approved", "label" => "Approved", "value" => "approved"],
			["icon" => "rescheduled", "label" => "Rescheduled", "value" => "rescheduled"],
			["icon" => "pending", "label" => "Pending", "value" => "pending"],
			["icon" => "rejected", "label" => "Rejected", "value" => "rejected"],
			["icon" => "canceledcirclefilled", "label" => "Cancelled", "value" => "cancelled"],
			["icon" => "completed", "label" => "Completed", "value" => "completed"],
			["icon" => "emergency", "label" => "Emergency", "value" => "emergency"],
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
			["icon" => "wallet-done-02", "label" => "Pay Later", "value" => "pay_later"], 
			["icon" => "cash", "label" => "Cash", "value" => "cash"], 
		]);
	}
}