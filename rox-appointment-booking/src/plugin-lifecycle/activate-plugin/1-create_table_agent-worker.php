<?php
defined('ABSPATH') || exit;

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_agent_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

$rox_appointment_booking_sql_agent = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_agent_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    allow_to_login TINYINT(1) DEFAULT 1,
    wp_user_id INT DEFAULT NULL,
    thumbnail_id INT DEFAULT NULL,
    location_id VARCHAR(255) DEFAULT NULL,
    weekly_schedule TEXT DEFAULT NULL,
    holiday TEXT DEFAULT NULL,
    special_days TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    experience_years INT DEFAULT NULL,
    certifications INT DEFAULT NULL,
    linkedin VARCHAR(255) DEFAULT NULL,
    twitter VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Check if table exists before creating
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_agent_table)) === $rox_appointment_booking_agent_table;

// Create table only if it doesn't exist
if (!$rox_appointment_booking_table_exists) {
    dbDelta($rox_appointment_booking_sql_agent);
}
