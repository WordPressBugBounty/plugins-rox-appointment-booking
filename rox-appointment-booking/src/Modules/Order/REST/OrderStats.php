<?php

namespace RoxAppointmentBooking\Modules\Order\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\Order\Services\OrderService;

/**
 * Class OrderStats
 *
 * @package RoxAppointmentBooking\Modules\Order\REST
 * @description Provides order statistics via REST API.
 */
class OrderStats extends AbstractREST
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
    public static string $route = 'orders/stats';

    /**
     * Get the HTTP methods allowed for this route.
     *
     * @return string|array
     */
    protected function getMethods(): string|array
    {
        return 'GET';
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
            
            // Get filters from request
            $filters = [
                'date_from' => $request->get_param('date_from'),
                'date_to' => $request->get_param('date_to'),
            ];

            // Remove empty filters
            $filters = array_filter($filters, function($value) {
                return $value !== null && $value !== '';
            });

            $stats = $orderService->getOrderStats($filters);

            return rox_appointment_booking_rest_response(
                data: $stats,
                message: [
                    'success' => [
                        esc_html__('Order statistics retrieved successfully', 'rox-appointment-booking')
                    ]
                ]
            );

        } catch (\Exception $e) {
            return new WP_Error(
                'stats_retrieval_failed',
                $e->getMessage(),
                ['status' => 500]
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
