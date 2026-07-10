<?php

namespace RoxAppointmentBooking\Modules\Notification\Services;

defined('ABSPATH') || exit;

use RoxAppointmentBooking\Modules\Notification\Data\NotificationModel;

/**
 * Class NotificationService
 * 
 * @package RoxAppointmentBooking\Modules\Notification\Services
 * @description Handles notification business logic
 */
class NotificationService
{
    /**
     * Create a new notification
     *
     * @param array $data
     * @return NotificationModel|null
     * @throws \Exception
     */
    public static function create(array $data): ?NotificationModel
    {
        // Validate required fields
        $required = ['title'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                // translators: %s = field name
                throw new \Exception(sprintf(esc_html__('%s is required', 'rox-appointment-booking'), esc_html(ucfirst($field))));
            }
        }

        // Set default values
        if (!isset($data['type'])) {
            $data['type'] = 'info';
        }

        if (!isset($data['is_viewed'])) {
            $data['is_viewed'] = 0;
        }

        if (!isset($data['view_count'])) {
            $data['view_count'] = 0;
        }

        if (!isset($data['notification_time'])) {
            $data['notification_time'] = current_time('mysql');
        }

        // Validate type
        $validTypes = ['success', 'info', 'warning', 'error'];
        if (!in_array($data['type'], $validTypes)) {
            $data['type'] = 'info';
        }

        // Handle action_link JSON
        if (isset($data['action_link']) && is_array($data['action_link'])) {
            $data['action_link'] = json_encode($data['action_link']);
        }

        $notification = new NotificationModel();
        $notification->fill($data);
        
        if ($notification->save()) {
            return $notification;
        }

        return null;
    }

    /**
     * Get notifications for a user
     *
     * @param int $userId
     * @param array $filters
     * @return array
     */
    public static function getUserNotifications(int $userId, array $filters = []): array
    {
        if (!$userId) {
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
            $userId = !empty($admins) ? (int) $admins[0] : 0;
        }

        if (!$userId) {
            return ['data' => [], 'total' => 0, 'page' => 1, 'per_page' => 20, 'total_pages' => 0];
        }

        $query = NotificationModel::query()->byUser($userId);

        // Filter by viewed status
        if (isset($filters['is_viewed']) && $filters['is_viewed'] == 0) {
            $query->unread();
        }

        // Filter by type
        if (!empty($filters['type'])) {
            $query->byType($filters['type']);
        }

        // Pagination
        $page = isset($filters['page']) ? intval($filters['page']) : 1;
        $per_page = isset($filters['per_page']) ? intval($filters['per_page']) : 20;
        $offset = ($page - 1) * $per_page;

        $total = $query->count();
        $notifications = $query->orderBy('notification_time', 'DESC')
            ->limit($per_page)
            ->offset($offset)
            ->get();

        return [
            'data' => $notifications,
            'total' => $total,
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total / $per_page),
        ];
    }

    /**
     * Get unread count for a user
     *
     * @param int $userId
     * @return int
     */
    public static function getUnreadCount(int $userId): int
    {
        if (!$userId) {
            $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
            $userId = !empty($admins) ? (int) $admins[0] : 0;
        }

        return NotificationModel::query()
            ->byUser($userId)
            ->unread()
            ->count();
    }

    /**
     * Mark notification as viewed
     *
     * @param int $notificationId
     * @return bool
     */
    public static function markAsViewed(int $notificationId): bool
    {
        $notification = NotificationModel::find($notificationId);
        if (!$notification) {
            return false;
        }

        return $notification->markAsViewed();
    }

    /**
     * Mark all notifications as viewed for a user
     *
     * @param int $userId
     * @return bool
     */
    public static function markAllAsViewed(int $userId): bool
    {
        $notifications = NotificationModel::query()
            ->byUser($userId)
            ->unread()
            ->get();

        foreach ($notifications as $notification) {
            $notification->is_viewed  = true;
            $notification->view_count = ($notification->view_count ?? 0) + 1;
            $notification->save();
        }

        return true;
    }

    /**
     * Delete old notifications
     *
     * @param int $days
     * @return int Number of deleted notifications
     */
    public static function deleteOldNotifications(int $days = 30): int
    {
        $date = gmdate('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return NotificationModel::query()
            ->where('notification_time', '<', $date)
            ->where('is_viewed', 1)
            ->delete();
    }

    /**
     * Create notification for appointment booking
     *
     * @param array $appointmentData
     * @return NotificationModel|null
     */
    public static function createAppointmentNotification(array $appointmentData): ?NotificationModel
    {
        $customerName = $appointmentData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');
        $serviceName = $appointmentData['service_name'] ?? esc_html__('Service', 'rox-appointment-booking');

        return self::create([
            'user_id' => $appointmentData['admin_user_id'] ?? null,
            'title' => esc_html__('New appointment booked', 'rox-appointment-booking'),
            'description' => sprintf(
                // translators: %1$s = customer name, %2$s = service name
                esc_html__('%1$s booked %2$s', 'rox-appointment-booking'),
                esc_html($customerName),
                esc_html($serviceName)
            ),
            'type' => 'success',
            'action_link' => [
                'type' => 'navigate',
                'route' => '/appointment/' . ($appointmentData['appointment_id'] ?? '')
            ]
        ]);
    }

    /**
     * Create notification for payment received
     *
     * @param array $paymentData
     * @return NotificationModel|null
     */
    public static function createPaymentNotification(array $paymentData): ?NotificationModel
    {
        $customerName = $paymentData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');
        $amount = $paymentData['amount'] ?? 0;
        $status = strtolower($paymentData['status'] ?? 'paid');

        if ($status === 'failed') {
            $title = __('Payment failed', 'rox-appointment-booking');
            $description = sprintf(
                // translators: 1: payment amount, 2: customer name
                __('Payment of %1$s from %2$s failed', 'rox-appointment-booking'),
                rox_appointment_booking_format_currency($amount),
                $customerName
            );
            $type = 'error';
        } elseif ($status === 'refunded') {
            $title = __('Payment refunded', 'rox-appointment-booking');
            $description = sprintf(
                // translators: 1: payment amount, 2: customer name
                __('Payment of %1$s to %2$s refunded', 'rox-appointment-booking'),
                rox_appointment_booking_format_currency($amount),
                $customerName
            );
            $type = 'warning';
        } elseif ($status === 'unpaid') {
            $title = __('Payment unpaid', 'rox-appointment-booking');
            $description = sprintf(
                // translators: 1: payment amount, 2: customer name
                __('Payment of %1$s from %2$s is unpaid', 'rox-appointment-booking'),
                rox_appointment_booking_format_currency($amount),
                $customerName
            );
            $type = 'info';
        } else {
            $title = __('Payment received', 'rox-appointment-booking');
            $description = sprintf(
                // translators: 1: payment amount, 2: customer name
                __('%1$s received from %2$s', 'rox-appointment-booking'),
                rox_appointment_booking_format_currency($amount),
                $customerName
            );
            $type = 'success';
        }

        return self::create([
            'user_id' => $paymentData['admin_user_id'] ?? null,
            'title' => $title,
            'description' => $description,
            'type' => $type,
            'action_link' => [
                'type' => 'navigate',
                'route' => '/payments/' . ($paymentData['payment_id'] ?? '')
            ]
        ]);
    }

    /**
     * Create notification for appointment cancellation
     *
     * @param array $appointmentData
     * @return NotificationModel|null
     */
    public static function createCancellationNotification(array $appointmentData): ?NotificationModel
    {
        $customerName = $appointmentData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');

        return self::create([
            'user_id' => $appointmentData['admin_user_id'] ?? null,
            'title' => esc_html__('Appointment cancelled', 'rox-appointment-booking'),
            'description' => sprintf(
                // translators: %s = customer name
                esc_html__('%s cancelled their appointment', 'rox-appointment-booking'),
                esc_html($customerName)
            ),
            'type' => 'warning',
            'action_link' => [
                'type' => 'navigate',
                'route' => '/appointments/' . ($appointmentData['appointment_id'] ?? '')
            ]
        ]);
    }
    /**
     * Create notification when admin creates a booking from the admin panel
     *
     * @param array $appointmentData
     * @return NotificationModel|null
     */
    public static function createAdminBookingNotification(array $appointmentData): ?NotificationModel
    {
        $customerName = $appointmentData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');
        $serviceName  = $appointmentData['service_name'] ?? esc_html__('Service', 'rox-appointment-booking');

        return self::create([
            'user_id'     => $appointmentData['admin_user_id'] ?? null,
            'title'       => esc_html__('New booking created by admin', 'rox-appointment-booking'),
            'description' => sprintf(
                // translators: %1$s = customer name, %2$s = service name
                esc_html__('Admin booked %2$s for %1$s', 'rox-appointment-booking'),
                esc_html($customerName),
                esc_html($serviceName)
            ),
            'type'        => 'success',
            'action_link' => [
                'type'  => 'navigate',
                'route' => '/appointment/' . ($appointmentData['appointment_id'] ?? ''),
            ],
        ]);
    }

    /**
     * Create notification when an appointment is rescheduled via the calendar or admin form
     *
     * @param array $appointmentData Keys: admin_user_id, appointment_id, customer_name, service_name, old_date, old_start_time, new_date, new_start_time
     * @return NotificationModel|null
     */
    public static function createRescheduleNotification(array $appointmentData): ?NotificationModel
    {
        $customerName   = $appointmentData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');
        $serviceName    = $appointmentData['service_name'] ?? esc_html__('Service', 'rox-appointment-booking');
        $oldDate        = $appointmentData['old_date'] ?? '';
        $oldStartTime   = $appointmentData['old_start_time'] ?? '';
        $newDate        = $appointmentData['new_date'] ?? '';
        $newStartTime   = $appointmentData['new_start_time'] ?? '';

        // Build human-readable "from" / "to" strings combining date + time when available
        $oldLabel = $oldDate;
        if ($oldStartTime) {
            $timeOnly = gmdate('H:i', strtotime($oldStartTime));
            $oldLabel = $oldDate ? "{$oldDate} {$timeOnly}" : $timeOnly;
        }

        $newLabel = $newDate;
        if ($newStartTime) {
            $timeOnly = gmdate('H:i', strtotime($newStartTime));
            $newLabel = $newDate ? "{$newDate} {$timeOnly}" : $timeOnly;
        }

        return self::create([
            'user_id'     => $appointmentData['admin_user_id'] ?? null,
            'title'       => esc_html__('Appointment rescheduled', 'rox-appointment-booking'),
            'description' => sprintf(
                // translators: 1: customer name, 2: service name, 3: old date/time, 4: new date/time
                esc_html__('%1$s\'s %2$s rescheduled from %3$s to %4$s', 'rox-appointment-booking'),
                esc_html($customerName),
                esc_html($serviceName),
                esc_html($oldLabel),
                esc_html($newLabel)
            ),
            'type'        => 'info',
            'action_link' => [
                'type'  => 'navigate',
                'route' => '/appointment/' . ($appointmentData['appointment_id'] ?? ''),
            ],
        ]);
    }

    /**
     * Create notification for an order status change
     *
     * @param array $orderData Keys: admin_user_id, order_id, order_status, customer_name, total_amount
     * @return NotificationModel|null
     */
    public static function createOrderStatusNotification(array $orderData): ?NotificationModel
    {
        $status       = strtolower($orderData['order_status'] ?? 'pending_payment');
        $customerName = $orderData['customer_name'] ?? esc_html__('Customer', 'rox-appointment-booking');
        $amount       = $orderData['total_amount'] ?? 0;

        $typeMap = [
            'completed'       => 'success',
            'paid'            => 'success',
            'pending_payment' => 'info',
            'processing'      => 'info',
            'on_hold'         => 'warning',
            'cancelled'       => 'warning',
            'refunded'        => 'warning',
            'failed'          => 'error',
        ];

        $labelMap = [
            'completed'       => __('Completed', 'rox-appointment-booking'),
            'paid'            => __('Paid', 'rox-appointment-booking'),
            'pending_payment' => __('Pending Payment', 'rox-appointment-booking'),
            'processing'      => __('Processing', 'rox-appointment-booking'),
            'on_hold'         => __('On Hold', 'rox-appointment-booking'),
            'cancelled'       => __('Cancelled', 'rox-appointment-booking'),
            'refunded'        => __('Refunded', 'rox-appointment-booking'),
            'failed'          => __('Failed', 'rox-appointment-booking'),
        ];

        $type        = $typeMap[$status] ?? 'info';
        $statusLabel = $labelMap[$status] ?? ucfirst(str_replace('_', ' ', $status));

        return self::create([
            'user_id'     => $orderData['admin_user_id'] ?? null,
            'title'       => sprintf(
                // translators: %s = order status label
                __('Order %s', 'rox-appointment-booking'),
                $statusLabel
            ),
            'description' => sprintf(
                // translators: 1: customer name, 2: formatted amount, 3: order status label
                __('Order for %1$s (%2$s) is now %3$s', 'rox-appointment-booking'),
                esc_html($customerName),
                esc_html(rox_appointment_booking_format_currency($amount)),
                $statusLabel
            ),
            'type'        => $type,
            'action_link' => [
                'type'  => 'navigate',
                'route' => '/orders/' . ($orderData['order_id'] ?? ''),
            ],
        ]);
    }

    /**
     * Create notification for appointment status change
     *
     * @param array $appointmentData
     * @param string $newStatus
     * @return NotificationModel|null
     */
    public static function createStatusChangeNotification(array $appointmentData, string $newStatus): ?NotificationModel
    {
        $customerName = $appointmentData['customer_name'] ?? __('Customer', 'rox-appointment-booking');
        $serviceName = $appointmentData['service_name'] ?? __('Service', 'rox-appointment-booking');

        $typeMap = [
            'approved' => 'success',
            'completed' => 'success',
            'pending' => 'info',
            'rescheduled' => 'info',
            'canceled' => 'warning',
            'rejected' => 'error',
            'emergency' => 'error'
        ];

        $type = $typeMap[strtolower($newStatus)] ?? 'info';

        $statusLabels = [
            'approved' => __('Approved', 'rox-appointment-booking'),
            'completed' => __('Completed', 'rox-appointment-booking'),
            'pending' => __('Pending', 'rox-appointment-booking'),
            'rescheduled' => __('Rescheduled', 'rox-appointment-booking'),
            'canceled' => __('Canceled', 'rox-appointment-booking'),
            'rejected' => __('Rejected', 'rox-appointment-booking'),
            'emergency' => __('Emergency', 'rox-appointment-booking')
        ];

        $statusLabel = $statusLabels[strtolower($newStatus)] ?? ucfirst($newStatus);
        return self::create([
            'user_id' => $appointmentData['admin_user_id'] ?? null,
            'title' => sprintf(
                // translators: %s = appointment status label
                __('Appointment %s', 'rox-appointment-booking'),
                $statusLabel
            ),
            'description' => sprintf(
                // translators: 1: service name, 2: customer name, 3: appointment status label
                __('Appointment for %1$s with %2$s is now %3$s', 'rox-appointment-booking'),
                $serviceName,
                $customerName,
                $statusLabel
            ),
            'type' => $type,
            'action_link' => [
                'type' => 'navigate',
                'route' => '/appointment/' . ($appointmentData['appointment_id'] ?? '')
            ]
        ]);
    }
}
