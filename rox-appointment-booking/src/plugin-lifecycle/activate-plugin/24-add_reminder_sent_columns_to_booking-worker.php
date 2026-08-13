<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_booking_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_booking';

// When each recipient's reminder went out, so the hourly cron can never mail
// the same booking twice. Customer and agent are stamped independently because
// they have their own lead times and their own on/off switch. NULL = not yet
// reminded, which is also the correct state for every existing booking.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_reminder_customer_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_booking_table}` LIKE %s", 'reminder_sent_customer_at'));
if (empty($rox_appointment_booking_reminder_customer_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_booking_table}` ADD COLUMN reminder_sent_customer_at DATETIME DEFAULT NULL AFTER reminder_notification");
}

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_reminder_agent_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_booking_table}` LIKE %s", 'reminder_sent_agent_at'));
if (empty($rox_appointment_booking_reminder_agent_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_booking_table}` ADD COLUMN reminder_sent_agent_at DATETIME DEFAULT NULL AFTER reminder_sent_customer_at");
}
