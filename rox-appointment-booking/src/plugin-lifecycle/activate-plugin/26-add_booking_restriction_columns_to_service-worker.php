<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_service_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_service';

// Per-service booking window: how soon before a slot it may still be booked,
// and how far ahead of it bookings are taken at all. Each end is a toggle plus
// the days and hours that add up to it, and each overrides the matching
// site-wide setting — see ServiceService::advanceWindow().
$rox_appointment_booking_restriction_columns = [
    'minimum_advance_enable' => 'TINYINT(1) DEFAULT 0',
    'minimum_advance_days' => 'INT DEFAULT 0',
    'minimum_advance_hours' => 'INT DEFAULT 0',
    'maximum_advance_enable' => 'TINYINT(1) DEFAULT 0',
    'maximum_advance_days' => 'INT DEFAULT 0',
    'maximum_advance_hours' => 'INT DEFAULT 0',
];

foreach ($rox_appointment_booking_restriction_columns as $rox_appointment_booking_restriction_column => $rox_appointment_booking_restriction_definition) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $rox_appointment_booking_existing_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_service_table}` LIKE %s", $rox_appointment_booking_restriction_column));

    if (empty($rox_appointment_booking_existing_column)) {
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
        $wpdb->query("ALTER TABLE `{$rox_appointment_booking_service_table}` ADD COLUMN {$rox_appointment_booking_restriction_column} {$rox_appointment_booking_restriction_definition}");
    }
}
