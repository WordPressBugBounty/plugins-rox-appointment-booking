<?php
defined('ABSPATH') || exit;

global $wpdb;

// Timezone the customer saw when booking, plus their standing preference.
// Both are display-only: every stored date and time stays site-local, so a
// NULL here simply reads as the site timezone.
$rox_appointment_booking_timezone_columns = [
    $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_booking'  => 'customer_timezone',
    $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_customer' => 'timezone',
];

foreach ($rox_appointment_booking_timezone_columns as $rox_appointment_booking_timezone_table => $rox_appointment_booking_timezone_column) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $rox_appointment_booking_timezone_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_timezone_table}` LIKE %s", $rox_appointment_booking_timezone_column));

    if (empty($rox_appointment_booking_timezone_exists)) {
        // Wide enough for the longest IANA identifier in the database
        // (`America/Argentina/ComodRivadavia`) with room to spare.
        // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
        $wpdb->query("ALTER TABLE `{$rox_appointment_booking_timezone_table}` ADD COLUMN `{$rox_appointment_booking_timezone_column}` VARCHAR(64) DEFAULT NULL");
    }
}
