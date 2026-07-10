<?php

namespace RoxAppointmentBooking\Modules\Notification;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Supports\Abstracts\AbstractLoader;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class Provider
 * 
 * @package RoxAppointmentBooking\Modules\Notification
 * @description Registers the notification module.
 */
class Provider extends AbstractLoader
{
    /**
     * Provider constructor.
     */
    public function __construct()
    {
        $this->classLoader([
            plugin_dir_path(__FILE__) . 'Services',
            plugin_dir_path(__FILE__) . 'REST',
        ]);
        $this->registerCleanupCron();
    }
    /**
     * Register a daily WP-Cron job to delete old viewed notifications.
     * Viewed notifications older than 30 days will be automatically removed.
     */
    private function registerCleanupCron(): void
    {
        add_action('rox_appointment_booking_cleanup_notifications', function () {
            NotificationService::deleteOldNotifications(30);
        });

        if (!wp_next_scheduled('rox_appointment_booking_cleanup_notifications')) {
            wp_schedule_event(time(), 'daily', 'rox_appointment_booking_cleanup_notifications');
        }
    }
}
