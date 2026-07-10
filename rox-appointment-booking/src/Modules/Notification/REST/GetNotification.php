<?php

namespace RoxAppointmentBooking\Modules\Notification\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Order\Data\OrderModel;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Notification\Data\NotificationModel;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class GetNotification
 * 
 * @package RoxAppointmentBooking\Modules\Notification\REST
 * @description Handles retrieving notifications via REST API.
 */
class GetNotification extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/notifications(?:/(?P<id>\d+))?';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/notifications/';

    /**
     * Get the methods allowed for this route
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Check if the user has permission to access this endpoint
     * 
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        if (!is_user_logged_in() || !current_user_can('manage_options')) {
            return false;
        }

        return true;
    }

    /**
     * Handle the REST API request
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = $request->get_param('id');
            $currentUserId = get_current_user_id();

            // Get single notification
            if ($id) {
                $notification = NotificationModel::find($id);

                if (!$notification) {
                    return new WP_Error(
                        'notification_not_found',
                        esc_html__('Notification not found', 'rox-appointment-booking'),
                        ['status' => 404]
                    );
                }

                // Check if notification belongs to current user
                if ($notification->user_id && $notification->user_id != $currentUserId) {
                    return new WP_Error(
                        'unauthorized',
                        esc_html__('You do not have permission to view this notification', 'rox-appointment-booking'),
                        ['status' => 403]
                    );
                }

                return new WP_REST_Response([
                    'code' => 200,
                    'success' => true,
                    'message' => [
                        'success' => ['Notifications retrieved successfully'],
                    ],
                    'data' => [
                        'action' =>  get_rest_url(null, '/rox-appointment-booking/v1/notifications/mark-viewed'),
                        'items'  => [
                            $this->formatNotification($notification)
                        ]
                    ]
                ], 200);
            }

            // Get list of notifications
            $filters = [
                'is_viewed' => $request->get_param('is_viewed'),
                'type' => $request->get_param('type'),
                'page' => $request->get_param('page') ?? 1,
                'per_page' => $request->get_param('per_page') ?? 20,
            ];

            $result = NotificationService::getUserNotifications($currentUserId, $filters);

            // Format notifications
            $notifications = is_array($result['data']) ? $result['data'] : $result['data']->all();
            $formattedData = array_map(function ($notification) {
                return $this->formatNotification($notification);
            }, $notifications);

            return new WP_REST_Response([
                'code' => 200,
                'message' => [
                    'success' => ['Notifications retrieved successfully'],
                ],
                'data' => [
                    'action' =>  get_rest_url(null, '/rox-appointment-booking/v1/notifications/mark-viewed'),
                    'items' => $formattedData,
                ],
                'success' => true,
            ], 200);
        } catch (\Exception $e) {
            return new WP_Error(
                'get_notifications_error',
                $e->getMessage(),
                ['status' => 500]
            );
        }
    }

    /**
     * Format notification data for response
     * 
     * @param NotificationModel $notification
     * @return array
     */
    protected function formatNotification(NotificationModel $notification): array
    {
        return [
            'id' => $notification->getID(),
            'type' => $notification->type,
            'title' => $notification->title,
            'description' => $notification->description,
            'created_at' => $notification->created_at,
            'time_ago' => $notification->getTimeAgo(),
            'status' => (int) (bool) $notification->is_viewed,
            'route' => $this->resolveNotificationRoute($notification),
        ];
    }
    /**
     * Resolve the frontend route for a notification.
     *
     * Appointment notifications should point to the main appointment,
     * while payment notifications should point to the payment details page.
     *
     * @param NotificationModel $notification
     * @return string
     */
    protected function resolveNotificationRoute(NotificationModel $notification): string
    {
        $actionLink = $notification->action_link;

        if (is_string($actionLink)) {
            $decoded = json_decode($actionLink, true);
            $actionLink = is_array($decoded) ? $decoded : [];
        }

        if (!is_array($actionLink)) {
            return '/notifications/' . $notification->getID();
        }

        $route = trim((string) ($actionLink['route'] ?? ''));

        if ($route === '') {
            return '/notifications/' . $notification->getID();
        }

        $normalizedRoute = '/' . ltrim($route, '/');
        $routeParts = array_values(array_filter(explode('/', trim($normalizedRoute, '/'))));
        $resource = $routeParts[0] ?? '';
        $resourceId = isset($routeParts[1]) ? (int) $routeParts[1] : 0;

        if ($resource === 'appointment') {
            $appointmentId = $this->resolveMainAppointmentId($resourceId);
            return '/appointment/' . $appointmentId;
        }

        // Payments have no frontend page; point the notification at the related
        // order instead (resolved from the payment's order_id).
        if ($resource === 'payments' && $resourceId > 0) {
            $orderId = $this->resolveOrderIdFromPayment($resourceId);

            if ($orderId > 0) {
                return '/orders/' . $orderId;
            }

            return '/notifications/' . $notification->getID();
        }

        return $normalizedRoute;
    }

    /**
     * Resolve the order attached to a given payment.
     *
     * @param int $paymentId
     * @return int
     */
    protected function resolveOrderIdFromPayment(int $paymentId): int
    {
        if ($paymentId <= 0) {
            return 0;
        }

        $payment = PaymentModel::find($paymentId);

        return $payment ? (int) $payment->order_id : 0;
    }

    /**
     * Resolve the first appointment attached to the order for a given appointment.
     *
     * @param int $appointmentId
     * @return int
     */
    protected function resolveMainAppointmentId(int $appointmentId): int
    {
        if ($appointmentId <= 0) {
            return 0;
        }

        $order = OrderModel::where('booking_ids', 'LIKE', '%"' . $appointmentId . '"%')->first();

        if ($order) {
            $bookingIds = $order->getBookingIds();
            $mainAppointmentId = isset($bookingIds[0]) ? (int) $bookingIds[0] : 0;

            if ($mainAppointmentId > 0) {
                return $mainAppointmentId;
            }
        }

        return $appointmentId;
    }
}
