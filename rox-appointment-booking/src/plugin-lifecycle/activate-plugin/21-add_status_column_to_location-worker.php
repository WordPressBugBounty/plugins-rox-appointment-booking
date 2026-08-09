<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_location_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_location';

// Active/inactive toggle so a location can be temporarily hidden from the public
// booking widget without being deleted.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_location_status_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_location_table}` LIKE %s", 'status'));
if (empty($rox_appointment_booking_location_status_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_location_table}` ADD COLUMN status VARCHAR(255) DEFAULT 'active'");
}
