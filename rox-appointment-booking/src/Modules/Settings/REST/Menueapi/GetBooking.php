<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetBooking
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves booking settings data via REST API.
 */
class GetBooking extends AbstractREST
{
    /**
     * Whether this class should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = '/booking-settings/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/booking-settings/get';

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
            // The booking toggles are still persisted on the shared general
            // settings option (read by CustomerService), they only moved to
            // their own settings menu — so read/return just those keys.
            $general_settings = get_option('rox_appointment_booking_general_settings', []);

            $booking_settings = [
                'customer_create_auto_enable' => filter_var(
                    $general_settings['customer_create_auto_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'customer_auto_login_enable' => filter_var(
                    $general_settings['customer_auto_login_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'customer_reschedule_enable' => filter_var(
                    $general_settings['customer_reschedule_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'customer_cancel_enable' => filter_var(
                    $general_settings['customer_cancel_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'agent_reschedule_enable' => filter_var(
                    $general_settings['agent_reschedule_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
                'agent_cancel_enable' => filter_var(
                    $general_settings['agent_cancel_enable'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                ),
            ];

            return rox_appointment_booking_rest_response(
                data: $booking_settings,
                code: 200,
                message: [
                    'success' => [
                        esc_html__('Booking settings retrieved successfully', 'rox-appointment-booking')
                    ]
                ]
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error retrieving booking settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
