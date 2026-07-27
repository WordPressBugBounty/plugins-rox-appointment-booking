<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerBookingsService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * GET /customer-panel/bookings — the logged-in customer's own bookings, grouped
 * (upcoming/past/cancelled) and shaped for the My Bookings view. Scoped strictly
 * to the current customer id resolved server-side; never trusts client input.
 */
class GetMyBookings extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/bookings';

    public static string $usableRoute = '/customer-panel/bookings';

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

        // No customer record yet → an empty (but valid) panel, not an error.
        if (!$customerId) {
            return rox_appointment_booking_rest_response(
                data: [
                    'nextAppointment' => null,
                    'counts' => ['upcoming' => 0, 'past' => 0, 'cancelled' => 0],
                    'groups' => ['upcoming' => [], 'past' => [], 'cancelled' => []],
                ],
                message: esc_html__('No bookings found', 'rox-appointment-booking')
            );
        }

        $service = new CustomerBookingsService();

        return rox_appointment_booking_rest_response(
            data: $service->getGroupedForCustomer($customerId),
            message: esc_html__('Bookings retrieved successfully', 'rox-appointment-booking')
        );
    }
}
