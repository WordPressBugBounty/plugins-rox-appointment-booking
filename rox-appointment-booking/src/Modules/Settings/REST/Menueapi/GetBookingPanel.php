<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetBookingPanel
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves booking panel data (shortcode) via REST API.
 */
class GetBookingPanel extends AbstractREST
{
    public static $loadable = true;
    public static string $route = '/booking-panel/get';
    public static string $usableRoute = '/booking-panel/get';

    protected function getMethods(): string|array
    {
        return 'GET';
    }

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

    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $shortcode = '[rox_appointment_booking]';

            return rox_appointment_booking_rest_response(
                data: [
                    'shortcode' => $shortcode
                ],
                code: 200,
                message: [
                    'success' => [
                        esc_html__('Booking panel shortcode retrieved successfully', 'rox-appointment-booking')
                    ]
                ]
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        sprintf(esc_html__('Error retrieving booking panel shortcode: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
