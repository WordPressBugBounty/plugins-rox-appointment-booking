<?php
defined('ABSPATH') || exit;

global $wpdb;
$charset_collate = $wpdb->get_charset_collate();
$rox_appointment_booking_notification_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_notification';

$rox_appointment_booking_sql_notification = "CREATE TABLE IF NOT EXISTS $rox_appointment_booking_notification_table (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY NOT NULL,
    user_id INT DEFAULT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    type VARCHAR(50) DEFAULT 'info' COMMENT 'success, info, warning, error',
    is_viewed TINYINT(1) DEFAULT 0,
    view_count INT DEFAULT 0,
    notification_time DATETIME DEFAULT CURRENT_TIMESTAMP,
    action_link JSON DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_is_viewed (is_viewed),
    INDEX idx_notification_time (notification_time)
) $charset_collate;";

require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_table_exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $rox_appointment_booking_notification_table)) === $rox_appointment_booking_notification_table;

if (!$rox_appointment_booking_table_exists) {
    dbDelta($rox_appointment_booking_sql_notification);
}
