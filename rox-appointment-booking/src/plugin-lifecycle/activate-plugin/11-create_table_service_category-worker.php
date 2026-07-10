<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_service_category_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_service_category';
$rox_appointment_booking_sql_service_category = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_service_category_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    category_id INT NOT NULL,
    service_id INT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_service_category_table)) === $rox_appointment_booking_service_category_table;
if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_service_category); }