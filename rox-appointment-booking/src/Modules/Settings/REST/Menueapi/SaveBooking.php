<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class SaveBooking
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving booking settings data via REST API.
 */
class SaveBooking extends AbstractREST
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
    public static string $route = '/booking-settings/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/booking-settings/save';

    /**
     * Keys owned by the Booking settings menu. They stay on the shared general
     * settings option (CustomerService reads them from there), so only these
     * are merged in — everything else on the option is left untouched.
     *
     * @var array
     */
    private const BOOKING_KEYS = [
        'customer_create_auto_enable',
        'customer_auto_login_enable',
        'customer_reschedule_enable',
        'customer_cancel_enable',
        'agent_reschedule_enable',
        'agent_cancel_enable',
    ];

    /**
     * Get the methods allowed for this route
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['POST', 'PUT'];
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
        $params = $request->get_params();

        if (empty($params)) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 400,
                message: esc_html__('Booking settings data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            $general_settings = get_option('rox_appointment_booking_general_settings', []);

            if (!is_array($general_settings)) {
                $general_settings = [];
            }

            $booking_settings = [];

            foreach (self::BOOKING_KEYS as $key) {
                $value = filter_var($params[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
                $general_settings[$key] = $value;
                $booking_settings[$key] = $value;
            }

            update_option('rox_appointment_booking_general_settings', $general_settings);

            return rox_appointment_booking_rest_response(
                data: $booking_settings,
                code: 200,
                message: esc_html__('Booking settings saved successfully', 'rox-appointment-booking')
            );

        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving booking settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
