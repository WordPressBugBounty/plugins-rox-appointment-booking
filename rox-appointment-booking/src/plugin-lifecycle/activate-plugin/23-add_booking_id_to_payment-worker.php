<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_payment_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_payment';

// Which single appointment this payment row settles, so an order covering
// several appointments can be paid one service at a time instead of only as
// a lump sum. NULL for legacy rows and for a deposit-only charge that can't
// be attributed to one line (kept order-level).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_booking_id_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_payment_table}` LIKE %s", 'booking_id'));
if (empty($rox_appointment_booking_booking_id_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_payment_table}` ADD COLUMN booking_id INT UNSIGNED DEFAULT NULL AFTER order_id");
}
