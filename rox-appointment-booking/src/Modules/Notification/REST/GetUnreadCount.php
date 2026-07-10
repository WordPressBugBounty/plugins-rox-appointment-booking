<?php

namespace RoxAppointmentBooking\Modules\Notification\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class GetUnreadCount
 * 
 * @package RoxAppointmentBooking\Modules\Notification\REST
 * @description Handles retrieving unread notification count via REST API.
 */
class GetUnreadCount extends AbstractREST
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
    public static string $route = '/notifications/unread-count';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/notifications/unread-count';

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
            $currentUserId = get_current_user_id();
            $unreadCount = NotificationService::getUnreadCount($currentUserId);

            return new WP_REST_Response([
                'success' => true,
                'unread_count' => $unreadCount
            ], 200);

        } catch (\Exception $e) {
            return new WP_Error(
                'get_unread_count_error',
                // translators: %s = error message
                sprintf(esc_html__('Error getting unread count: %s', 'rox-appointment-booking'), esc_html($e->getMessage())),
                ['status' => 500]
            );
        }
    }
}
