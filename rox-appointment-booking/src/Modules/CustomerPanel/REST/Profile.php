<?php

namespace RoxAppointmentBooking\Modules\CustomerPanel\REST;

use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use RoxAppointmentBooking\Supports\Abstracts\AbstractREST;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerPanelService;
use RoxAppointmentBooking\Modules\CustomerPanel\Services\CustomerProfileService;

if (! defined('ABSPATH')) exit; // Exit if accessed directly

/**
 * GET/POST /customer-panel/profile — read and persist the logged-in customer's
 * own profile (personal info + communication preferences) for the Profile view.
 * Identity is always resolved server-side; the client never supplies a customer
 * id, and email is not editable (it anchors the login identity).
 */
class Profile extends AbstractREST
{
    public static $loadable = true;

    public static string $route = '/customer-panel/profile';

    public static string $usableRoute = '/customer-panel/profile';

    protected function getMethods(): string|array
    {
        return ['GET', 'POST'];
    }

    /**
     * Logged-in customer users only, with a valid REST nonce (both read + write).
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
        $service = new CustomerProfileService();

        if (strtoupper($request->get_method()) === 'POST') {
            $data = $service->saveProfile($request->get_params());

            return rox_appointment_booking_rest_response(
                data: $data,
                message: esc_html__('Profile updated successfully', 'rox-appointment-booking')
            );
        }

        return rox_appointment_booking_rest_response(
            data: $service->getProfile(),
            message: esc_html__('Profile retrieved successfully', 'rox-appointment-booking')
        );
    }
}
