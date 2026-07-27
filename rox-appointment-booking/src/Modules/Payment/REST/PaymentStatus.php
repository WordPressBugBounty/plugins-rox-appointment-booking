<?php

namespace RoxAppointmentBooking\Modules\Payment\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Payment\Data\PaymentModel;
use RoxAppointmentBooking\Modules\Payment\Services\PaymentStatusSyncService;

defined('ABSPATH') || exit;

/**
 * Class PaymentStatus
 *
 * @package RoxAppointmentBooking\Modules\Payment
 * @description Handles PaymentStatus functionality.
 */
class PaymentStatus extends AbstractREST
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
    public static string $route = '/payment/status-update/(?P<id>\d+)';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return ['PUT'];
    }

    /**
     * Check whether the current user can access this endpoint.
     *
     * @param WP_REST_Request $request REST request instance.
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
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = intval($request->get_param('id'));
        $body = $request->get_json_params();
        $status = sanitize_text_field($body['status'] ?? '');

        $allowed = [
            PaymentModel::STATUS_UNPAID,
            PaymentModel::STATUS_PAID,
            PaymentModel::STATUS_FAILED,
            PaymentModel::STATUS_REFUNDED
        ];

        if (!in_array($status, $allowed, true)) {
            return new WP_Error(
                'invalid_status',
                sprintf(
                    // translators: %s = comma-separated list of allowed statuses
                    esc_html__('Invalid status. Allowed: %s', 'rox-appointment-booking'),
                    implode(', ', $allowed)
                ),
                ['status' => 400]
            );
        }

        $payment = PaymentStatusSyncService::applyStatus($id, $status);

        if (!$payment) {
            return new WP_Error(
                'payment_not_found',
                esc_html__('Payment not found', 'rox-appointment-booking'),
                ['status' => 404]
            );
        }

        return rox_appointment_booking_rest_response(
            data: ['id' => $id, 'status' => $status],
            message: [
                'success' => [
                    esc_html__('Payment status updated successfully', 'rox-appointment-booking')
                ]
            ]
        );
    }
}
