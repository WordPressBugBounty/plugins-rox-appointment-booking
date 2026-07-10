<?php

namespace RoxAppointmentBooking\Modules\Notification\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Notification\Services\NotificationService;

/**
 * Class SaveNotification
 * 
 * @package RoxAppointmentBooking\Modules\Notification\REST
 * @description Handles creating notifications via REST API.
 */
class SaveNotification extends AbstractREST
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
    public static string $route = '/notifications/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/notifications/save';

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

            if (empty($data)) {
                return new WP_Error(
                    'missing_data',
                    esc_html__('Notification data is required', 'rox-appointment-booking'),
                    ['status' => 400]
                );
            }

            // Create notification
            $notification = NotificationService::create($data);

            if (!$notification) {
                return new WP_Error(
                    'create_failed',
                    esc_html__('Failed to create notification', 'rox-appointment-booking'),
                    ['status' => 500]
                );
            }

            return new WP_REST_Response([
                'success' => true,
                'message' => esc_html__('Notification created successfully', 'rox-appointment-booking'),
                'data' => [
                    'id' => $notification->getID(),
                    'title' => $notification->title,
                    'description' => $notification->description,
                    'type' => $notification->type,
                    'user_id' => $notification->user_id,
                ]
            ], 201);

        } catch (\Exception $e) {
            return new WP_Error(
                'save_notification_error',
                // translators: %s = error message
                sprintf(esc_html__('Error creating notification: %s', 'rox-appointment-booking'), esc_html($e->getMessage())),
                ['status' => 500]
            );
        }
    }
}
