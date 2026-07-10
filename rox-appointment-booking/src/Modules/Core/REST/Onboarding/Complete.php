<?php

namespace RoxAppointmentBooking\Modules\Core\REST\Onboarding;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

defined('ABSPATH') || exit;

/**
 * Class Complete
 *
 * @package RoxAppointmentBooking\Modules\Core\REST\Onboarding
 * @description Handles the completion of the onboarding process via REST API.
 */
class Complete extends AbstractREST
{
    public static $loadable = true;

    public static string $route = 'onboarding/complete';

    /**
     * Get the allowed HTTP methods for this endpoint.
     *
     * @return string|array The allowed HTTP method(s).
     */
    protected function getMethods(): string|array
    {
        return 'POST';
    }

    /**
     * Handle the REST API request to complete onboarding.
     *
     * @param WP_REST_Request $request The REST request object.
     * @return WP_REST_Response|WP_Error The response object or an error.
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $onboarded = (int) $request->get_param('onboarded');

        if ($onboarded !== 1) {
            return new WP_Error(
                'invalid_value',
                esc_html__('Invalid value. onboarded must be 1.', 'rox-appointment-booking'),
                ['status' => 422]
            );
        }

        update_option('rox_appointment_booking_onboarded', 1);

        return rox_appointment_booking_rest_response(
            data: ['onboarded' => true],
            message: [
                'success' => [
                    esc_html__('Onboarding completed successfully.', 'rox-appointment-booking'),
                ],
            ]
        );
    }

    /**
     * Checks if the current user has permission to complete the onboarding process.
     * 
     * Only users with 'manage_options' capability can complete onboarding.
     * 
     * @param WP_REST_Request $request The REST request object.
     * @return bool True if the user has permission, false otherwise.
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
}
