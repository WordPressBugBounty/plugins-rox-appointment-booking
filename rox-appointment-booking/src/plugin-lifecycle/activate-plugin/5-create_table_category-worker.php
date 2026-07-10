<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_category_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_category';
$rox_appointment_booking_sql_category = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_category_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) DEFAULT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    thumbnail_id INT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    internal_notes VARCHAR(255) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_category_table)) === $rox_appointment_booking_category_table;
if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_category); }