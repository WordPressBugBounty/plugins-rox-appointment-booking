<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_agent_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

// Legacy single-platform columns, superseded by the social_profiles JSON column.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_linkedin_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_agent_table}` LIKE %s", 'linkedin'));
if (!empty($rox_appointment_booking_linkedin_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_agent_table}` DROP COLUMN linkedin");
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_twitter_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_agent_table}` LIKE %s", 'twitter'));
if (!empty($rox_appointment_booking_twitter_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_agent_table}` DROP COLUMN twitter");
}
