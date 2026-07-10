<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class GetPayments
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Retrieves payments settings data via REST API.
 */
class GetPayments extends AbstractREST
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
    public static string $route = '/payments-settings/get';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/payments-settings/get';

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
            $payments_settings = rox_appointment_booking_payment_settings();
            
            // Set default values if not set
            $defaults = [
                'payment_gateway_enable' => false,
                'payment_currency' => rox_appointment_booking_payment_settings('payment_currency') ?? 'USD',
                'stripe_publishable_key' => rox_appointment_booking_payment_settings('stripe_publishable_key'),
                'stripe_secret_key' => rox_appointment_booking_payment_settings('stripe_secret_key'),
                'stripe_connection_status' => rox_appointment_booking_payment_settings('stripe_connection_status', 'disconnected'),
                'stripe_account_id' => rox_appointment_booking_payment_settings('stripe_account_id', ''),
                'stripe_account_email' => rox_appointment_booking_payment_settings('stripe_account_email', ''),
                'stripe_account_country' => rox_appointment_booking_payment_settings('stripe_account_country', ''),
                'stripe_mode' => rox_appointment_booking_payment_settings('stripe_mode', ''),
                'stripe_connected_at' => rox_appointment_booking_payment_settings('stripe_connected_at', ''),
                'default_payment_status' => rox_appointment_booking_payment_settings('default_payment_status') ?? 'unpaid'
            ];
            
            $payments_settings = wp_parse_args($payments_settings, $defaults);
            
            // Ensure payment_gateway_enable is a boolean for featuretoggle component
            if (isset($payments_settings['payment_gateway_enable'])) {
                $payments_settings['payment_gateway_enable'] = filter_var(
                    $payments_settings['payment_gateway_enable'], 
                    FILTER_VALIDATE_BOOLEAN
                );
            }

            return rox_appointment_booking_rest_response(
                data: $payments_settings,
                code: 200,
                message: [
                    'success' => [
                        esc_html__('Payments settings retrieved successfully', 'rox-appointment-booking')
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
                        sprintf(esc_html__('Error retrieving payments settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
