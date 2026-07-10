<?php
defined('ABSPATH') || exit;

global $wpdb;

$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_payment_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_payment';

$rox_appointment_booking_sql_payment = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_payment_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    order_id INT UNSIGNED DEFAULT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    payment_method VARCHAR(100) DEFAULT NULL,
    transaction_id VARCHAR(255) DEFAULT NULL,
    payment_time DATETIME DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// Check if table exists before creating
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_payment_table)) === $rox_appointment_booking_payment_table;

// Create table only if it doesn't exist
if (!$rox_appointment_booking_table_exists) {
    dbDelta($rox_appointment_booking_sql_payment);
}
