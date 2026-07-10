<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_service_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_service';
$rox_appointment_booking_sql_service = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_service_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    title VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    duration VARCHAR(255) DEFAULT NULL,
    price DECIMAL(10,2) DEFAULT NULL,
    capacity VARCHAR(255) DEFAULT NULL,
    max_capacity INT DEFAULT NULL,
    deposit TINYINT(1) DEFAULT 0,
    deposit_type VARCHAR(255) DEFAULT NULL,
    deposit_amount DECIMAL(10,2) DEFAULT NULL,
    weekly_schedule JSON DEFAULT NULL,
    special_days JSON DEFAULT NULL,
    color VARCHAR(255) DEFAULT NULL,
    hide_price_booking_panel TINYINT(1) DEFAULT 0,
    hide_duration_booking_panel TINYINT(1) DEFAULT 0,
    only_visible_to_agent TINYINT(1) DEFAULT 0,
    thumbnail_id INT DEFAULT NULL,
    status VARCHAR(255) DEFAULT 'active',
    internal_notes TEXT DEFAULT NULL,
    sort_order INT DEFAULT 0,
    created_by INT DEFAULT NULL,
    updated_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_service_table)) === $rox_appointment_booking_service_table;
if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_service); }