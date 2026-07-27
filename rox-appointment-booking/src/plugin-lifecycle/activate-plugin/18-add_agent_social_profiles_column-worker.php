<?php
defined('ABSPATH') || exit;

global $wpdb;
$rox_appointment_booking_agent_table = $wpdb->prefix . ROX_APPOINTMENT_BOOKING_PREFIX . '_agent';

// Stores the agent's social profile links (LinkedIn, Twitter, Facebook, YouTube, Instagram, TikTok) as a JSON array.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
$rox_appointment_booking_social_profiles_column = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `{$rox_appointment_booking_agent_table}` LIKE %s", 'social_profiles'));
if (empty($rox_appointment_booking_social_profiles_column)) {
    // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Database schema operations require direct DB access
    $wpdb->query("ALTER TABLE `{$rox_appointment_booking_agent_table}` ADD COLUMN social_profiles TEXT DEFAULT NULL");
}
