<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetLocation
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves location settings data via REST API.
 */
class GetLocation extends AbstractREST
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
    public static string $route = '/location-settings/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/location-settings/get';

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
            $location_settings = get_option('rox_appointment_booking_location_settings', []);
            
            // Set default values if not set
            $defaults = [
                'location_module_enable' => false
            ];
            
            $location_settings = wp_parse_args($location_settings, $defaults);
            
            // Ensure location_module_enable is a boolean for featuretoggle component
            if (isset($location_settings['location_module_enable'])) {
                $location_settings['location_module_enable'] = filter_var(
                    $location_settings['location_module_enable'], 
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            return rox_appointment_booking_rest_response(
                data: $location_settings,
                code: 200,
                message: [
                    'success' => [
                        esc_html__('Location settings retrieved successfully', 'rox-appointment-booking')
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
                        sprintf(esc_html__('Error retrieving location settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
