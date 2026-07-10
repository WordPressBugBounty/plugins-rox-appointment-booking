<?php

use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Notification\Data\NotificationModel;

defined('ABSPATH') || exit;

if (!function_exists('rox_appointment_booking_create_notification')) {
    /**
     * Create a new notification
     *
     * @param array $data Notification data
     * @return NotificationModel|null
     */
    function rox_appointment_booking_create_notification(array $data): ?NotificationModel
    {
        try {
            return NotificationService::create($data);
        } catch (\Exception $e) {
            return null;
        }
    }
}

if (!function_exists('rox_appointment_booking_get_user_notifications')) {
    /**
     * Get notifications for a user
     *
     * @param int $userId
     * @param array $filters
     * @return array
     */
    function rox_appointment_booking_get_user_notifications(int $userId, array $filters = []): array
    {
        return NotificationService::getUserNotifications($userId, $filters);
    }
}

if (!function_exists('rox_appointment_booking_get_unread_count')) {
    /**
     * Get unread notification count for a user
     *
     * @param int $userId
     * @return int
     */
    function rox_appointment_booking_get_unread_count(int $userId): int
    {
        return NotificationService::getUnreadCount($userId);
    }
}

if (!function_exists('rox_appointment_booking_mark_notification_viewed')) {
    /**
     * Mark a notification as viewed
     *
     * @param int $notificationId
     * @return bool
     */
    function rox_appointment_booking_mark_notification_viewed(int $notificationId): bool
    {
        return NotificationService::markAsViewed($notificationId);
    }
}

if (!function_exists('rox_appointment_booking_mark_all_notifications_viewed')) {
    /**
     * Mark all notifications as viewed for a user
     *
     * @param int $userId
     * @return bool
     */
    function rox_appointment_booking_mark_all_notifications_viewed(int $userId): bool
    {
        return NotificationService::markAllAsViewed($userId);
    }
}

if (!function_exists('rox_appointment_booking_notify_appointment_booked')) {
    /**
     * Create notification for new appointment booking
     *
     * @param array $appointmentData
     * @return NotificationModel|null
     */
    function rox_appointment_booking_notify_appointment_booked(array $appointmentData): ?NotificationModel
    {
        return NotificationService::createAppointmentNotification($appointmentData);
    }
}

if (!function_exists('rox_appointment_booking_notify_payment_received')) {
    /**
     * Create notification for payment received
     *
     * @param array $paymentData
     * @return NotificationModel|null
     */
    function rox_appointment_booking_notify_payment_received(array $paymentData): ?NotificationModel
    {
        return NotificationService::createPaymentNotification($paymentData);
    }
}

if (!function_exists('rox_appointment_booking_notify_appointment_cancelled')) {
    /**
     * Create notification for appointment cancellation
     *
     * @param array $appointmentData
     * @return NotificationModel|null
     */
    function rox_appointment_booking_notify_appointment_cancelled(array $appointmentData): ?NotificationModel
    {
        return NotificationService::createCancellationNotification($appointmentData);
    }
}

if (!function_exists('rox_appointment_booking_clean_old_notifications')) {
    /**
     * Delete old viewed notifications
     *
     * @param int $days Number of days to keep
     * @return int Number of deleted notifications
     */
    function rox_appointment_booking_clean_old_notifications(int $days = 30): int
    {
        return NotificationService::deleteOldNotifications($days);
    }
}

if (!function_exists('rox_appointment_booking_format_currency')) {
    /**
     * Format amount as currency (if not already exists)
     *
     * @param float $amount
     * @return string
     */
    function rox_appointment_booking_format_currency(float $amount): string
    {
        // Check if there's a currency setting, otherwise use a default
        $currency_symbol = get_option('rox_appointment_booking_currency_symbol', '$');
        $currency_position = get_option('rox_appointment_booking_currency_position', 'left');
        
        $formatted_amount = number_format($amount, 2);
        
        if ($currency_position === 'left') {
            return $currency_symbol . $formatted_amount;
        } else {
            return $formatted_amount . $currency_symbol;
        }
    }
}
