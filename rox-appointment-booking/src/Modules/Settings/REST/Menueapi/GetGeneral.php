<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetGeneral
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves general settings data via REST API.
 */
class GetGeneral extends AbstractREST
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
    public static string $route = '/general-settings/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/general-settings/get';

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
            $general_settings = get_option('rox_appointment_booking_general_settings', []);
            
            // Set default values if not set
            $defaults = [
                // pass usa
                'default_phone_country_code' => rox_appointment_booking_general_settings('default_phone_country_code'),
                'default_appointment_status' => 'pending',
                'blocking_appointment_statuses' => [],
                'time_system' => '12_hour',
                'date_format' => 'MM/DD/YYYY',
                'show_appointment_end_time' => false,
                'thousand_separator' => 'comma',
                'hide_price_breakdown_for_free_services' => false,
                // Booking Permissions — same keys BookingSettingsForm.jsx's
                // GetBooking.php defaults to false for; duplicated here so a
                // fresh install renders identically on both settings menus.
                'customer_create_auto_enable' => false,
                'customer_auto_login_enable' => false,
                'customer_reschedule_enable' => false,
                'customer_cancel_enable' => false,
                'agent_reschedule_enable' => false,
                'agent_cancel_enable' => false,
            ];

            $general_settings = wp_parse_args($general_settings, $defaults);

            // Stored on the Payments settings option (read/used everywhere via
            // rox_appointment_booking_payment_settings()), surfaced here since
            // the Currency field now lives on the General settings form.
            // Assigned after wp_parse_args (not as a default) because a stale
            // copy left on general_settings — from when this form persisted the
            // field itself — would otherwise shadow the real stored value.
            $general_settings['payment_currency'] = rox_appointment_booking_payment_settings('payment_currency') ?? 'USD';

            // Same reasoning as payment_currency above: stored on the Location
            // settings option (read by GetLocation.php/App.php/BookingPanelStructure.php),
            // surfaced here since the Locations toggle now also lives on the
            // General settings form.
            $location_settings = get_option('rox_appointment_booking_location_settings', []);
            $general_settings['location_module_enable'] = filter_var(
                $location_settings['location_module_enable'] ?? false,
                FILTER_VALIDATE_BOOLEAN
            );

            return rox_appointment_booking_rest_response(
                data: $general_settings,
                code: 200,
                message: [
                    'success' => [
                        esc_html__('General settings retrieved successfully', 'rox-appointment-booking')
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
                        sprintf(esc_html__('Error retrieving general settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
