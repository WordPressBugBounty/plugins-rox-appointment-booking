<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPaymentsService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * GET /customer-panel/payments — the logged-in customer's own payment history,
 * shaped for the Payment History view (stat cards + transactions). Scoped
 * strictly to the current customer id resolved server-side; never trusts client
 * input.
 */
class GetMyPayments extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/payments';

    public static string $usableRoute = '/customer-panel/payments';

    protected function getMethods(): string|array
    {
        return 'GET';
    }

    /**
     * Logged-in customer users only, with a valid REST nonce.
     *
     * @param WP_REST_Request $request
     * @return bool
     */
    public function permissionCheck(WP_REST_Request $request): bool
    {
        if (!wp_verify_nonce($request->get_header('X-WP-Nonce'), 'wp_rest')) {
            return false;
        }

        return CustomerPanelService::isCurrentUserCustomer();
    }

    /**
     * @param WP_REST_Request $request
     * @return WP_REST_Response|WP_Error
     */
    public function handleRequest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $customerId = CustomerPanelService::currentCustomerId();

        $service = new CustomerPaymentsService();

        // No customer record yet → an empty (but valid) payload, not an error.
        if (!$customerId) {
            return rox_appointment_booking_rest_response(
                data: $service->getForCustomer(0),
                message: esc_html__('No payments found', 'rox-appointment-booking')
            );
        }

        return rox_appointment_booking_rest_response(
            data: $service->getForCustomer($customerId),
            message: esc_html__('Payments retrieved successfully', 'rox-appointment-booking')
        );
    }
}
