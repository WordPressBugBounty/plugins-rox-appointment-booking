<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_order_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_order';

$rox_appointment_booking_sql_order = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_order_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    booking_ids JSON DEFAULT NULL,
    subtotal DECIMAL(10,2) DEFAULT 0.00,
    discount_amount DECIMAL(10,2) DEFAULT 0.00,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(10) DEFAULT 'USD',
    coupon_id INT UNSIGNED DEFAULT NULL,
    coupon_code VARCHAR(255) DEFAULT NULL,
    payment_method VARCHAR(255) DEFAULT NULL,
    payment_status VARCHAR(255) DEFAULT 'pending',
    payment_transaction_id VARCHAR(255) DEFAULT NULL,
    order_status VARCHAR(255) DEFAULT 'pending',
    order_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    fulfillment_date DATETIME DEFAULT NULL,
    cancellation_date DATETIME DEFAULT NULL,
    refund_amount DECIMAL(10,2) DEFAULT 0.00,
    refund_date DATETIME DEFAULT NULL,
    refund_reason TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_order_table)) === $rox_appointment_booking_order_table;

if (!$rox_appointment_booking_table_exists) {
    dbDelta($rox_appointment_booking_sql_order);
}