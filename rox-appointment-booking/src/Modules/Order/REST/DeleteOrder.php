<?php

namespace RoxAppointmentBooking\Modules\Order\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Order\Services\OrderService;

/**
 * Class DeleteOrder
 *
 * @package RoxAppointmentBooking\Modules\Order\REST
 * @description Handles deleting orders via REST API.
 */
class DeleteOrder extends AbstractREST
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
    public static string $route = 'order(?:/(?P<id>\d+))?';
    /**
     * Human-readable route pattern used by the UI.
     *
     * @var string
     */
    public static string $usableRoute = '/order/{id}';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'DELETE';
    }

    /**
     * Handle the REST API request.
     *
     * @param WP_REST_Request $request REST request instance.
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $orderService = new OrderService();
        $ids = $request->get_param('ids');
        $id = $request->get_param('id');
        $deleted = [];
        $errors = [];

        if ($ids && is_array($ids)) {
            foreach ($ids as $singleId) {
                try {
                    $success = $orderService->deleteOrder((int)$singleId);
                    if ($success) {
                        $deleted[] = $singleId;
                    } else {
                        // translators: %d = order ID that failed to delete
                        $errors[] = sprintf(esc_html__('Failed to delete order ID %d', 'rox-appointment-booking'), $singleId);
                    }
                } catch (\Exception $e) {
                    $errors[] = $e->getMessage();
                }
            }
            $status = empty($errors) ? 200 : 500;
            return rox_appointment_booking_rest_response(
                data: ['deleted' => $deleted, 'errors' => $errors],
                code: $status,
                message: empty($errors)
                    ? esc_html__('Orders deleted successfully', 'rox-appointment-booking')
                    : esc_html__('Some orders could not be deleted', 'rox-appointment-booking'),
                headers: ['status' => $status]
            );
        }

        // Single delete fallback
        try {
            $success = $orderService->deleteOrder((int)$id);
            if (!$success) {
                return rox_appointment_booking_rest_response(
                    data: null,
                    code: 404,
                    message: esc_html__('Order not found', 'rox-appointment-booking'),
                    headers: ['status' => 404]
                );
            }
            return rox_appointment_booking_rest_response(
                data: null,
                message: esc_html__('Order deleted successfully', 'rox-appointment-booking'),
                code: 200
            );
        } catch (\Exception $e) {
            $status = $e->getMessage() === 'Order not found' ? 404 : 400;
            return rox_appointment_booking_rest_response(
                data: null,
                code: $status,
                message: $e->getMessage(),
                headers: ['status' => $status]
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
