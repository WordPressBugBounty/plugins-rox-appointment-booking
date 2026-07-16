<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_service_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_service';

// Per-service agent-optional toggle: allow booking without choosing an agent.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_allow_without_agent_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_service_table}` LIKE %s", 'allow_without_agent'));
if (empty($rox_appointment_booking_allow_without_agent_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_service_table}` ADD COLUMN allow_without_agent TINYINT(1) DEFAULT 0");
}

// Concurrent bookings allowed per time slot for an agent-less service (default 1).
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_without_agent_capacity_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_service_table}` LIKE %s", 'without_agent_capacity'));
if (empty($rox_appointment_booking_without_agent_capacity_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_service_table}` ADD COLUMN without_agent_capacity INT DEFAULT 1");
}
