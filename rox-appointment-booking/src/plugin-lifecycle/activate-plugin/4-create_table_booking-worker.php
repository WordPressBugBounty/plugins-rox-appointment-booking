<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_booking_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_booking';
$rox_appointment_booking_sql_booking = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_booking_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    location_id INT DEFAULT NULL,
    category_id INT DEFAULT NULL,
    service_details JSON DEFAULT NULL,
    service_id INT DEFAULT NULL,
    extra_services JSON DEFAULT NULL,
    agent_id INT DEFAULT NULL,
    customer_id INT DEFAULT NULL,
    date DATE DEFAULT NULL,
    start_time DATETIME DEFAULT NULL,
    end_time DATETIME DEFAULT NULL,
    coupon_id INT DEFAULT NULL,
    purchase_details JSON DEFAULT NULL,
    status VARCHAR(255) DEFAULT 'active',
    payment_status VARCHAR(255) DEFAULT 'pending',
    total_attendees INT DEFAULT 0,
    send_notification TINYINT(1) DEFAULT 0,
    reminder_notification TINYINT(1) DEFAULT 0,
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";
require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_booking_table)) === $rox_appointment_booking_booking_table;
if (!$rox_appointment_booking_table_exists) { dbDelta($rox_appointment_booking_sql_booking); }