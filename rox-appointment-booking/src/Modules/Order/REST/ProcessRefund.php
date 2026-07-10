<?php

namespace RoxAppointmentBooking\Modules\Order\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Order\Services\OrderService;

/**
 * Class ProcessRefund
 *
 * @package RoxAppointmentBooking\Modules\Order\REST
 * @description Handles processing order refunds via REST API.
 */
class ProcessRefund extends AbstractREST
{
    /**
     * Whether this REST endpoint should be loaded.
     *
     * @var bool
     */
    public static $loadable = true;
    /**
     * REST route for this endpoint.
     *
     * @var string
     */
    public static string $route = 'orders/(?P<id>\d+)/refund';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'POST';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $orderService = new OrderService();
            $id = (int) $request->get_param('id');
            $amount = (float) $request->get_param('amount');
            $reason = $request->get_param('reason') ?: '';
            
            $order = $orderService->processRefund($id, $amount, $reason);

            return rox_appointment_booking_rest_response(
                data: $order->toArray(),
                message: [
                    'success' => [
                        esc_html__('Refund processed successfully', 'rox-appointment-booking')
                    ]
                ]
            );

        } catch (\Exception $e) {
            $status = $e->getMessage() === 'Order not found' ? 404 : 400;
            
            return new WP_Error(
                'refund_failed',
                $e->getMessage(),
                ['status' => $status]
            );
        }
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
}
