<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_customer_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_customer';

$rox_appointment_booking_sql_customer = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_customer_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    first_name VARCHAR(255) NOT NULL,
    last_name VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    thumbnail_id INT DEFAULT NULL,
    gender VARCHAR(255) DEFAULT NULL,
    dob DATE DEFAULT NULL,
    allow_to_login TINYINT(1) DEFAULT 0,
    wp_user_id INT DEFAULT NULL,
    send_notifications TINYINT(1) DEFAULT 0,
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_customer_table)) === $rox_appointment_booking_customer_table;

if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_customer); }