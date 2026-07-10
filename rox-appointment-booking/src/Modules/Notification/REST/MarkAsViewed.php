<?php

namespace RoxAppointmentBooking\Modules\Notification\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;
use RoxAppointmentBooking\Modules\Notification\Data\NotificationModel;

/**
 * Class MarkAsViewed
 * 
 * @package RoxAppointmentBooking\Modules\Notification\REST
 * @description Handles marking notifications as viewed via REST API.
 */
class MarkAsViewed extends AbstractREST
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
    public static string $route = '/notifications/mark-viewed';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/notifications/mark-viewed';

    /**
     * Get the methods allowed for this route
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
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
            $data = $request->get_json_params();
            $notificationId = $data['notification_id'] ?? null;
            $markAll = $data['mark_all'] ?? false;
            $currentUserId = get_current_user_id();

            // Mark all as viewed
            if ($markAll) {
                $success = NotificationService::markAllAsViewed($currentUserId);
                
                if (!$success) {
                    return new WP_Error(
                        'mark_failed',
                        esc_html__('Failed to mark notifications as viewed', 'rox-appointment-booking'),
                        ['status' => 500]
                    );
                }

                return new WP_REST_Response([
                    'success' => true,
                    'message' => esc_html__('All notifications marked as viewed', 'rox-appointment-booking'),
                    'unread_count' => 0
                ], 200);
            }

            // Mark single notification as viewed
            if (!$notificationId) {
                return new WP_Error(
                    'notification_id ',
                    esc_html__('notification_id is required', 'rox-appointment-booking'),
                    ['status' => 400]
                );
            }

            // Verify notification belongs to current user
            $notification = NotificationModel::find($notificationId);
            if (!$notification) {
                return new WP_Error(
                    'not_found',
                    esc_html__('Notification not found', 'rox-appointment-booking'),
                    ['status' => 404]
                );
            }

            if ($notification->user_id && $notification->user_id != $currentUserId) {
                return new WP_Error(
                    'unauthorized',
                    esc_html__('You do not have permission to update this notification', 'rox-appointment-booking'),
                    ['status' => 403]
                );
            }

            $success = NotificationService::markAsViewed($notificationId);
            
            if (!$success) {
                return new WP_Error(
                    'mark_failed',
                    esc_html__('Failed to mark notification as viewed', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => esc_html__('Notification marked as viewed', 'rox-appointment-booking'),
                'unread_count' => NotificationService::getUnreadCount($currentUserId)
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'mark_viewed_error',
                // translators: %s = error message
                sprintf(esc_html__('Error marking notification as viewed: %s', 'rox-appointment-booking'), esc_html($e->getMessage())),
                ['status' => 500]
            );
        }
    }
}
