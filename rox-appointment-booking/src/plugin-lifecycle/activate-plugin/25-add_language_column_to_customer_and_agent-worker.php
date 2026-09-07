<?php
defined('ABSPATH') || exit;

global $wpdb;

// Preferred language per person, so a transactional e-mail is written in the
// recipient's language rather than whoever's language triggered the send.
// Customers get theirs from the language the booking was made in; agents fall
// back to their linked WordPress user's locale when this is empty.
$rox_appointment_booking_language_tables = [
    $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_customer',
    $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent',
];

foreach ($rox_appointment_booking_language_tables as $rox_appointment_booking_language_table) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $rox_appointment_booking_language_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_language_table}` LIKE %s", 'language'));

    if (empty($rox_appointment_booking_language_column)) {
        // Wide enough for the longest WPML/BCP-47 codes in practice
        // (e.g. `zh-hans`, `pt-br`); NULL means "no preference recorded".
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
        $wpdb->query("ALTER TABLE `{$rox_appointment_booking_language_table}` ADD COLUMN language VARCHAR(20) DEFAULT NULL");
    }
}
