<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class SaveGeneral
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving general settings data via REST API.
 */
class SaveGeneral extends AbstractREST
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
    public static string $route = '/general-settings/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/general-settings/save';

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
                message: esc_html__('General settings data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            // Extract country ISO from phone code if provided
            if (isset($params['default_phone_country_code'])) {
                $params['default_phone_country_iso'] = rox_appointment_booking_get_country_iso_by_phone_code($params['default_phone_country_code']);
            }
            
            update_option('rox_appointment_booking_general_settings', $params);
            
            return rox_appointment_booking_rest_response(
                data: $params,
                code: 200,
                message: esc_html__('General settings saved successfully', 'rox-appointment-booking')
            );
            
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving general settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
