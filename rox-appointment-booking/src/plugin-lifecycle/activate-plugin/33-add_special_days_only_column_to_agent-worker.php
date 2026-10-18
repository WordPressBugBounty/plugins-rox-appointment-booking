<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_agent_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

// When set, the agent can be booked on their special days only.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_special_days_only_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_agent_table}` LIKE %s", 'special_days_only'));
if (empty($rox_appointment_booking_special_days_only_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_agent_table}` ADD COLUMN special_days_only TINYINT(1) NOT NULL DEFAULT 0");
}
