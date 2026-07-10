<?php

namespace RoxAppointmentBooking\Modules\Payment\REST;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Payment\Services\StripePaymentService;

defined('ABSPATH') || exit;

/**
 * Class StripeConnection. Handles Stripe connection, disconnection, and status retrieval via REST API.
 * 
 * @package RoxAppointmentBooking\Modules\Payment\REST
 */
class StripeConnection extends AbstractREST
{
    public static $loadable = true;
    public static string $route = '/stripe/(?P<action>connect|disconnect|status)';
    public static string $usableRoute = '/stripe/connect';

    /**
     * Get allowed HTTP methods for this endpoint.
     * 
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['GET', 'POST', 'DELETE'];
    }

    /**
     * Check permissions for the REST request.
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
     * Handle the REST API request for Stripe connection management.
     * 
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $action = sanitize_key($request->get_param('action'));
        $method = $request->get_method();
        $service = new StripePaymentService();

        if ($action === 'status') {
            if ($method !== 'GET') {
                return new WP_Error('method_not_allowed', esc_html__('Invalid request method', 'rox-appointment-booking'), ['status' => 405]);
            }

            return rox_appointment_booking_rest_response(
                data: $service->getConnectionStatus(),
                code: 200,
                message: esc_html__('Stripe connection status retrieved successfully', 'rox-appointment-booking')
            );
        }

        if ($action === 'disconnect') {
            if (!in_array($method, ['POST', 'DELETE'], true)) {
                return new WP_Error('method_not_allowed', esc_html__('Invalid request method', 'rox-appointment-booking'), ['status' => 405]);
            }

            $result = $service->disconnect();

            return rox_appointment_booking_rest_response(
                data: $result,
                code: 200,
                message: esc_html__('Stripe disconnected successfully', 'rox-appointment-booking')
            );
        }

        if ($method !== 'POST') {
            return new WP_Error('method_not_allowed', esc_html__('Invalid request method', 'rox-appointment-booking'), ['status' => 405]);
        }

        $params = $request->get_json_params();
        if (!is_array($params) || empty($params)) {
            $params = $request->get_params();
        }

        $result = $service->connect(
            (string) ($params['stripe_publishable_key'] ?? $params['publishable_key'] ?? ''),
            (string) ($params['stripe_secret_key'] ?? $params['secret_key'] ?? '')
        );

        if (!$result['success']) {
            return rox_appointment_booking_rest_response(
                data: $result,
                code: 400,
                message: $result['error'] ?? esc_html__('Stripe connection failed', 'rox-appointment-booking'),
                headers: ['status' => 400]
            );
        }

        return rox_appointment_booking_rest_response(
            data: $result,
            code: 200,
            message: esc_html__('Stripe connected successfully', 'rox-appointment-booking')
        );
    }
}
