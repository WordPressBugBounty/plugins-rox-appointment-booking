<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_location_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_location';

$rox_appointment_booking_sql_location = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_location_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    thumbnail_id INT DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    geo_position VARCHAR(255) DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_location_table)) === $rox_appointment_booking_location_table;
if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_location); }