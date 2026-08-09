<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_order_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_order';

// Deposit portion of total_amount computed at order time (sum of each line's deposit rule).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_deposit_amount_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_order_table}` LIKE %s", 'deposit_amount'));
if (empty($rox_appointment_booking_deposit_amount_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_order_table}` ADD COLUMN deposit_amount DECIMAL(10,2) DEFAULT 0.00");
}

// What must be charged now (deposit, or full total_amount when no deposit / customer chose to pay in full).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_amount_due_now_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_order_table}` LIKE %s", 'amount_due_now'));
if (empty($rox_appointment_booking_amount_due_now_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_order_table}` ADD COLUMN amount_due_now DECIMAL(10,2) DEFAULT 0.00");
}

// Remaining balance owed after amount_due_now is collected.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_amount_due_later_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_order_table}` LIKE %s", 'amount_due_later'));
if (empty($rox_appointment_booking_amount_due_later_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_order_table}` ADD COLUMN amount_due_later DECIMAL(10,2) DEFAULT 0.00");
}
