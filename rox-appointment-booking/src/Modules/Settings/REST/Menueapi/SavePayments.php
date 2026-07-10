<?php

namespace RoxAppointmentBooking\Modules\Settings\REST\Menueapi;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;

/**
 * Class SavePayments
 *
 * @package RoxAppointmentBooking\Modules\Settings\REST\Menueapi
 * @description Handles saving payments settings data via REST API.
 */
class SavePayments extends AbstractREST
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
    public static string $route = '/payments-settings/save';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/payments-settings/save';

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
     * Validate that at least one payment option is enabled.
     *
     * Previously lived on Settings\Menu\Payments; relocated here when the
     * payments settings *structure* moved to the static JS config.
     *
     * @param array $params
     * @return true|string True if valid, error message string if not.
     */
    private static function validatePaymentOptions(array $params): bool|string
    {
        $stripeEnabled   = filter_var($params['stripe_payment_gateway_enable'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $payLaterEnabled = filter_var($params['pay_later_payment_option_enable'] ?? false, FILTER_VALIDATE_BOOLEAN);

        if (!$stripeEnabled && !$payLaterEnabled) {
            return esc_html__('At least one payment option must be enabled.', 'rox-appointment-booking');
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
                message: esc_html__('Payments settings data is required', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        try {
            $validation = self::validatePaymentOptions($params);
            if ($validation !== true) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 400,
                    message: $validation,
                    headers: ['status' => 400]
                );
            }

            // Convert payment_gateway_enable to boolean for featuretoggle component
            if (isset($params['payment_gateway_enable'])) {
                $params['payment_gateway_enable'] = filter_var(
                    $params['payment_gateway_enable'], 
                    FILTER_VALIDATE_BOOLEAN
                );
            } else {
                // Set default to false when not present
                $params['payment_gateway_enable'] = false;
            }

            $existingSettings = rox_appointment_booking_payment_settings() ?? [];
            $connectionFields = [
                'stripe_connection_status',
                'stripe_account_id',
                'stripe_account_email',
                'stripe_account_country',
                'stripe_mode',
                'stripe_connected_at',
            ];

            $stripeKeysChanged = (
                ($params['stripe_publishable_key'] ?? '') !== ($existingSettings['stripe_publishable_key'] ?? '')
                || ($params['stripe_secret_key'] ?? '') !== ($existingSettings['stripe_secret_key'] ?? '')
            );

            foreach ($connectionFields as $field) {
                if (array_key_exists($field, $params)) {
                    continue;
                }

                $params[$field] = $stripeKeysChanged
                    ? ($field === 'stripe_connection_status' ? 'disconnected' : '')
                    : ($existingSettings[$field] ?? ($field === 'stripe_connection_status' ? 'disconnected' : ''));
            }
            
            update_option('rox_appointment_booking_payments_settings', $params);
            
            return rox_appointment_booking_rest_response(
                data: $params,
                code: 200,
                message: esc_html__('Payments settings saved successfully', 'rox-appointment-booking')
            );
            
        } catch (\Exception $e) {
            return rox_appointment_booking_rest_response(
                data: null,
                code: 500,
                message: [
                    'error' => [
                        // translators: %s = error message
                        sprintf(esc_html__('Error saving payments settings: %s', 'rox-appointment-booking'), esc_html($e->getMessage()))
                    ]
                ],
                headers: ['status' => 500]
            );
        }
    }
}
