<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_agent_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

// Active/inactive toggle so an agent can be temporarily hidden from the public
// booking widget without being deleted.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_agent_status_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_agent_table}` LIKE %s", 'status'));
if (empty($rox_appointment_booking_agent_status_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_agent_table}` ADD COLUMN status VARCHAR(255) DEFAULT 'active'");
}
